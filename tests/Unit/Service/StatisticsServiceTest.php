<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Repository\ReadingEntryRepository;
use App\Service\StatisticsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StatisticsServiceTest extends TestCase
{
    private StatisticsService $service;

    /** @var ReadingEntryRepository&MockObject */
    private ReadingEntryRepository $repository;

    protected function setUp(): void
    {
        // createStub — most tests only need return values, not call verification.
        // Tests that verify expectations create their own inline mocks.
        $this->repository = $this->createStub(ReadingEntryRepository::class);
        $this->service = new StatisticsService($this->repository);
    }

    public function test_finish_rate_calculates_correctly(): void
    {
        $user = $this->createStub(User::class);

        $this->repository->method('countFinished')->willReturn(7);
        $this->repository->method('countStarted')->willReturn(10);

        $rate = $this->service->getFinishRate($user, null);

        $this->assertSame(70.0, $rate);
    }

    public function test_finish_rate_is_zero_when_no_started_entries(): void
    {
        $user = $this->createStub(User::class);

        $this->repository->method('countFinished')->willReturn(0);
        $this->repository->method('countStarted')->willReturn(0);

        $rate = $this->service->getFinishRate($user, null);

        // Must not divide by zero
        $this->assertSame(0.0, $rate);
    }

    public function test_finish_rate_rounds_to_one_decimal(): void
    {
        $user = $this->createStub(User::class);

        $this->repository->method('countFinished')->willReturn(1);
        $this->repository->method('countStarted')->willReturn(3);

        $rate = $this->service->getFinishRate($user, null);

        $this->assertSame(33.3, $rate);
    }

    public function test_get_dashboard_summary_returns_expected_keys(): void
    {
        $user = $this->createStub(User::class);

        $this->repository->method('countFinished')->willReturn(5);
        $this->repository->method('countStarted')->willReturn(8);
        $this->repository->method('countByUser')->willReturn(10);
        $this->repository->method('getWordCountStats')->willReturn([
            'totalWords' => 50000,
            'averageWords' => 10000.0,
            'entryCount' => 5,
        ]);
        $this->repository->method('getAverageRating')->willReturn(4.2);
        $this->repository->method('countPinned')->willReturn(2);
        $this->repository->method('countByStatus')->willReturn(['Reading' => 3, 'Completed' => 5]);
        $this->repository->method('countByWorkType')->willReturn(['Book' => 4, 'Fanfiction' => 6]);
        $this->repository->method('findAvailableYears')->willReturn([2025, 2024]);

        $summary = $this->service->getDashboardSummary($user, null);

        $expectedKeys = [
            'entryCount',
            'finishedCount',
            'wordCountStats',
            'finishRate',
            'averageRating',
            'pinnedCount',
            'byStatus',
            'byWorkType',
            'availableYears',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $summary, "Missing key: $key");
        }
    }

    public function test_all_time_entry_count_uses_count_by_user(): void
    {
        $user = $this->createStub(User::class);

        $this->repository->method('countFinished')->willReturn(5);
        $this->repository->method('countStarted')->willReturn(8);
        $this->repository->method('countByUser')->willReturn(12);
        $this->repository->method('getWordCountStats')->willReturn(['totalWords' => 0, 'averageWords' => null, 'entryCount' => 0]);
        $this->repository->method('getAverageRating')->willReturn(null);
        $this->repository->method('countPinned')->willReturn(0);
        $this->repository->method('countByStatus')->willReturn([]);
        $this->repository->method('countByWorkType')->willReturn([]);
        $this->repository->method('findAvailableYears')->willReturn([]);

        $summary = $this->service->getDashboardSummary($user, null);

        // All-time: entryCount must be the total from countByUser, not countFinished
        $this->assertSame(12, $summary['entryCount']);
    }

    public function test_year_filtered_entry_count_uses_count_finished(): void
    {
        $user = $this->createStub(User::class);

        $this->repository->method('countFinished')->willReturn(5);
        $this->repository->method('countStarted')->willReturn(8);
        $this->repository->method('countByUser')->willReturn(12);
        $this->repository->method('getWordCountStats')->willReturn(['totalWords' => 0, 'averageWords' => null, 'entryCount' => 0]);
        $this->repository->method('getAverageRating')->willReturn(null);
        $this->repository->method('countPinned')->willReturn(0);
        $this->repository->method('countByStatus')->willReturn([]);
        $this->repository->method('countByWorkType')->willReturn([]);
        $this->repository->method('findAvailableYears')->willReturn([]);

        $summary = $this->service->getDashboardSummary($user, 2025);

        // Year-filtered: entryCount must be countFinished (5), not countByUser (12)
        $this->assertSame(5, $summary['entryCount']);
    }

    public function test_year_parameter_forwarded_to_repository_methods(): void
    {
        $user = $this->createStub(User::class);
        $year = 2024;

        // Inline mock — this test verifies that the year is forwarded to every method.
        $repository = $this->createMock(ReadingEntryRepository::class);
        $service = new StatisticsService($repository);

        $repository->expects($this->once())->method('countFinished')->with($user, $year)->willReturn(3);
        $repository->expects($this->once())->method('countStarted')->with($user, $year)->willReturn(5);
        $repository->expects($this->once())->method('getWordCountStats')->with($user, $year)->willReturn(['totalWords' => 0, 'averageWords' => null, 'entryCount' => 0]);
        $repository->expects($this->once())->method('getAverageRating')->with($user, $year)->willReturn(null);
        $repository->expects($this->once())->method('countPinned')->with($user, $year)->willReturn(0);
        $repository->expects($this->once())->method('countByStatus')->with($user, $year)->willReturn([]);
        $repository->expects($this->once())->method('countByWorkType')->with($user, $year)->willReturn([]);
        $repository->method('findAvailableYears')->willReturn([]);

        $service->getDashboardSummary($user, $year);
    }

    public function test_get_trend_data_routes_to_count_by_month_when_year_given(): void
    {
        $user = $this->createStub(User::class);
        $monthlyData = array_fill_keys(range(1, 12), 0);

        $repository = $this->createMock(ReadingEntryRepository::class);
        $service = new StatisticsService($repository);

        $repository->expects($this->once())->method('countByMonth')->with($user, 2025)->willReturn($monthlyData);
        $repository->expects($this->never())->method('countByYear');

        $result = $service->getTrendData($user, 2025);

        $this->assertSame($monthlyData, $result);
    }

    public function test_get_trend_data_routes_to_count_by_year_when_no_year(): void
    {
        $user = $this->createStub(User::class);
        $yearlyData = [2023 => 10, 2024 => 47, 2025 => 83];

        $repository = $this->createMock(ReadingEntryRepository::class);
        $service = new StatisticsService($repository);

        $repository->expects($this->once())->method('countByYear')->with($user)->willReturn($yearlyData);
        $repository->expects($this->never())->method('countByMonth');

        $result = $service->getTrendData($user, null);

        $this->assertSame($yearlyData, $result);
    }
}
