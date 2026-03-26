<?php

declare(strict_types=1);

namespace App\Tests\Functional\DataIntegrity;

use App\Entity\ReadingEntry;
use App\Entity\UserAchievement;
use App\Entity\Work;
use App\Enum\AchievementDefinition;
use App\Tests\Functional\AbstractFunctionalTest;

/**
 * Integration tests for achievement evaluation.
 *
 * Tests:
 * - Creating an entry triggers evaluation and unlocks first_entry achievement
 * - UNIQUE constraint prevents duplicate achievement rows (idempotent evaluation)
 * - Achievement unlock is scoped to the submitting user only
 */
class AchievementIntegrationTest extends AbstractFunctionalTest
{
    public function test_creating_first_completed_entry_unlocks_first_entry_achievement(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        // countsAsRead = true triggers finished-count achievements
        $status = $this->createStatus('Completed', true, true);

        $work = new Work(\App\Enum\WorkType::Book, 'My First Book');
        $this->em->persist($work);
        $this->em->flush();
        $workId = $work->getId();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        // Submit the reading entry form
        $this->client->request('GET', '/reading-entries/new/' . $workId);
        $this->submitFirstForm($this->client, [
            'reading_entry_form[status]' => $status->getId(),
        ]);

        $this->assertResponseRedirects('/reading-entries');

        // The first_entry achievement must now exist for Alice
        $this->em->clear();
        $achievement = $this->em->getRepository(UserAchievement::class)->findOneBy([
            'user'           => $alice->getId(),
            'achievementKey' => AchievementDefinition::FirstEntry->value,
        ]);

        $this->assertNotNull($achievement, 'first_entry achievement should be unlocked after first completed entry');
    }

    public function test_achievement_evaluation_is_idempotent(): void
    {
        // If evaluateAchievements() is called twice, it must not create duplicate rows.
        // The UNIQUE(user_id, achievement_key) constraint enforces this at the DB level,
        // but the service should also short-circuit via the unlocked-keys check.
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $status = $this->createStatus('Completed', true, true);

        $work = new Work(\App\Enum\WorkType::Book, 'Idempotent Book');
        $this->em->persist($work);
        $this->em->flush();
        $workId = $work->getId();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        // First entry — unlocks first_entry
        $this->client->request('GET', '/reading-entries/new/' . $workId);
        $this->submitFirstForm($this->client, [
            'reading_entry_form[status]' => $status->getId(),
        ]);

        // Second entry on same work — evaluation runs again; first_entry must not be duplicated
        $work2 = new Work(\App\Enum\WorkType::Book, 'Second Book');
        $this->em->persist($work2);
        $this->em->flush();
        $work2Id = $work2->getId();

        $this->client->request('GET', '/reading-entries/new/' . $work2Id);
        $this->submitFirstForm($this->client, [
            'reading_entry_form[status]' => $status->getId(),
        ]);

        $this->em->clear();
        $achievements = $this->em->getRepository(UserAchievement::class)->findBy([
            'user'           => $alice->getId(),
            'achievementKey' => AchievementDefinition::FirstEntry->value,
        ]);

        $this->assertCount(1, $achievements, 'first_entry must not be duplicated on repeated evaluation');
    }

    public function test_non_counted_status_does_not_unlock_entry_achievement(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        // countsAsRead = false — entry doesn't count toward finished_count achievements
        $status = $this->createStatus('Reading', true, false);

        $work = new Work(\App\Enum\WorkType::Book, 'In Progress Book');
        $this->em->persist($work);
        $this->em->flush();
        $workId = $work->getId();

        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();

        $this->client->request('GET', '/reading-entries/new/' . $workId);
        $this->submitFirstForm($this->client, [
            'reading_entry_form[status]' => $status->getId(),
        ]);

        $this->em->clear();
        $achievement = $this->em->getRepository(UserAchievement::class)->findOneBy([
            'user'           => $alice->getId(),
            'achievementKey' => AchievementDefinition::FirstEntry->value,
        ]);

        $this->assertNull($achievement, 'first_entry must not unlock when status does not count as read');
    }

    public function test_achievements_are_scoped_to_submitting_user(): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', 'CorrectHorse99!');
        $bob   = $this->createUser('bob@example.com', 'Bob', 'CorrectHorse99!');
        $status = $this->createStatus('Completed', true, true);

        $work = new Work(\App\Enum\WorkType::Book, 'Shared Work');
        $this->em->persist($work);
        $this->em->flush();
        $workId = $work->getId();

        // Alice creates an entry
        $this->logIn($this->client, 'alice@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();
        $this->client->request('GET', '/reading-entries/new/' . $workId);
        $this->submitFirstForm($this->client, [
            'reading_entry_form[status]' => $status->getId(),
        ]);

        $this->em->clear();

        // Only Alice should have the achievement — not Bob
        $aliceAchievement = $this->em->getRepository(UserAchievement::class)->findOneBy([
            'user'           => $alice->getId(),
            'achievementKey' => AchievementDefinition::FirstEntry->value,
        ]);
        $bobAchievement = $this->em->getRepository(UserAchievement::class)->findOneBy([
            'user'           => $bob->getId(),
            'achievementKey' => AchievementDefinition::FirstEntry->value,
        ]);

        $this->assertNotNull($aliceAchievement, 'Alice should have the first_entry achievement');
        $this->assertNull($bobAchievement, 'Bob must not receive Alice\'s achievement');
    }
}
