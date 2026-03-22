<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\SeriesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/series', name: 'api_series_')]
#[IsGranted('ROLE_USER')]
class SeriesSearchController extends AbstractController
{
    public function __construct(
        private readonly SeriesRepository $seriesRepository,
    ) {
    }

    /**
     * Search series by name.
     *
     * GET /api/series/search?q={term}
     *
     * Returns up to 15 matches ordered by name.
     * Minimum query length of 2 characters is enforced server-side.
     */
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->query->getString('q', ''));

        if (mb_strlen($q) < 2) {
            return $this->json([]);
        }

        $results = $this->seriesRepository->searchByName($q);

        return $this->json(array_map(
            static fn ($s) => ['id' => $s['id'], 'name' => $s['name']],
            $results,
        ));
    }
}
