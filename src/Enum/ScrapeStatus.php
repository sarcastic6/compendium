<?php

declare(strict_types=1);

namespace App\Enum;

enum ScrapeStatus: string
{
    case Pending  = 'pending';
    case Complete = 'complete';
    case Failed   = 'failed';
}
