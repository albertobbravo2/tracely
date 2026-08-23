<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use App\Observers\ShipmentObserver;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tracking_number
 * @property int|null $sender_id
 * @property string $receiver_name
 * @property string $origin
 * @property string $destination
 * @property string $estimated_delivery_date
 * @property ShipmentStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tracking_number',
    'sender_id',
    'receiver_name',
    'origin',
    'destination',
    'estimated_delivery_date',
    'status',
    'company_id',
])]
#[ObservedBy([ShipmentObserver::class])]
class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory;

    /**
     * Primer evento del historial, cuando el alta lo trae consigo.
     *
     * No es un atributo del modelo: es una propiedad PHP normal, así que
     * Eloquent no la persiste ni la serializa. Solo existe para que el dato
     * llegue desde el controlador hasta `ShipmentObserver::created()`, que es
     * quien crea de verdad el `ShipmentHistory` — un observer recibe el modelo,
     * no la request, y sin esto no tendría forma de ver esos campos.
     *
     * @var array<string, mixed>|null
     */
    public ?array $initialHistory = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
        ];
    }

    /**
     * Usuario que registra el envío.
     *
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Usuarios finales a los que este envío les aparece en su lista.
     *
     * No confundir con `sender()`: ese es el agente que registró el envío.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Eventos del historial de este envío.
     *
     * @return HasMany<ShipmentHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(ShipmentHistory::class);
    }

    /**
     * Documentos adjuntos a este envío.
     *
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
