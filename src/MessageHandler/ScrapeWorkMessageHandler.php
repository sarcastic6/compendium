<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\ScrapeStatus;
use App\Message\ScrapeWorkMessage;
use App\Repository\WorkRepository;
use App\Scraper\Ao3Scraper;
use App\Scraper\RateLimitException;
use App\Scraper\ScrapingException;
use App\Service\ImportService;
use App\Service\WorkService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[AsMessageHandler]
final class ScrapeWorkMessageHandler
{
    /**
     * Maximum number of manual requeues for rate-limit / transport-error cases.
     * After this many attempts the work is marked as failed; the user can retry
     * manually via "Refresh from source" on the reading entry detail page.
     * This bounds the Pending state so stuck jobs eventually produce a clear failure.
     */
    private const MAX_ATTEMPTS = 10;

    public function __construct(
        private readonly Ao3Scraper $scraper,
        private readonly WorkService $workService,
        private readonly ImportService $importService,
        private readonly WorkRepository $workRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ScrapeWorkMessage $message): void
    {
        $work = $this->workRepository->find($message->workId);
        if ($work === null) {
            $this->logger->warning(
                'ScrapeWorkMessageHandler: Work {id} not found, discarding message.',
                ['id' => $message->workId],
            );

            return;
        }

        // Catch-all wraps all inner try/catch blocks.
        // Ensures scrapeStatus always leaves Pending even on unexpected errors
        // (Doctrine constraint violations, unexpected nulls, etc.). Without this,
        // scrapeStatus stays Pending indefinitely if something unexpected fires.
        try {
            try {
                $scraped      = $this->scraper->scrape($message->url);
                $importResult = $this->importService->mapToWorkFormDto($scraped);
                $this->workService->refreshWork($work, $importResult->dto, fullReplace: true);
                $work->setScrapeStatus(ScrapeStatus::Complete);
                $this->entityManager->flush();

                $this->logger->info(
                    'ScrapeWorkMessageHandler: Work {id} ({title}) scraped successfully.',
                    ['id' => $message->workId, 'title' => $work->getTitle()],
                );
            } catch (RateLimitException $e) {
                $this->requeueOrFail($message, $e->getRetryAfterSeconds(), 'rate limiting');
            } catch (ScrapingException $e) {
                $work->setScrapeStatus(ScrapeStatus::Failed);
                $this->entityManager->flush();

                $this->logger->error(
                    'ScrapeWorkMessageHandler: Work {id} ({title}) scrape failed permanently: {message}',
                    [
                        'id'      => $message->workId,
                        'title'   => $work->getTitle(),
                        'message' => $e->getMessage(),
                    ],
                );
            } catch (TransportExceptionInterface $e) {
                $this->requeueOrFail($message, null, 'transport error');

                $this->logger->warning(
                    'ScrapeWorkMessageHandler: Work {id} transport error on attempt {attempt}, requeued.',
                    [
                        'id'      => $message->workId,
                        'attempt' => $message->attempt,
                        'error'   => $e->getMessage(),
                    ],
                );
            }
        } catch (\Throwable $e) {
            // Something unexpected — mark as failed so the status leaves Pending.
            // Re-throw so Messenger can apply its retry_strategy and route to the
            // failed transport after max retries.
            $work->setScrapeStatus(ScrapeStatus::Failed);
            $this->entityManager->flush();

            $this->logger->error(
                'ScrapeWorkMessageHandler: Work {id} unexpected error, marked as failed: {message}',
                ['id' => $message->workId, 'message' => $e->getMessage()],
            );

            throw $e;
        }
    }

    /**
     * Requeues the message with exponential backoff, or marks the work as failed
     * if the max attempt cap has been reached.
     */
    private function requeueOrFail(ScrapeWorkMessage $message, ?int $retryAfterSeconds, string $context): void
    {
        if ($message->attempt >= self::MAX_ATTEMPTS) {
            $work = $this->workRepository->find($message->workId);
            if ($work !== null) {
                $work->setScrapeStatus(ScrapeStatus::Failed);
                $this->entityManager->flush();
            }

            $this->logger->error(
                'ScrapeWorkMessageHandler: Work {id} max attempts reached after {context}, marked as failed.',
                ['id' => $message->workId, 'context' => $context],
            );

            return;
        }

        $delaySecs = $retryAfterSeconds
            ?? (2 ** $message->attempt) + random_int(0, 1000) / 1000;
        $delayMs = (int) ($delaySecs * 1000);

        $this->messageBus->dispatch(
            new ScrapeWorkMessage($message->workId, $message->url, $message->attempt + 1),
            [new DelayStamp($delayMs)],
        );

        $this->logger->info(
            'ScrapeWorkMessageHandler: Work {id} {context}, requeued with {delay}s delay (attempt {attempt}).',
            [
                'id'      => $message->workId,
                'context' => $context,
                'delay'   => $delaySecs,
                'attempt' => $message->attempt,
            ],
        );
    }
}
