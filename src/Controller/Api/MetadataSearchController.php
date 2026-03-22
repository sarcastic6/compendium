<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\MetadataRepository;
use App\Repository\MetadataTypeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/metadata', name: 'api_metadata_')]
#[IsGranted('ROLE_USER')]
class MetadataSearchController extends AbstractController
{
    public function __construct(
        private readonly MetadataRepository $metadataRepository,
        private readonly MetadataTypeRepository $metadataTypeRepository,
    ) {
    }

    /**
     * Search metadata by name, scoped to a MetadataType.
     *
     * GET /api/metadata/search?q={term}&typeId={metadataTypeId}
     *
     * Returns up to 15 matches ordered by name.
     * Minimum query length of 2 characters is enforced server-side.
     */
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->query->getString('q', ''));
        $typeId = $request->query->getInt('typeId', 0);

        if (mb_strlen($q) < 2 || $typeId <= 0) {
            return $this->json([]);
        }

        $metadataType = $this->metadataTypeRepository->find($typeId);
        if ($metadataType === null) {
            return $this->json([]);
        }

        $results = $this->metadataRepository->searchByNameAndType($q, $metadataType);

        return $this->json(array_map(
            static fn ($m) => ['id' => $m['id'], 'name' => $m['name']],
            $results,
        ));
    }
}
