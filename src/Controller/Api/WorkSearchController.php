<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Work;
use App\Repository\WorkRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/work', name: 'api_work_')]
#[IsGranted('ROLE_USER')]
class WorkSearchController extends AbstractController
{
    public function __construct(
        private readonly WorkRepository $workRepository,
    ) {
    }

    /**
     * Title search for the work autocomplete on the select page.
     *
     * GET /api/work/search?q={term}
     *
     * Returns up to 15 active works whose title matches the query,
     * ordered by title. Each result includes:
     *   - id:       work ID
     *   - name:     work title (used to fill the search input on selection)
     *   - subtitle: "by Author1, Author2 · Type" (pre-formatted for dropdown display)
     *
     * Minimum query length of 2 characters is enforced server-side.
     */
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->query->getString('q', ''));

        if (mb_strlen($q) < 2) {
            return $this->json([]);
        }

        $works = $this->workRepository->searchByTitle($q);

        return $this->json(array_map(
            static fn (Work $work) => [
                'id'       => $work->getId(),
                'name'     => $work->getTitle(),
                'subtitle' => self::buildSubtitle($work),
            ],
            $works,
        ));
    }

    /**
     * Builds the subtitle string shown beneath the title in the autocomplete dropdown.
     *
     * Format: "by Author1, Author2 · Fanfiction"
     * When no authors are attached, the "by …" prefix is omitted.
     */
    private static function buildSubtitle(Work $work): string
    {
        $authors = $work->getAuthors()
            ->map(static fn ($a) => $a->getName())
            ->toArray();

        sort($authors);

        $parts = [];

        if ($authors !== []) {
            $parts[] = 'by ' . implode(', ', $authors);
        }

        $parts[] = $work->getType()->value;

        return implode(' · ', $parts);
    }
}
