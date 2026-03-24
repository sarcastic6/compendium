<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for typographic cover art generation.
 *
 * Provides deterministic gradient backgrounds and font class selection
 * for the book cover thumbnail component used in the reading entry list.
 */
class CoverArtExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('cover_gradient', $this->coverGradient(...), ['is_safe' => ['html']]),
            new TwigFunction('cover_font_class', $this->coverFontClass(...)),
        ];
    }

    /**
     * Generates an inline CSS style string for a deterministic gradient background.
     *
     * Algorithm: hue = 120 + (workId * 17) % 50
     * Constrains the hue to the 120°–170° green/teal range.
     * The prime multiplier (17) ensures consecutive IDs spread across the
     * range rather than stepping linearly.
     *
     * @param int $workId The work's database ID
     *
     * @return string Inline CSS style attribute value, e.g.
     *                "background: linear-gradient(135deg, hsl(137, 35%, 18%), hsl(147, 30%, 40%));"
     */
    public function coverGradient(int $workId): string
    {
        $hue = 120 + ($workId * 17) % 50;
        $hueLight = $hue + 10;

        return sprintf(
            'background: linear-gradient(135deg, hsl(%d, 35%%, 18%%), hsl(%d, 30%%, 40%%));',
            $hue,
            $hueLight,
        );
    }

    /**
     * Returns the CSS class for the cover title font based on work type.
     *
     * Books use Lora italic (cover-font-book).
     * Fanfiction uses Manrope (cover-font-fanfic).
     *
     * @param string $workType The work type string ('Book' or 'Fanfiction')
     *
     * @return string CSS class name
     */
    public function coverFontClass(string $workType): string
    {
        return $workType === 'Book' ? 'cover-font-book' : 'cover-font-fanfic';
    }
}
