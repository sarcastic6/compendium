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
     * Curated gradient pairs for typographic cover art.
     *
     * Each entry is [dark stop, light stop] as inline CSS color values.
     * Pairs are chosen to be visually distinct and work well against white text,
     * while remaining cohesive alongside the forest-green UI palette.
     * Selection is deterministic: pair = GRADIENTS[workId % count].
     */
    private const GRADIENTS = [
        // Greens & teals
        ['hsl(140, 38%, 16%)', 'hsl(152, 32%, 36%)'],  // deep forest → mid green
        ['hsl(168, 42%, 18%)', 'hsl(175, 36%, 38%)'],  // dark teal → seafoam
        ['hsl(155, 30%, 22%)', 'hsl(162, 28%, 42%)'],  // moss → sage
        // Blues & indigos
        ['hsl(220, 45%, 20%)', 'hsl(228, 38%, 42%)'],  // midnight blue → slate blue
        ['hsl(240, 35%, 22%)', 'hsl(248, 30%, 44%)'],  // deep indigo → soft indigo
        ['hsl(205, 50%, 18%)', 'hsl(212, 42%, 38%)'],  // ocean → steel blue
        // Purples
        ['hsl(265, 35%, 22%)', 'hsl(272, 30%, 44%)'],  // deep violet → muted purple
        ['hsl(285, 30%, 20%)', 'hsl(292, 26%, 42%)'],  // plum → dusty mauve
        // Warm reds & burgundies
        ['hsl(340, 40%, 20%)', 'hsl(348, 34%, 40%)'],  // deep rose → dusty rose
        ['hsl(4, 42%, 20%)',   'hsl(12, 36%, 40%)'],   // burgundy → warm terracotta
        ['hsl(16, 45%, 20%)',  'hsl(24, 38%, 40%)'],   // burnt sienna → clay
        // Ambers & golds
        ['hsl(36, 48%, 18%)',  'hsl(44, 42%, 38%)'],   // dark amber → warm gold
        ['hsl(28, 44%, 20%)',  'hsl(36, 38%, 40%)'],   // umber → ochre
        // Slates & neutrals
        ['hsl(200, 22%, 20%)', 'hsl(208, 18%, 40%)'],  // dark slate → cool grey
        ['hsl(180, 18%, 20%)', 'hsl(188, 16%, 40%)'],  // charcoal teal → pewter
    ];

    /**
     * Generates an inline CSS style string for a deterministic gradient background.
     *
     * Selects from a curated set of 15 gradient pairs using workId % count,
     * giving consistent but visually varied cover art across the entry list.
     *
     * @param int $workId The work's database ID
     *
     * @return string Inline CSS style attribute value
     */
    public function coverGradient(int $workId): string
    {
        $pair = self::GRADIENTS[$workId % count(self::GRADIENTS)];

        return sprintf(
            'background: linear-gradient(135deg, %s, %s);',
            $pair[0],
            $pair[1],
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
