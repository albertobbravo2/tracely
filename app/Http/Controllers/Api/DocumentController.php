<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $documents = Document::query()
            ->when(
                $request->integer('shipment_id'),
                fn ($query, $shipmentId) => $query->where('shipment_id', $shipmentId),
            )
            ->paginate(15);

        return response()->json($documents);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shipment_id' => ['required', 'integer', 'exists:shipments,id'],
            'document_name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        // El path lo decide el servidor, nunca el cliente: un path arbitrario
        // dejaría escribir fuera del área de almacenamiento. Disco `documents`
        // (privado) porque son documentos del envío, no ficheros públicos.
        $data['file_path'] = $request->file('file')->store(
            (string) $data['shipment_id'],
            'documents',
        );

        unset($data['file']);

        return response()->json(Document::create($data), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Document $document): JsonResponse
    {
        return response()->json($document);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate([
            'shipment_id' => ['sometimes', 'required', 'integer', 'exists:shipments,id'],
            'document_name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'string', 'max:255'],
            'file' => ['sometimes', 'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        // Sustituir el fichero: se guarda el nuevo y se borra el anterior —
        // pero solo después de que el update persista, para no perder el
        // fichero si la escritura falla.
        $previousPath = null;

        if ($request->hasFile('file')) {
            $previousPath = $document->file_path;

            $data['file_path'] = $request->file('file')->store(
                (string) ($data['shipment_id'] ?? $document->shipment_id),
                'documents',
            );

            unset($data['file']);
        }

        $document->update($data);

        if ($previousPath !== null) {
            Storage::disk('documents')->delete($previousPath);
        }

        return response()->json($document);
    }

    /**
     * Descargar el fichero del documento.
     *
     * El fichero se sirve por aquí y no con una URL firmada porque una URL
     * firmada se salta el middleware: quien tuviera el enlace entraría sin
     * sesión ni permiso.
     */
    public function download(Document $document): StreamedResponse
    {
        abort_unless(Storage::disk('documents')->exists($document->file_path), 404);

        return Storage::disk('documents')->download(
            $document->file_path,
            $document->document_name,
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Document $document): JsonResponse
    {
        $document->delete();

        Storage::disk('documents')->delete($document->file_path);

        return response()->json(status: 204);
    }
}
