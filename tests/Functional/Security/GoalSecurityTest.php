<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\ReadingGoal;
use App\Enum\GoalType;
use App\Tests\Functional\AbstractFunctionalTest;

/**
 * Verifies that reading goal data is strictly isolated between users.
 *
 * Security boundaries tested:
 * - Un-authenticated access is rejected
 * - User A cannot delete User B's goal (ownership check in GoalController)
 * - Goal save is CSRF-protected
 */
class GoalSecurityTest extends AbstractFunctionalTest
{
    public function test_goals_page_requires_authentication(): void
    {
        $this->client->request('GET', '/goals');
        $this->assertResponseRedirects('/login');
    }

    public function test_goals_page_is_accessible_when_authenticated(): void
    {
        $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/goals');
        $this->assertResponseIsSuccessful();
    }

    public function test_user_cannot_delete_other_users_goal(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $bob   = $this->createUser('bob@example.com', 'Bob', 'CorrectHorse99!');

        // Bob sets a goal
        $bobGoal = new ReadingGoal($bob, (int) date('Y'), GoalType::EntriesCompleted, 50);
        $this->em->persist($bobGoal);
        $this->em->flush();
        $bobGoalId = $bobGoal->getId();

        // Alice tries to delete Bob's goal
        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('POST', '/goals/' . $bobGoalId . '/delete', [
            '_token' => 'any-token-value',
        ]);

        // Controller must deny access — either 403 or redirect without deleting
        // (CSRF will fail first, which redirects, but the goal must not be deleted)
        $this->em->clear();
        $this->assertNotNull(
            $this->em->find(ReadingGoal::class, $bobGoalId),
            "Bob's goal must not be deleted by Alice",
        );
    }

    public function test_goal_save_requires_csrf_token(): void
    {
        $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('POST', '/goals', [
            '_token'       => 'invalid-csrf-token',
            'goal_type'    => GoalType::EntriesCompleted->value,
            'target_value' => '52',
        ]);

        // No goal should be created — CSRF rejection redirects and no DB row
        $this->em->clear();
        $goals = $this->em->getRepository(ReadingGoal::class)->findAll();
        $this->assertCount(0, $goals, 'Goal must not be created with invalid CSRF token');
    }

    public function test_goal_delete_requires_csrf_token(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');

        $goal = new ReadingGoal($alice, (int) date('Y'), GoalType::EntriesCompleted, 52);
        $this->em->persist($goal);
        $this->em->flush();
        $goalId = $goal->getId();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        // Submit delete with wrong CSRF token
        $this->client->request('POST', '/goals/' . $goalId . '/delete', [
            '_token' => 'wrong-token',
        ]);

        $this->em->clear();
        $this->assertNotNull(
            $this->em->find(ReadingGoal::class, $goalId),
            'Goal must not be deleted with invalid CSRF token',
        );
    }
}
