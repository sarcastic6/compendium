<?php

declare(strict_types=1);

namespace App\Enum;

enum SourceType: string
{
    case AO3 = 'AO3';
    case FFN = 'FFN';
    case Wattpad = 'Wattpad';
    case Manual = 'Manual';
}
