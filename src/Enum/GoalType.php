<?php

declare(strict_types=1);

namespace App\Enum;

enum GoalType: string
{
    case EntriesCompleted = 'entries_completed';
    case WordsRead = 'words_read';
}
