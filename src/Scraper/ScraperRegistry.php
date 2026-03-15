<?php

declare(strict_types=1);

namespace App\Scraper;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Holds all registered scrapers and resolves the correct one for a URL.
 * Scrapers are auto-registered via autoconfigure (ScraperInterface tag).
 */
class ScraperRegistry
{
    /** @param iterable<ScraperInterface> $scrapers */
    public function __construct(
        #[AutowireIterator(ScraperInterface::class)]
        private readonly iterable $scrapers,
    ) {
    }

    /**
     * Returns the first scraper that supports the given URL, or null if none does.
     */
    public function getScraperForUrl(string $url): ?ScraperInterface
    {
        foreach ($this->scrapers as $scraper) {
            if ($scraper->supports($url)) {
                return $scraper;
            }
        }

        return null;
    }
}
