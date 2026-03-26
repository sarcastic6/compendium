<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\AchievementDefinition;
use App\Repository\UserAchievementRepository;
use App\Service\AchievementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/achievements')]
#[IsGranted('ROLE_USER')]
class AchievementController extends AbstractController
{
    public function __construct(
        private readonly AchievementService $achievementService,
        private readonly UserAchievementRepository $userAchievementRepository,
    ) {
    }

    #[Route('', name: 'app_achievements')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Evaluate achievements on page load to catch any that haven't been checked yet
        $this->achievementService->evaluateAchievements($user);

        $progress = $this->achievementService->getProgress($user);

        // Mark all as notified (clears "New!" badges after this page view)
        $this->userAchievementRepository->markAllNotifiedForUser($user);

        return $this->render('achievement/index.html.twig', [
            'progress' => $progress,
            'grouped'  => AchievementDefinition::groupedByCategory(),
        ]);
    }
}
