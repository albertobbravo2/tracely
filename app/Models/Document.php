<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $shipment_id
 * @property string $document_name
 * @property string $file_path
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'shipment_id',
    'document_name',
    'file_path',
    'status',
])]
// `file_path` es una ruta interna del disco `documents`: al cliente no le sirve
// de nada y solo revela cómo organizamos el almacenamiento. Lo que consume es
// `download_url`, que apunta al endpoint protegido por permisos.
#[Hidden('file_path')]
#[Appends('download_url')]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    /**
     * Envío al que pertenece este documento.
     *
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * URL del endpoint de descarga.
     */
    protected function downloadUrl(): Attribute
    {
        return Attribute::get(fn (): string => route('documents.download', $this));
    }
}
