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
            // Return the URL unchanged if its scheme is http or https, otherwise null.
            // Use this filter before rendering any stored URL in an href attribute to
            // prevent javascript: URL injection — Twig's auto-escaping does not strip
            // javascript: schemes, it only HTML-encodes special characters.
            new TwigFilter('safe_url', static function (?string $url): ?string {
                if ($url === null || $url === '') {
                    return null;
                }
                $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

                return in_array($scheme, ['http', 'https'], true) ? $url : null;
            }),
        ];
    }
}
