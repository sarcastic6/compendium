<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\MetadataTypeRepository;
use App\Repository\StatusRepository;
use App\Service\StatisticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/stats')]
#[IsGranted('ROLE_USER')]
class StatsController extends AbstractController
{
    public function __construct(
        private readonly StatisticsService $statisticsService,
        private readonly MetadataTypeRepository $metadataTypeRepository,
        private readonly StatusRepository $statusRepository,
    ) {
    }

    #[Route('', name: 'app_stats_dashboard')]
    public function dashboard(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $year = $this->parseYearParam($request);

        $summary = $this->statisticsService->getDashboardSummary($user, $year);
        $trendData = $this->statisticsService->getTrendData($user, $year);
        $ratingDistributions = $this->statisticsService->getRatingDistributions($user, $year);
        $rankingTypes = $this->statisticsService->getAvailableRankingTypes($user, $year);

        $chartUrls = $this->buildChartUrls($summary, $trendData, $ratingDistributions, $year);

        $topMetadata = [
            'rating'   => $this->statisticsService->getTopMetadataSpotlight($user, 'Rating', $year),
            'category' => $this->statisticsService->getTopMetadataSpotlight($user, 'Category', $year),
            'fandom'   => $this->statisticsService->getTopMetadataSpotlight($user, 'Fandom', $year),
            'pairing'  => $this->statisticsService->getTopMetadataSpotlight($user, 'Pairing', $year),
        ];

        return $this->render('stats/dashboard.html.twig', [
            'summary' => $summary,
            'trendData' => $trendData,
            'ratingDistributions' => $ratingDistributions,
            'rankingTypes' => $rankingTypes,
            'year' => $year,
            'chartUrls' => $chartUrls,
            'topMetadata' => $topMetadata,
        ]);
    }

    #[Route('/rankings/{type}', name: 'app_stats_rankings')]
    public function rankings(Request $request, string $type): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Validate $type against actual metadata_types rows — no hardcoded allow-list.
        // The valid set is whatever the admin created at runtime.
        $metadataType = $this->metadataTypeRepository->findOneBy(['name' => $type]);
        if ($metadataType === null) {
            throw $this->createNotFoundException(
                sprintf('No metadata type named "%s" exists.', $type),
            );
        }

        $year = $this->parseYearParam($request);
        $availableYears = $this->statisticsService->getDashboardSummary($user, null)['availableYears'];
        [$sortColumn, $sortDir] = $this->parseSortParams($request);

        $rankings = $this->statisticsService->getRankings($user, $type, $sortColumn, $sortDir, $year);

        return $this->render('stats/rankings.html.twig', [
            'type' => $type,
            'rankings' => $rankings,
            'year' => $year,
            'availableYears' => $availableYears,
            'sortColumn' => $sortColumn,
            'sortDir' => $sortDir,
        ]);
    }

    /**
     * Builds the per-label URL arrays used to make each chart segment clickable.
     *
     * Each array is ordered to match the chart's label/data arrays so that
     * clicking the Nth bar/slice navigates to urls[N].
     *
     * When $year is set, all list links are scoped to that year via dateFrom/dateTo.
     *
     * @param array<string, mixed>     $summary
     * @param array<int, int>          $trendData
     * @param array{review: array<int,int>, spice: array<int,int>} $ratingDistributions
     * @return array{trend: string[], status: array<string|null>, rating: string[], spice: string[]}
     */
    private function buildChartUrls(
        array $summary,
        array $trendData,
        array $ratingDistributions,
        ?int $year,
    ): array {
        $yearScope = $year !== null
            ? ['dateFrom' => "$year-01-01", 'dateTo' => "$year-12-31"]
            : [];

        // Trend chart: each bar is either a year (all-time) or a month (year view)
        $trendUrls = [];
        foreach (array_keys($trendData) as $key) {
            if ($year !== null) {
                $monthStr = str_pad((string) $key, 2, '0', \STR_PAD_LEFT);
                $monthStart = new \DateTimeImmutable("$year-$monthStr-01");
                $monthEnd = $monthStart->modify('last day of this month');
                $trendUrls[] = $this->generateUrl('app_reading_entry_list', [
                    'dateFrom' => $monthStart->format('Y-m-d'),
                    'dateTo' => $monthEnd->format('Y-m-d'),
                ]);
            } else {
                $trendUrls[] = $this->generateUrl('app_reading_entry_list', [
                    'dateFrom' => "$key-01-01",
                    'dateTo' => "$key-12-31",
                ]);
            }
        }

        // Status chart: map name → ID for the list's ?status= filter
        $statusIdByName = [];
        foreach ($this->statusRepository->findAll() as $status) {
            $statusIdByName[$status->getName()] = $status->getId();
        }
        $statusUrls = [];
        foreach (array_keys($summary['byStatus']) as $statusName) {
            $id = $statusIdByName[$statusName] ?? null;
            $statusUrls[] = $id !== null
                ? $this->generateUrl('app_reading_entry_list', array_merge(['status' => $id], $yearScope))
                : null;
        }

        // Review stars distribution
        $ratingUrls = [];
        foreach (array_keys($ratingDistributions['review']) as $stars) {
            $ratingUrls[] = $this->generateUrl('app_reading_entry_list', array_merge(['rating' => $stars], $yearScope));
        }

        // Spice stars distribution
        $spiceUrls = [];
        foreach (array_keys($ratingDistributions['spice']) as $spice) {
            $spiceUrls[] = $this->generateUrl('app_reading_entry_list', array_merge(['spice' => $spice], $yearScope));
        }

        return [
            'trend' => $trendUrls,
            'status' => $statusUrls,
            'rating' => $ratingUrls,
            'spice' => $spiceUrls,
        ];
    }

    /**
     * Extracts and validates the ?sort= and ?dir= query parameters for rankings.
     *
     * Returns [sortColumn, sortDir]. Defaults to ['count', 'desc'] for invalid
     * or missing values.
     *
     * @return array{string, string}
     */
    private function parseSortParams(Request $request): array
    {
        $validColumns = ['name', 'count', 'count_pct', 'words', 'words_pct', 'read_count', 'read_pct'];
        $column = $request->query->get('sort', 'count');
        if (!in_array($column, $validColumns, true)) {
            $column = 'count';
        }

        $dir = $request->query->get('dir', 'desc');
        if ($dir !== 'asc' && $dir !== 'desc') {
            $dir = 'desc';
        }

        return [$column, $dir];
    }

    /**
     * Extracts and validates the ?year= query parameter.
     * Returns null for the all-time view.
     */
    private function parseYearParam(Request $request): ?int
    {
        $raw = $request->query->get('year', '');
        if ($raw === '' || $raw === null) {
            return null;
        }

        $year = (int) $raw;

        // Sanity bounds: discard obviously invalid years
        if ($year < 1900 || $year > 2100) {
            return null;
        }

        return $year;
    }
}
