<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Dto\ImportResult;
use App\Dto\WorkFormDto;
use App\Entity\Work;
use App\Enum\ScrapeStatus;
use App\Enum\WorkType;
use App\Message\ScrapeWorkMessage;
use App\MessageHandler\ScrapeWorkMessageHandler;
use App\Repository\WorkRepository;
use App\Scraper\Ao3Scraper;
use App\Scraper\RateLimitException;
use App\Scraper\ScrapedWorkDto;
use App\Scraper\ScrapingException;
use App\Service\ImportService;
use App\Service\WorkService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class ScrapeWorkMessageHandlerTest extends TestCase
{
    private function makeWork(): Work
    {
        $work = new Work(WorkType::Fanfiction, 'Test Work');
        $ref  = new \ReflectionProperty(Work::class, 'id');
        $ref->setValue($work, 1);

        return $work;
    }

    private function makeHandler(
        Ao3Scraper $scraper,
        WorkService $workService,
        ImportService $importService,
        WorkRepository $workRepository,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
    ): ScrapeWorkMessageHandler {
        return new ScrapeWorkMessageHandler(
            $scraper,
            $workService,
            $importService,
            $workRepository,
            $entityManager,
            $messageBus,
            new NullLogger(),
        );
    }

    public function test_handler_sets_complete_on_success(): void
    {
        $work    = $this->makeWork();
        $message = new ScrapeWorkMessage(1, 'https://archiveofourown.org/works/1');

        $workRepository = $this->createMock(WorkRepository::class);
        $workRepository->expects($this->atLeastOnce())
            ->method('find')
            ->with(1)
            ->willReturn($work);

        $scraped = new ScrapedWorkDto();
        $scraped->title = 'Scraped Title';

        $importResult = new ImportResult(new WorkFormDto());

        $scraper = $this->createMock(Ao3Scraper::class);
        $scraper->expects($this->once())
            ->method('scrape')
            ->with($message->url)
            ->willReturn($scraped);

        $importService = $this->createMock(ImportService::class);
        $importService->expects($this->once())
            ->method('mapToWorkFormDto')
            ->with($scraped)
            ->willReturn($importResult);

        $workService = $this->createMock(WorkService::class);
        $workService->expects($this->once())
            ->method('refreshWork')
            ->with($work, $importResult->dto, true);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $handler = $this->makeHandler(
            $scraper,
            $workService,
            $importService,
            $workRepository,
            $entityManager,
            $this->createStub(MessageBusInterface::class),
        );

        $handler($message);

        $this->assertSame(ScrapeStatus::Complete, $work->getScrapeStatus());
    }

    public function test_handler_requeues_on_rate_limit(): void
    {
        $work    = $this->makeWork();
        $message = new ScrapeWorkMessage(1, 'https://archiveofourown.org/works/1', attempt: 0);

        $workRepository = $this->createStub(WorkRepository::class);
        $workRepository->method('find')->willReturn($work);

        $scraper = $this->createMock(Ao3Scraper::class);
        $scraper->expects($this->once())
            ->method('scrape')
            ->willThrowException(new RateLimitException('https://archiveofourown.org/works/1', 30));

        // A new ScrapeWorkMessage with attempt=1 and 30 000 ms delay must be dispatched
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(static function (ScrapeWorkMessage $msg): bool {
                    return $msg->workId === 1 && $msg->attempt === 1;
                }),
                $this->callback(static function (array $stamps): bool {
                    foreach ($stamps as $stamp) {
                        if ($stamp instanceof DelayStamp && $stamp->getDelay() === 30000) {
                            return true;
                        }
                    }

                    return false;
                }),
            )
            ->willReturn(new Envelope(new ScrapeWorkMessage(1, 'https://archiveofourown.org/works/1', 1)));

        // Status must NOT be set to failed (flush never called) — job is still in flight
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $handler = $this->makeHandler(
            $scraper,
            $this->createStub(WorkService::class),
            $this->createStub(ImportService::class),
            $workRepository,
            $entityManager,
            $messageBus,
        );

        $handler($message);

        // scrapeStatus was never changed — still null (pending was set by import service, not handler)
        $this->assertNull($work->getScrapeStatus());
    }

    public function test_handler_sets_failed_on_scraping_exception(): void
    {
        $work    = $this->makeWork();
        $message = new ScrapeWorkMessage(1, 'https://archiveofourown.org/works/1');

        $workRepository = $this->createStub(WorkRepository::class);
        $workRepository->method('find')->willReturn($work);

        $scraper = $this->createMock(Ao3Scraper::class);
        $scraper->expects($this->once())
            ->method('scrape')
            ->willThrowException(new ScrapingException(
                'https://archiveofourown.org/works/1',
                'Parse error',
            ));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $handler = $this->makeHandler(
            $scraper,
            $this->createStub(WorkService::class),
            $this->createStub(ImportService::class),
            $workRepository,
            $entityManager,
            $this->createStub(MessageBusInterface::class),
        );

        $handler($message);

        $this->assertSame(ScrapeStatus::Failed, $work->getScrapeStatus());
    }
}
