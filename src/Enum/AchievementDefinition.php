<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Defines all 20 achievement milestones.
 *
 * Each case value is the string stored in user_achievements.achievement_key.
 * No query logic lives here — this is pure metadata. The AchievementService
 * reads conditionType + threshold and dispatches to the correct repository method.
 *
 * Icons use Bootstrap Icons (bi-*) class names.
 */
enum AchievementDefinition: string
{
    // ── Volume ────────────────────────────────────────────────────────────────
    case FirstEntry    = 'first_entry';
    case Entries10     = 'entries_10';
    case Entries50     = 'entries_50';
    case Entries100    = 'entries_100';
    case Entries500    = 'entries_500';
    case Entries1000   = 'entries_1000';
    case Words100k     = 'words_100k';
    case Words1m       = 'words_1m';
    case Words10m      = 'words_10m';

    // ── Exploration ───────────────────────────────────────────────────────────
    case Fandoms5      = 'fandoms_5';
    case Fandoms20     = 'fandoms_20';
    case Authors10     = 'authors_10';
    case Authors50     = 'authors_50';
    case Languages3    = 'languages_3';

    // ── Dedication ────────────────────────────────────────────────────────────
    case FirstReread   = 'first_reread';
    case LongWork100k  = 'long_work_100k';
    case LongWork500k  = 'long_work_500k';

    // ── Engagement ────────────────────────────────────────────────────────────
    case FirstReview   = 'first_review';
    case Reviews50     = 'reviews_50';
    case FirstStar     = 'first_star';

    // ── Metadata ─────────────────────────────────────────────────────────────

    public function getCategory(): string
    {
        return match ($this) {
            self::FirstEntry, self::Entries10, self::Entries50,
            self::Entries100, self::Entries500, self::Entries1000,
            self::Words100k, self::Words1m, self::Words10m          => 'volume',
            self::Fandoms5, self::Fandoms20, self::Authors10,
            self::Authors50, self::Languages3                        => 'exploration',
            self::FirstReread, self::LongWork100k, self::LongWork500k => 'dedication',
            self::FirstReview, self::Reviews50, self::FirstStar      => 'engagement',
        };
    }

    /**
     * Translation key for the achievement name, e.g. 'achievement.first_entry.name'.
     */
    public function getNameKey(): string
    {
        return 'achievement.' . $this->value . '.name';
    }

    /**
     * Translation key for the achievement description.
     */
    public function getDescriptionKey(): string
    {
        return 'achievement.' . $this->value . '.description';
    }

    /**
     * Translation key for the flash message shown on unlock.
     */
    public function getUnlockedMessageKey(): string
    {
        return 'achievement.' . $this->value . '.unlocked';
    }

    /**
     * Bootstrap Icon class name. Used in templates as <i class="bi {{ def.icon }}"></i>.
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::FirstEntry    => 'bi-book',
            self::Entries10     => 'bi-journal-bookmark',
            self::Entries50     => 'bi-journals',
            self::Entries100    => 'bi-trophy',
            self::Entries500    => 'bi-gem',
            self::Entries1000   => 'bi-stars',
            self::Words100k     => 'bi-feather',
            self::Words1m       => 'bi-file-richtext',
            self::Words10m      => 'bi-bank',
            self::Fandoms5      => 'bi-compass',
            self::Fandoms20     => 'bi-globe2',
            self::Authors10     => 'bi-people',
            self::Authors50     => 'bi-people-fill',
            self::Languages3    => 'bi-translate',
            self::FirstReread   => 'bi-arrow-repeat',
            self::LongWork100k  => 'bi-graph-up',
            self::LongWork500k  => 'bi-graph-up-arrow',
            self::FirstReview   => 'bi-pencil',
            self::Reviews50     => 'bi-pen',
            self::FirstStar     => 'bi-heart-fill',
        };
    }

    /**
     * The numeric threshold for this achievement.
     */
    public function getThreshold(): int
    {
        return match ($this) {
            self::FirstEntry    => 1,
            self::Entries10     => 10,
            self::Entries50     => 50,
            self::Entries100    => 100,
            self::Entries500    => 500,
            self::Entries1000   => 1000,
            self::Words100k     => 100_000,
            self::Words1m       => 1_000_000,
            self::Words10m      => 10_000_000,
            self::Fandoms5      => 5,
            self::Fandoms20     => 20,
            self::Authors10     => 10,
            self::Authors50     => 50,
            self::Languages3    => 3,
            self::FirstReread   => 1,   // needs >= 2 finished entries on same work; threshold=1 means "first occurrence"
            self::LongWork100k  => 100_000,
            self::LongWork500k  => 500_000,
            self::FirstReview   => 1,
            self::Reviews50     => 50,
            self::FirstStar     => 1,
        };
    }

    /**
     * The condition type string, used by AchievementService to dispatch to the
     * correct repository method.
     *
     * Possible values:
     *   - 'finished_count'      — COUNT of entries where status.countsAsRead = true
     *   - 'words_sum'           — running SUM of work.words (hasBeenStarted entries)
     *   - 'unique_fandoms'      — COUNT(DISTINCT fandom metadata) on finished entries
     *   - 'unique_authors'      — COUNT(DISTINCT author metadata) on finished entries
     *   - 'unique_languages'    — COUNT(DISTINCT work.language) on finished entries
     *   - 'reread'              — same work with >= 2 finished entries
     *   - 'long_work'           — finished a single work with >= threshold words
     *   - 'rated_count'         — COUNT of entries where reviewStars IS NOT NULL
     *   - 'pinned_count'        — COUNT of entries where pinned = true
     */
    public function getConditionType(): string
    {
        return match ($this) {
            self::FirstEntry, self::Entries10, self::Entries50,
            self::Entries100, self::Entries500, self::Entries1000 => 'finished_count',
            self::Words100k, self::Words1m, self::Words10m        => 'words_sum',
            self::Fandoms5, self::Fandoms20                       => 'unique_fandoms',
            self::Authors10, self::Authors50                      => 'unique_authors',
            self::Languages3                                      => 'unique_languages',
            self::FirstReread                                     => 'reread',
            self::LongWork100k, self::LongWork500k                => 'long_work',
            self::FirstReview                                     => 'rated_count',
            self::Reviews50                                       => 'rated_count',
            self::FirstStar                                       => 'pinned_count',
        };
    }

    /**
     * For metadata-type conditions, the metadata type name to filter on.
     * Returns null for non-metadata conditions.
     */
    public function getMetadataTypeName(): ?string
    {
        return match ($this) {
            self::Fandoms5, self::Fandoms20   => 'Fandom',
            self::Authors10, self::Authors50  => 'Author',
            default                           => null,
        };
    }

    /**
     * Returns all definitions grouped by category, in display order.
     *
     * @return array<string, list<self>>
     */
    public static function groupedByCategory(): array
    {
        $grouped = [];
        foreach (self::cases() as $def) {
            $grouped[$def->getCategory()][] = $def;
        }
        return $grouped;
    }
}
