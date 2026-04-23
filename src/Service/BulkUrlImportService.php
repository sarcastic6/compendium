<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\BulkUrlImportSummary;
use App\Entity\Work;
use App\Enum\ScrapeStatus;
use App\Enum\SourceType;
use App\Enum\WorkType;
use App\Message\ScrapeWorkMessage;
use App\Repository\WorkRepository;
use App\Scraper\ScraperRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class BulkUrlImportService
{
    public function __construct(
        private readonly ScraperRegistry $scraperRegistry,
        private readonly WorkRepository $workRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Parses a block of text (one URL per line), creates Work stubs for new AO3 URLs,
     * and dispatches background scrape jobs. Returns a summary of the results.
     */
    public function import(string $rawInput): BulkUrlImportSummary
    {
        $summary = new BulkUrlImportSummary();
        $lines   = explode("\n", $rawInput);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // Basic URL syntax check before hitting the scraper registry
            if (!filter_var($line, FILTER_VALIDATE_URL)) {
                $summary->invalidUrls[] = $line;
                continue;
            }

            $scraper = $this->scraperRegistry->getScraperForUrl($line);

            if ($scraper === null) {
                // Valid URL syntax but not a supported platform (e.g. FFN, Wattpad, random site)
                $summary->invalidUrls[] = $line;
                continue;
            }

            $canonical = $scraper->canonicalizeUrl($line);
            $existing  = $this->workRepository->findByLink($canonical);

            if ($existing !== null) {
                $summary->skippedUrls[$canonical] = $existing->getTitle();
                continue;
            }

            $work = new Work(WorkType::Fanfiction, $canonical);
            $work->setSourceType(SourceType::AO3);
            $work->setLink($canonical);
            $work->setScrapeStatus(ScrapeStatus::Pending);

            $this->entityManager->persist($work);
            $this->entityManager->flush();

            $workId = $work->getId();

            if ($workId === null) {
                // Should never happen after a successful flush, but guard for static analysis
                continue;
            }

            $this->messageBus->dispatch(new ScrapeWorkMessage($workId, $canonical));
            $summary->worksQueued++;

            $this->logger->info(
                'BulkUrlImportService: queued ScrapeWorkMessage for work {id}.',
                ['id' => $workId, 'url' => $canonical],
            );
        }

        return $summary;
    }
}
