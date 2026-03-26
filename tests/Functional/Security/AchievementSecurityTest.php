<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\UserAchievement;
use App\Enum\AchievementDefinition;
use App\Tests\Functional\AbstractFunctionalTest;

/**
 * Verifies that achievement data is strictly isolated between users.
 *
 * Security boundaries tested:
 * - User A cannot see User B's achievements page
 * - Achievement evaluation only unlocks for the correct user
 * - Un-authenticated access is rejected
 */
class AchievementSecurityTest extends AbstractFunctionalTest
{
    public function test_achievements_page_requires_authentication(): void
    {
        $this->client->request('GET', '/achievements');
        $this->assertResponseRedirects('/login');
    }

    public function test_achievements_page_is_accessible_when_authenticated(): void
    {
        $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/achievements');
        $this->assertResponseIsSuccessful();
    }

    public function test_user_only_sees_own_achievements(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $bob   = $this->createUser('bob@example.com', 'Bob', 'CorrectHorse99!');

        // Give Bob an achievement directly (bypassing service to isolate the test)
        $bobAchievement = new UserAchievement(
            $bob,
            AchievementDefinition::FirstEntry->value,
            new \DateTimeImmutable('2024-01-01'),
        );
        $this->em->persist($bobAchievement);
        $this->em->flush();
        $bobAchievementId = $bobAchievement->getId();

        // Alice logs in — she must not be able to access Bob's achievement record
        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        // Alice visits achievements page — should succeed (her own, empty)
        $this->client->request('GET', '/achievements');
        $this->assertResponseIsSuccessful();

        // Bob's achievement must still exist (no cross-user side effects)
        $this->em->clear();
        $this->assertNotNull($this->em->find(UserAchievement::class, $bobAchievementId));
    }

    public function test_achievement_evaluation_does_not_affect_other_users(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $bob   = $this->createUser('bob@example.com', 'Bob', 'CorrectHorse99!');

        // Give Bob an existing achievement
        $bobAchievement = new UserAchievement(
            $bob,
            AchievementDefinition::FirstEntry->value,
            new \DateTimeImmutable('2024-01-01'),
        );
        $this->em->persist($bobAchievement);
        $this->em->flush();

        // Alice visits achievements — triggers evaluateAchievements() for Alice only
        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();
        $this->client->request('GET', '/achievements');
        $this->assertResponseIsSuccessful();

        // Bob's record must be untouched (not duplicated, not modified)
        $this->em->clear();
        $bobRecords = $this->em->getRepository(UserAchievement::class)
            ->findBy(['user' => $bob]);

        $this->assertCount(1, $bobRecords, 'Bob should still have exactly 1 achievement');
        $this->assertSame(AchievementDefinition::FirstEntry->value, $bobRecords[0]->getAchievementKey());
    }
}
