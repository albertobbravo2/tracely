<?php

namespace App\Http\Controllers\Api;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportShipmentsRequest;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Alta masiva de pedidos desde un CSV.
 *
 * Controlador aparte y no un método más de `ShipmentController` porque ese ya
 * carga con la caché de Redis de la consulta pública y no tienen nada que ver.
 *
 * Dos niveles de error, a propósito:
 *
 * - El fichero no sirve (vacío, sin las columnas obligatorias, demasiadas
 *   filas): 422 y no se importa nada.
 * - Una fila no sirve: se anota en el informe y las demás siguen adelante. Un
 *   CSV de mil líneas no puede caerse entero por una fecha mal escrita, así que
 *   tampoco hay transacción que envuelva el bucle.
 */
class ShipmentImportController extends Controller
{
    /** Columnas que el fichero debe traer sí o sí. */
    private const REQUIRED_COLUMNS = [
        'tracking_number',
        'receiver_name',
        'origin',
        'destination',
        'estimated_delivery_date',
    ];

    /** Columnas que se leen; cualquier otra del fichero se ignora. */
    private const KNOWN_COLUMNS = [
        ...self::REQUIRED_COLUMNS,
        'status',
        'history_location',
        'history_description',
    ];

    /**
     * Tope de filas por fichero. El import es síncrono: sin un límite, un
     * fichero grande se comería el tiempo de la petición.
     */
    private const MAX_ROWS = 1000;

    public function __invoke(ImportShipmentsRequest $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');

        ['headers' => $headers, 'rows' => $rows] = $this->readCsv((string) $file->getRealPath());

        $user = $request->user();
        $created = 0;
        $errors = [];

        // Guías ya vistas en este mismo fichero: la regla `unique` no las caza,
        // porque la primera aparición ya se ha insertado cuando llega la segunda.
        $seen = [];

        foreach ($rows as $row) {
            $line = $row['line'];
            $values = $row['values'];

            if (count($values) !== count($headers)) {
                $errors[] = $this->error($line, null, [
                    __('La fila tiene :filas columnas y la cabecera :cabecera.', [
                        'filas' => count($values),
                        'cabecera' => count($headers),
                    ]),
                ]);

                continue;
            }

            /** @var array<string, string> $data */
            $data = array_combine($headers, array_map(
                fn ($value) => trim((string) $value),
                $values,
            ));

            $payload = Arr::only($data, self::KNOWN_COLUMNS);

            // Una celda vacía es "no viene", no "cadena vacía": así `status` cae
            // al valor por defecto de la columna en vez de fallar la validación.
            $payload = array_filter($payload, fn (string $value) => $value !== '');

            if (isset($payload['estimated_delivery_date'])) {
                $payload['estimated_delivery_date'] = $this->normalizeDate($payload['estimated_delivery_date']);
            }

            $trackingNumber = $payload['tracking_number'] ?? null;

            if ($trackingNumber !== null && isset($seen[$trackingNumber])) {
                $errors[] = $this->error($line, $trackingNumber, [
                    __('Este número de guía ya aparece en la línea :linea del fichero.', [
                        'linea' => $seen[$trackingNumber],
                    ]),
                ]);

                continue;
            }

            $validator = Validator::make($payload, $this->rules());

            if ($validator->fails()) {
                $errors[] = $this->error($line, $trackingNumber, array_values($validator->errors()->all()));

                continue;
            }

            try {
                $this->createShipment($validator->validated(), $user);
            } catch (\Throwable $e) {
                $errors[] = $this->error($line, $trackingNumber, [
                    __('No pudimos guardar esta fila. Revísala e inténtalo de nuevo.'),
                ]);

                report($e);

                continue;
            }

            $seen[(string) $trackingNumber] = $line;
            $created++;
        }

        // 200 aunque haya filas fallidas: el fichero se ha procesado, y lo que
        // pasó con cada fila lo cuenta el informe.
        return response()->json([
            'total' => count($rows),
            'created' => $created,
            'failed' => count($errors),
            'errors' => $errors,
        ]);
    }

    /**
     * Reglas de una fila: las mismas de `StoreShipmentRequest` más las dos
     * columnas del evento inicial.
     *
     * `sender_id` y `company_id` no están, igual que allí: el remitente es
     * siempre quien sube el fichero, y la empresa sale de su cuenta.
     *
     * @return array<string, array<mixed>>
     */
    private function rules(): array
    {
        return [
            'tracking_number' => ['required', 'string', 'max:255', 'unique:shipments,tracking_number'],
            'receiver_name' => ['required', 'string', 'max:255'],
            'origin' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'estimated_delivery_date' => ['required', 'date'],
            'status' => ['sometimes', 'required', Rule::enum(ShipmentStatus::class)],
            'history_location' => ['sometimes', 'required', 'string', 'max:255'],
            'history_description' => ['sometimes', 'required', 'string'],
        ];
    }

    /**
     * Crear el pedido de una fila ya validada.
     *
     * El evento inicial va por `Shipment::$initialHistory`, que es el mismo
     * camino que usa el alta del backoffice: lo crea `ShipmentObserver`. Solo se
     * monta si la fila trae ubicación o descripción — si no vienen, el pedido
     * nace sin historial en vez de inventarle uno.
     *
     * @param  array<string, mixed>  $data
     */
    private function createShipment(array $data, ?User $user): void
    {
        $status = $data['status'] ?? ShipmentStatus::Pendiente->value;

        $shipment = new Shipment([
            ...Arr::except($data, ['history_location', 'history_description']),
            'status' => $status,
            'sender_id' => $user?->id,
            'company_id' => $user?->company_id,
        ]);

        if (isset($data['history_location']) || isset($data['history_description'])) {
            $shipment->initialHistory = [
                // El evento nace con el estado que trae el pedido, no siempre
                // "pendiente": un fichero puede dar de alta envíos ya en curso.
                'status' => $status,
                'location' => $data['history_location'] ?? null,
                'description' => $data['history_description'] ?? null,
                'recorded_at' => now(),
            ];
        }

        $shipment->save();
    }

    /**
     * Leer el CSV y devolver su cabecera normalizada y sus filas con el número
     * de línea, que es lo que hace útil el informe de errores.
     *
     * @return array{headers: list<string>, rows: list<array{line: int, values: list<string|null>}>}
     *
     * @throws ValidationException si el fichero no sirve como fichero.
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->fail(__('No pudimos leer el fichero.'));
        }

        try {
            $headerLine = fgets($handle);

            if ($headerLine === false) {
                $this->fail(__('El fichero está vacío.'));
            }

            $headerLine = preg_replace('/^\xEF\xBB\xBF/', '', rtrim($headerLine, "\r\n")) ?? '';

            $delimiter = $this->detectDelimiter($headerLine);
            $headers = $this->parseHeader($headerLine, $delimiter);

            $missing = array_diff(self::REQUIRED_COLUMNS, $headers);

            if ($missing !== []) {
                $this->fail(__('Al fichero le faltan columnas obligatorias: :columnas.', [
                    'columnas' => implode(', ', $missing),
                ]));
            }

            $rows = [];
            $line = 1;

            while (($values = fgetcsv($handle, null, $delimiter, '"', '\\')) !== false) {
                $line++;

                // Excel deja líneas en blanco al final del fichero: no son filas.
                if ($this->isEmptyRow($values)) {
                    continue;
                }

                if (count($rows) >= self::MAX_ROWS) {
                    $this->fail(__('El fichero supera las :max filas. Divídelo en varios ficheros.', [
                        'max' => self::MAX_ROWS,
                    ]));
                }

                $rows[] = ['line' => $line, 'values' => $values];
            }

            if ($rows === []) {
                $this->fail(__('El fichero no tiene ninguna fila que importar.'));
            }

            return ['headers' => $headers, 'rows' => $rows];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Delimitador del fichero: se detecta `;` además de `,` porque es lo que
     * exporta Excel en español, y con el delimitador equivocado la cabecera
     * entera se leería como una sola columna.
     */
    private function detectDelimiter(string $headerLine): string
    {
        return substr_count($headerLine, ';') > substr_count($headerLine, ',') ? ';' : ',';
    }

    /**
     * Cabecera normalizada a minúsculas y sin espacios.
     *
     * Se quita el BOM que Excel escribe al principio del fichero: sin quitarlo,
     * la primera columna se llamaría "\u{FEFF}tracking_number" y no la
     * reconoceríamos como `tracking_number`.
     *
     * @return list<string>
     */
    private function parseHeader(string $headerLine, string $delimiter): array
    {
        return array_map(
            fn ($header) => mb_strtolower(trim((string) $header)),
            str_getcsv($headerLine, $delimiter, '"', '\\'),
        );
    }

    /**
     * @param  list<string|null>  $values
     */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Fecha de entrega en formato de base de datos.
     *
     * Se aceptan los dos formatos con los que llega un CSV real y se descarta
     * el resto devolviéndolo tal cual, para que falle la regla `date` y el
     * usuario vea el error en su fila. `Carbon::parse` a secas no vale aquí:
     * con `03/04/2026` elige mes o día por su cuenta y sin avisar.
     */
    private function normalizeDate(string $value): string
    {
        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            if (Carbon::hasFormat($value, $format)) {
                return Carbon::createFromFormat($format, $value)->toDateString();
            }
        }

        return $value;
    }

    /**
     * @param  list<string>  $messages
     * @return array{line: int, tracking_number: string|null, errors: list<string>}
     */
    private function error(int $line, ?string $trackingNumber, array $messages): array
    {
        return [
            'line' => $line,
            'tracking_number' => $trackingNumber,
            'errors' => $messages,
        ];
    }

    /**
     * @throws ValidationException
     */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
