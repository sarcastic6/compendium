<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\MetadataTypeRepository;
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

        return $this->render('stats/dashboard.html.twig', [
            'summary' => $summary,
            'trendData' => $trendData,
            'ratingDistributions' => $ratingDistributions,
            'rankingTypes' => $rankingTypes,
            'year' => $year,
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
        $rankings = $this->statisticsService->getTopMetadata($user, $type, 50, $year);

        return $this->render('stats/rankings.html.twig', [
            'type' => $type,
            'rankings' => $rankings,
            'year' => $year,
            'availableYears' => $availableYears,
        ]);
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
