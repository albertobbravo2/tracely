<?php

namespace App\Livewire\Backoffice;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

/**
 * Base de las cinco pantallas del backoffice.
 *
 * Todas hacen lo mismo en su esqueleto: hablar con nuestra propia API por HTTP,
 * paginar el resultado y traducir el fallo a un mensaje. Eso vive aquí y los
 * componentes single-file solo ponen sus campos, su tabla y su formulario.
 *
 * El backoffice va contra la API en vez de contra Eloquent a propósito, igual
 * que ⚡searchfield y ⚡my-shipments: la autorización (`permission:` en cada
 * ruta) y la validación viven ahí, y así no hay dos sitios donde diverjan.
 */
abstract class BackofficeComponent extends Component
{
    public int $page = 1;

    /** @var array<string, mixed> */
    public array $meta = [];

    public ?string $errorMessage = null;

    /**
     * Cargar la página actual del listado.
     *
     * Cada pantalla la implementa contra su propio endpoint y deja el resultado
     * en su propia propiedad (`$shipments`, `$users`, ...), con el nombre del
     * dominio en vez de uno genérico, para que su vista se lea sola.
     */
    abstract protected function loadRows(): void;

    public function mount(): void
    {
        $this->loadRows();
    }

    public function nextPage(): void
    {
        if ($this->page < (int) ($this->meta['last_page'] ?? 1)) {
            $this->page++;
            $this->loadRows();
        }
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
            $this->loadRows();
        }
    }

    /**
     * Volver a pedir el listado tal y como está.
     *
     * Lo llama el botón de `<x-backoffice.alert>`: cuando la API falla, el
     * listado se queda vacío con su mensaje, y sin esto la única salida era
     * recargar la página a mano. Público —a diferencia de `loadRows()`— porque
     * quien lo dispara es la vista.
     */
    public function retry(): void
    {
        $this->loadRows();
    }

    /**
     * Volver a la primera página y recargar. Lo llaman los `updatedX()` de los
     * filtros: cambiar el filtro estando en la página 4 dejaría un listado
     * vacío sin que se entienda por qué.
     */
    protected function resetAndReload(): void
    {
        $this->page = 1;
        $this->loadRows();
    }

    /**
     * Ejecutar una llamada a la API ya autenticada.
     *
     * La petición sale del servidor y no arrastra la cookie de sesión del
     * navegador, así que para la API seríamos un invitado: se acuña un token de
     * un minuto y se borra en `finally`, para que no quede vivo si la conexión
     * falla.
     *
     * El closure recibe la petición configurada, así que cada pantalla usa el
     * verbo que necesita —`->get()`, `->post()`, `->attach(...)->post()`— y
     * puede encadenar varias peticiones con un solo token.
     *
     * Devuelve null si no se pudo conectar; qué decirle al usuario lo decide
     * quien llama, vía `apiErrorMessage()`.
     *
     * @param  callable(PendingRequest): ?Response  $call
     */
    protected function callApi(callable $call): ?Response
    {
        $pending = Http::acceptJson()
            ->timeout(15)
            ->baseUrl(config('services.internal_api.url'));

        $token = auth()->user()?->createToken('backoffice', ['backoffice'], now()->addMinute());

        if ($token) {
            $pending->withToken($token->plainTextToken);
        }

        try {
            return $call($pending);
        } catch (ConnectionException) {
            return null;
        } finally {
            $token?->accessToken->delete();
        }
    }

    /**
     * Quedarse con las filas de una respuesta paginada y guardar el resto del
     * paginador en `$meta`, que es lo que consume `<x-backoffice.pagination>`.
     *
     * El cuerpo de la respuesta es JSON de fuera, así que se comprueba su forma
     * en vez de darla por buena: si no es lo que esperamos, el listado sale
     * vacío en lugar de reventar la pantalla.
     *
     * @return list<array<mixed, mixed>>
     */
    protected function rowsFrom(Response $response): array
    {
        $payload = $response->json();

        if (! is_array($payload)) {
            $this->meta = [];

            return [];
        }

        $this->meta = Arr::except($payload, 'data');

        $rows = $payload['data'] ?? [];

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, is_array(...)));
    }

    /**
     * Buscar en un listado ya cargado la fila que tiene ese id.
     *
     * Lo usan los modales de borrado para poder nombrar lo que se va a borrar:
     * la fila ya está en pantalla —es desde donde se ha pulsado—, así que no
     * hace falta volver a pedírsela a la API solo para escribir su nombre.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    protected function rowById(array $rows, int|string|null $id): array
    {
        if ($id === null) {
            return [];
        }

        foreach ($rows as $row) {
            if (($row['id'] ?? null) === $id) {
                return $row;
            }
        }

        return [];
    }

    /**
     * Dejar el listado vacío y con su mensaje cuando la carga falla.
     */
    protected function failListing(?Response $response, string $fallback): void
    {
        $this->meta = [];
        $this->errorMessage = $this->apiErrorMessage($response, $fallback);
    }

    /**
     * Volcar los errores de validación de la API (422) sobre los campos del
     * formulario, para que `<flux:error name="...">` los pinte donde toca.
     *
     * Las propiedades de los componentes se llaman igual que los campos de la
     * API (`tracking_number`, `history.status`, ...) precisamente para que este
     * mapeo sea directo y no haga falta una tabla de traducción.
     *
     * Devuelve true si la respuesta era de validación y ya está gestionada.
     */
    protected function applyApiValidationErrors(?Response $response): bool
    {
        if ($response === null || $response->status() !== 422) {
            return false;
        }

        $errors = $response->json('errors');

        if (! is_array($errors)) {
            return true;
        }

        foreach ($errors as $field => $messages) {
            foreach ((array) $messages as $message) {
                $this->addError((string) $field, (string) $message);
            }
        }

        return true;
    }

    /**
     * Mensaje para el usuario a partir de una respuesta fallida.
     *
     * El 403 tiene mensaje propio porque es un caso normal, no una avería: la
     * API está partida por permisos (empresas es solo de superadministrador) y
     * el backoffice entero está detrás de un único `role:`, así que un agente
     * puede llegar a una pantalla cuyo endpoint no tiene permiso para usar.
     */
    protected function apiErrorMessage(?Response $response, string $fallback): string
    {
        if ($response === null) {
            return __('No pudimos conectar con el servicio. Inténtalo de nuevo en unos segundos.');
        }

        return match ($response->status()) {
            401 => __('Tu sesión ha caducado. Vuelve a iniciar sesión.'),
            403 => __('Tu rol no tiene permiso para esta operación.'),
            404 => __('No encontramos el registro. Puede que lo haya borrado otra persona.'),
            429 => __('Demasiadas peticiones seguidas. Espera un momento y reinténtalo.'),
            default => $fallback,
        };
    }
}
