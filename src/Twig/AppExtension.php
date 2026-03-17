<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Application-level Twig extensions.
 */
class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // Re-index an associative array to a plain list of its values.
            // Twig has |keys but no |values, so this fills that gap.
            new TwigFilter('values', 'array_values'),
        ];
    }
}
