<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\GoalType;
use App\Repository\ReadingGoalRepository;
use App\Service\ReadingGoalService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/goals')]
#[IsGranted('ROLE_USER')]
class GoalController extends AbstractController
{
    public function __construct(
        private readonly ReadingGoalService $readingGoalService,
        private readonly ReadingGoalRepository $readingGoalRepository,
    ) {
    }

    #[Route('', name: 'app_goals', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user        = $this->getUser();
        $currentYear = (int) date('Y');

        $currentGoals = $this->readingGoalService->getGoalsWithProgress($user, $currentYear);

        // Past years that have goals (read-only display)
        $pastYears    = [];
        $yearsWithGoals = $this->readingGoalRepository->findYearsWithGoals($user);
        foreach ($yearsWithGoals as $y) {
            if ($y === $currentYear) {
                continue;
            }
            $pastYears[$y] = $this->readingGoalService->getGoalsWithProgress($user, $y);
        }

        return $this->render('goal/manage.html.twig', [
            'currentYear'  => $currentYear,
            'currentGoals' => $currentGoals,
            'pastYears'    => $pastYears,
            'goalTypes'    => GoalType::cases(),
        ]);
    }

    #[Route('', name: 'app_goals_save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('goal_save', $request->request->get('_token'))) {
            $this->addFlash('error', 'security.csrf_invalid');
            return $this->redirectToRoute('app_goals');
        }

        $year        = (int) date('Y');
        $goalTypeStr = (string) $request->request->get('goal_type', '');
        $rawValue    = $request->request->get('target_value', '');

        // Validate goal type
        $goalType = GoalType::tryFrom($goalTypeStr);
        if ($goalType === null) {
            $this->addFlash('error', 'goal.error.invalid_type');
            return $this->redirectToRoute('app_goals');
        }

        // Validate target value
        $targetValue = filter_var($rawValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($targetValue === false) {
            $this->addFlash('error', 'goal.error.invalid_value');
            return $this->redirectToRoute('app_goals');
        }

        $this->readingGoalService->setGoal($user, $year, $goalType, $targetValue);
        $this->addFlash('success', 'goal.saved');

        return $this->redirectToRoute('app_goals');
    }

    #[Route('/{id}/delete', name: 'app_goals_delete', methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('goal_delete_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'security.csrf_invalid');
            return $this->redirectToRoute('app_goals');
        }

        $goal = $this->readingGoalRepository->find($id);

        // Security: ensure goal belongs to this user
        if ($goal === null || $goal->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $this->readingGoalService->deleteGoal($goal);
        $this->addFlash('success', 'goal.deleted');

        return $this->redirectToRoute('app_goals');
    }
}
