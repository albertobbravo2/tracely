<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $shipment_id
 * @property ShipmentStatus $status
 * @property string|null $location
 * @property string|null $description
 * @property Carbon $recorded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'shipment_id',
    'status',
    'location',
    'description',
    'recorded_at',
])]
class ShipmentHistory extends Model
{
    // formatear fecha
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'recorded_at' => 'datetime',
        ];
    }

    // sacar historial de un pedido
    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
