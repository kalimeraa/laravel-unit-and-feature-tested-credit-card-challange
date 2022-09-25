<?php

declare(strict_types=1);

namespace App\Enums;

enum CurrencyType: string
{
    case TRY = 'TRY';
    case EUR = 'EUR';
    case LEU = 'LEU';
    case USD = 'USD';
    
    public static function toString(): string
    {
        $strings = [];
        foreach(self::cases() as $case) {
            $strings[] = $case->value;
        }
        return implode(',',$strings);
    }
}
