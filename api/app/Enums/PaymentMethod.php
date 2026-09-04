<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Qr = 'qr';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Card => 'Kad',
            self::Qr => 'QR / DuitNow',
            self::Other => 'Lain-lain',
        };
    }
}
