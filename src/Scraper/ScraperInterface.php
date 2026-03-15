<?php

declare(strict_types=1);

namespace App\Scraper;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contract for all source-specific work metadata scrapers.
 *
 * The #[AutoconfigureTag] attribute ensures all implementing services are
 * tagged with this interface's FQCN, allowing ScraperRegistry to collect
 * them via #[AutowireIterator].
 *
 * CRITICAL GUARDRAIL — METADATA ONLY:
 * Implementations must NEVER extract, store, or process any story or chapter
 * body content. Only metadata is permitted: title, author names, summary,
 * tags, word count, chapter count, dates, series info, language, and source
 * URL. Code that navigates to chapter pages or reads prose content violates
 * this contract and must not be merged.
 */
#[AutoconfigureTag]
interface ScraperInterface
{
    /**
     * Returns true if this scraper can handle the given URL.
     */
    public function supports(string $url): bool;

    /**
     * Fetches metadata for the work at the given URL.
     *
     * @throws ScrapingException on HTTP errors or unrecoverable parse failures
     */
    public function scrape(string $url): ScrapedWorkDto;
}
