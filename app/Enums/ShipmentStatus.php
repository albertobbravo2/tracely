<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case Pendiente = 'pendiente';
    case EnTransito = 'en_transito';
    case EnAduana = 'en_aduana';
    case Entregado = 'entregado';
    case Incidencia = 'incidencia';

    // transcribe el valor del enum a un label legible para el usuario
    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnTransito => 'En tránsito',
            self::EnAduana => 'En aduana',
            self::Entregado => 'Entregado',
            self::Incidencia => 'Incidencia',
        };
    }
}
