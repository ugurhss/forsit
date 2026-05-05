<?php

namespace App\Enums;

enum ReservationStatus: string
{
    //rezervasyon kaydı status durumunu belirler
    case Active = 'active'; 
    case Released = 'released';
    case Expired = 'expired';

    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}
