<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tracking_number
 * @property int $sender_id
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
])]
class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory;

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
     * Eventos del historial de este envío.
     *
     * @return HasMany<ShipmentHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(ShipmentHistory::class);
    }
}
