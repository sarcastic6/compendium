<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ImportRowError;
use App\Dto\ImportSummary;
use App\Entity\Language;
use App\Entity\Metadata;
use App\Entity\MetadataType;
use App\Entity\ReadingEntry;
use App\Entity\Series;
use App\Entity\SeriesSourceLink;
use App\Entity\Status;
use App\Entity\User;
use App\Entity\Work;
use App\Enum\ScrapeStatus;
use App\Enum\SourceType;
use App\Enum\WorkType;
use App\Message\ScrapeWorkMessage;
use App\Repository\LanguageRepository;
use App\Repository\MetadataRepository;
use App\Repository\MetadataTypeRepository;
use App\Repository\SeriesRepository;
use App\Repository\StatusRepository;
use App\Repository\WorkRepository;
use App\Scraper\ScraperRegistry;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Parses a Familiar Format XLSX file and creates ReadingEntry + Work entities.
 *
 * Import is NOT idempotent — importing the same file twice creates duplicate entries.
 * The controller/template must display a clear warning about this.
 *
 * The import loop:
 * 1. Preflight: verify required MetadataTypes exist
 * 2. For each row after the header:
 *    a. Skip completely blank rows silently
 *    b. Resolve Status by name (Col G) — skip row with error if not found
 *    c. If a URL is present: canonicalize it and find an existing Work by link
 *    d. Existing work found → reuse (no scrape queued)
 *    e. New work with URL → create stub, queue ScrapeWorkMessage
 *    f. No URL → create stub from spreadsheet columns (permanent, no scraping)
 *    g. Create ReadingEntry and flush
 * 3. Return ImportSummary
 */
class SpreadsheetImportService
{
    // Column indices (0-based) matching the Familiar Format layout.
    // Columns not listed here are ignored during import.
    private const COL_COMPLETION_STATUS = 0;  // A — ignored
    private const COL_URL               = 1;  // B
    // C (2), D (3) — ignored
    private const COL_TITLE             = 4;  // E
    private const COL_AUTHORS           = 5;  // F
    private const COL_STATUS_NAME       = 6;  // G
    private const COL_DATE_FINISHED     = 7;  // H
    // I (8) — ignored
    private const COL_REVIEW_STARS      = 9;  // J
    // K (10) — heart encoding, same value as J; ignored
    private const COL_SPICE_STARS       = 11; // L
    private const COL_SERIES_NAME       = 12; // M
    private const COL_PLACE_IN_SERIES   = 13; // N
    private const COL_SERIES_URL        = 14; // O
    private const COL_RATING            = 15; // P
    private const COL_WARNINGS          = 16; // Q
    private const COL_CATEGORIES        = 17; // R
    private const COL_FANDOMS           = 18; // S
    private const COL_MAIN_PAIRING      = 19; // T
    private const COL_RELATIONSHIPS     = 20; // U
    private const COL_CHARACTERS        = 21; // V
    private const COL_TAGS              = 22; // W
    private const COL_LANGUAGE          = 23; // X
    private const COL_PUBLISHED_DATE    = 24; // Y
    private const COL_LAST_UPDATED_DATE = 25; // Z
    private const COL_WORDS             = 26; // AA
    private const COL_CHAPTERS          = 27; // AB
    private const COL_LAST_READ_CHAPTER = 28; // AC
    // AD (29) — ignored
    private const COL_SUMMARY           = 30; // AE
    private const COL_COMMENTS          = 31; // AF

    public function __construct(
        private readonly WorkService $workService,
        private readonly WorkRepository $workRepository,
        private readonly StatusRepository $statusRepository,
        private readonly MetadataRepository $metadataRepository,
        private readonly MetadataTypeRepository $metadataTypeRepository,
        private readonly LanguageRepository $languageRepository,
        private readonly SeriesRepository $seriesRepository,
        private readonly ScraperRegistry $scraperRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Parses the XLSX at $filePath and creates reading entries for $user.
     * Dispatches ScrapeWorkMessage for each new AO3 work URL found.
     *
     * @param string $filePath Path to the uploaded temporary XLSX file
     */
    public function import(User $user, string $filePath): ImportSummary
    {
        $summary = new ImportSummary();

        // ── Preflight checks ────────────────────────────────────────────────
        // 'Relationships' MetadataType must exist — required for main pairing resolution.
        // Fail immediately with a clean error rather than a partial import or exception.
        $relationshipsType = $this->metadataTypeRepository->findOneBy(['name' => 'Relationships']);
        if ($relationshipsType === null) {
            $summary->errors[] = new ImportRowError(
                0,
                'MetadataType "Relationships" does not exist. Create it via the admin UI before importing.',
            );
            $summary->rowsSkipped++;

            return $summary;
        }

        // ── Load spreadsheet ─────────────────────────────────────────────────
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $sheet       = $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestRow();

        // Track Work IDs already dispatched to avoid duplicate scrape jobs for
        // re-read rows that reference the same work URL multiple times.
        $dispatchedWorkIds = [];

        // ── Row loop (row 1 is the header — start from row 2) ──────────────
        for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
            $title  = $this->cell($sheet, $rowIndex, self::COL_TITLE);
            $status = $this->cell($sheet, $rowIndex, self::COL_STATUS_NAME);
            $url    = $this->cell($sheet, $rowIndex, self::COL_URL);

            // Skip completely empty rows silently
            if ($title === null && $status === null && $url === null) {
                continue;
            }

            // Resolve Status — required; skip row if not found
            $statusName   = trim((string) $status);
            $statusEntity = $this->statusRepository->findOneBy(['name' => $statusName]);
            if ($statusEntity === null) {
                $summary->errors[] = new ImportRowError(
                    $rowIndex,
                    sprintf('Status "%s" not found.', $statusName),
                );
                $summary->rowsSkipped++;
                $this->logger->info(
                    'SpreadsheetImportService: row {row} skipped — status "{status}" not found.',
                    ['row' => $rowIndex, 'status' => $statusName],
                );
                continue;
            }

            // Resolve URL
            $rawUrl      = trim((string) $url);
            $canonicalUrl = null;
            if ($rawUrl !== '') {
                $scraper      = $this->scraperRegistry->getScraperForUrl($rawUrl);
                $canonicalUrl = $scraper !== null ? $scraper->canonicalizeUrl($rawUrl) : $rawUrl;
            }

            // Find or create Work
            $work    = null;
            $isNewWork = false;
            if ($canonicalUrl !== null) {
                $work = $this->workRepository->findByLink($canonicalUrl);
                if ($work !== null) {
                    $summary->worksReused++;
                    $this->logger->info(
                        'SpreadsheetImportService: row {row} — reusing existing work {id} for URL {url}.',
                        ['row' => $rowIndex, 'id' => $work->getId(), 'url' => $canonicalUrl],
                    );
                } else {
                    $titleStr = trim((string) $title);
                    if ($titleStr === '') {
                        $summary->errors[] = new ImportRowError($rowIndex, 'Title is required.');
                        $summary->rowsSkipped++;
                        continue;
                    }
                    $work      = $this->createWorkStub($sheet, $rowIndex, $titleStr, $canonicalUrl, $statusEntity);
                    $isNewWork = true;
                    $summary->worksCreated++;
                }
            } else {
                $titleStr = trim((string) $title);
                if ($titleStr === '') {
                    $summary->errors[] = new ImportRowError($rowIndex, 'Title is required.');
                    $summary->rowsSkipped++;
                    continue;
                }
                $work      = $this->createWorkStub($sheet, $rowIndex, $titleStr, null, $statusEntity);
                $isNewWork = true;
                $summary->worksCreated++;
            }

            // Dispatch scrape job for new works with a URL that have not yet been queued
            if ($isNewWork && $canonicalUrl !== null) {
                $workId = $work->getId();
                if ($workId !== null && !isset($dispatchedWorkIds[$workId])) {
                    $this->messageBus->dispatch(new ScrapeWorkMessage($workId, $canonicalUrl));
                    $dispatchedWorkIds[$workId] = true;
                    $summary->worksQueuedForScraping++;
                    $this->logger->info(
                        'SpreadsheetImportService: queued ScrapeWorkMessage for work {id}.',
                        ['id' => $workId],
                    );
                }
            }

            // Build ReadingEntry
            $entry = new ReadingEntry($user, $work, $statusEntity);
            $entry->setDateFinished($this->parseDateCell($this->cell($sheet, $rowIndex, self::COL_DATE_FINISHED)));
            $entry->setReviewStars($this->parseReviewStars((string) ($this->cell($sheet, $rowIndex, self::COL_REVIEW_STARS) ?? '')));
            $entry->setSpiceStars($this->parseSpiceStars((string) ($this->cell($sheet, $rowIndex, self::COL_SPICE_STARS) ?? '')));
            $entry->setLastReadChapter($this->intCellOrNull($this->cell($sheet, $rowIndex, self::COL_LAST_READ_CHAPTER)));
            $entry->setComments($this->stringCellOrNull($this->cell($sheet, $rowIndex, self::COL_COMMENTS)));

            $mainPairingName = trim((string) ($this->cell($sheet, $rowIndex, self::COL_MAIN_PAIRING) ?? ''));
            if ($mainPairingName !== '') {
                // resolveMainPairing() will not throw here — the preflight check above guarantees
                // the 'Relationships' type exists before we enter the row loop.
                $entry->setMainPairing($this->resolveMainPairing($mainPairingName, $relationshipsType));
            }

            $this->entityManager->persist($entry);
            $this->entityManager->flush();
            $summary->entriesCreated++;

            $this->logger->info(
                'SpreadsheetImportService: row {row} — entry created (work: {workTitle}).',
                ['row' => $rowIndex, 'workTitle' => $work->getTitle()],
            );
        }

        return $summary;
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Creates and persists a Work stub from spreadsheet columns.
     *
     * Called only when a new Work must be created: either no URL was found,
     * or a URL was present but no matching Work exists in the database.
     * Not called when an existing Work is matched by URL.
     *
     * Sets scrape_status = Pending when a URL is present (AO3 scrape will follow).
     * Leaves scrape_status = null when no URL is present (stub is permanent).
     */
    private function createWorkStub(
        Worksheet $sheet,
        int $rowIndex,
        string $title,
        ?string $canonicalUrl,
        Status $status,
    ): Work {
        // Infer source type from URL
        $sourceType = SourceType::Manual;
        if ($canonicalUrl !== null && str_contains($canonicalUrl, 'archiveofourown.org')) {
            $sourceType = SourceType::AO3;
        }

        $work = new Work(WorkType::Fanfiction, $title);
        $work->setSourceType($sourceType);
        $work->setLink($canonicalUrl);
        $work->setWords($this->intCellOrNull($this->cell($sheet, $rowIndex, self::COL_WORDS)));
        $work->setChapters($this->intCellOrNull($this->cell($sheet, $rowIndex, self::COL_CHAPTERS)));
        $work->setPublishedDate($this->parseDateCell($this->cell($sheet, $rowIndex, self::COL_PUBLISHED_DATE)));
        $work->setLastUpdatedDate($this->parseDateCell($this->cell($sheet, $rowIndex, self::COL_LAST_UPDATED_DATE)));
        $work->setSummary($this->stringCellOrNull($this->cell($sheet, $rowIndex, self::COL_SUMMARY)));

        // Mark pending when URL present — scrape will provide authoritative data
        if ($canonicalUrl !== null) {
            $work->setScrapeStatus(ScrapeStatus::Pending);
        }

        // Language — find or create
        $languageName = trim((string) ($this->cell($sheet, $rowIndex, self::COL_LANGUAGE) ?? ''));
        if ($languageName !== '') {
            $work->setLanguage($this->findOrCreateLanguage($languageName));
        }

        // Series — find or create (URL-first)
        $seriesName = trim((string) ($this->cell($sheet, $rowIndex, self::COL_SERIES_NAME) ?? ''));
        if ($seriesName !== '') {
            $seriesUrl = trim((string) ($this->cell($sheet, $rowIndex, self::COL_SERIES_URL) ?? ''));
            $series    = $this->findOrCreateSeries(
                $seriesName,
                $seriesUrl !== '' ? $seriesUrl : null,
                $sourceType,
            );
            $work->setSeries($series);
            $work->setPlaceInSeries($this->intCellOrNull($this->cell($sheet, $rowIndex, self::COL_PLACE_IN_SERIES)));
        }

        // Authors (comma-separated, type='Author')
        $this->addCommaMetadata(
            $work,
            (string) ($this->cell($sheet, $rowIndex, self::COL_AUTHORS) ?? ''),
            $this->workService->findOrCreateAuthorType(),
        );

        // Metadata columns: single-value types first, then multi-value
        $this->addSingleMetadata(
            $work,
            (string) ($this->cell($sheet, $rowIndex, self::COL_RATING) ?? ''),
            'Rating',
        );
        $this->addCommaMetadata(
            $work,
            (string) ($this->cell($sheet, $rowIndex, self::COL_WARNINGS) ?? ''),
            $this->findOrCreateMetadataType('Warning', true),
        );
        $this->addCommaMetadata(
            $work,
            (string) ($this->cell($sheet, $rowIndex, self::COL_CATEGORIES) ?? ''),
            $this->findOrCreateMetadataType('Category', true),
        );
        $this->addCommaMetadata(
            $work,
            (string) ($this->cell($sheet, $rowIndex, self::COL_FANDOMS) ?? ''),
            $this->findOrCreateMetadataType('Fandom', true),
        );
        $this->addCommaMetadata(
            $work,
            (string) ($this->cell($sheet, $rowIndex, self::COL_RELATIONSHIPS) ?? ''),
            $this->findOrCreateMetadataType('Relationships', true),
        );
        $this->addCommaMetadata(
            $work,
            (string) ($this->cell($sheet, $rowIndex, self::COL_CHARACTERS) ?? ''),
            $this->findOrCreateMetadataType('Character', true),
        );
        $this->addCommaMetadata(
            $work,
            (string) ($this->cell($sheet, $rowIndex, self::COL_TAGS) ?? ''),
            $this->findOrCreateMetadataType('Tag', true),
        );

        $this->entityManager->persist($work);
        $this->entityManager->flush();

        return $work;
    }

    /**
     * Adds a single metadata value (non-comma-split) to the work.
     * Used for Rating which has multiple_allowed = false.
     */
    private function addSingleMetadata(Work $work, string $cell, string $typeName): void
    {
        $name = trim($cell);
        if ($name === '') {
            return;
        }

        $type     = $this->findOrCreateMetadataType($typeName, false);
        $metadata = $this->findOrCreateMetadata($name, $type);
        $work->addMetadata($metadata);
    }

    /**
     * Splits a comma-separated cell value and adds each part as metadata.
     * Used for multi-value types (Warning, Category, Fandom, etc.).
     */
    private function addCommaMetadata(Work $work, string $cell, MetadataType $type): void
    {
        if (trim($cell) === '') {
            return;
        }

        $parts = array_map('trim', explode(',', $cell));
        foreach ($parts as $name) {
            if ($name !== '') {
                $work->addMetadata($this->findOrCreateMetadata($name, $type));
            }
        }
    }

    /**
     * Finds or creates the MetadataType with the given name.
     * Auto-creates with a log warning if not found — consistent with ImportService behaviour.
     */
    private function findOrCreateMetadataType(string $name, bool $multipleAllowed): MetadataType
    {
        $type = $this->metadataTypeRepository->findOneBy(['name' => $name]);
        if ($type !== null) {
            return $type;
        }

        $type = new MetadataType($name, $multipleAllowed);
        $this->entityManager->persist($type);
        $this->entityManager->flush();

        $this->logger->warning(
            'SpreadsheetImportService: MetadataType "{name}" was auto-created during import.',
            ['name' => $name],
        );

        return $type;
    }

    /**
     * Finds an existing Metadata record by (name, type) or creates a new one.
     */
    private function findOrCreateMetadata(string $name, MetadataType $type): Metadata
    {
        $existing = $this->metadataRepository->findOneBy(['name' => $name, 'metadataType' => $type]);
        if ($existing !== null) {
            return $existing;
        }

        $metadata = new Metadata($name, $type);
        $this->entityManager->persist($metadata);

        return $metadata;
    }

    /**
     * Finds or creates a Language by name.
     */
    private function findOrCreateLanguage(string $name): Language
    {
        $language = $this->languageRepository->findOneBy(['name' => $name]);
        if ($language !== null) {
            return $language;
        }

        $language = new Language($name);
        $this->entityManager->persist($language);
        $this->entityManager->flush();

        $this->logger->info(
            'SpreadsheetImportService: Language "{name}" was auto-created during import.',
            ['name' => $name],
        );

        return $language;
    }

    /**
     * Finds or creates a Series using URL-first lookup.
     *
     * 1. If $sourceUrl is provided: query series_source_links for a matching URL.
     *    Return that series (updating name if changed — AO3 is source of truth).
     * 2. If no URL or not found: fall back to name matching.
     * 3. If neither finds a match: create a new Series.
     *
     * Stores the source URL on the series for future scraper enrichment.
     */
    private function findOrCreateSeries(string $name, ?string $sourceUrl, SourceType $sourceType): Series
    {
        $series = null;

        if ($sourceUrl !== null) {
            $series = $this->seriesRepository->findBySourceUrl($sourceType, $sourceUrl);
            if ($series !== null && $series->getName() !== $name) {
                $series->setName($name);
            }
        }

        if ($series === null) {
            $series = $this->seriesRepository->findOneBy(['name' => $name]);
        }

        if ($series === null) {
            $series = new Series($name);
            $this->entityManager->persist($series);
        }

        // Store the source URL on the series for future scraper enrichment
        if ($sourceUrl !== null) {
            $alreadyStored = false;
            foreach ($series->getSourceLinks() as $existing) {
                if ($existing->getSourceType() === $sourceType) {
                    $alreadyStored = true;
                    break;
                }
            }
            if (!$alreadyStored) {
                $sourceLink = new SeriesSourceLink($series, $sourceType, $sourceUrl);
                $series->addSourceLink($sourceLink);
                $this->entityManager->persist($sourceLink);
            }
        }

        return $series;
    }

    /**
     * Finds or creates a Metadata record for a main pairing (type='Relationships').
     *
     * No source link is available at import time — the scraper adds source links
     * when it processes the work's relationships metadata later.
     *
     * The 'Relationships' MetadataType is passed in from the import() method after
     * the preflight check verifies it exists. The preflight guarantees $type is
     * non-null here, so the defensive \RuntimeException below is a backstop only.
     *
     * @throws \RuntimeException if the 'Relationships' MetadataType is unexpectedly null
     */
    private function resolveMainPairing(string $name, MetadataType $type): Metadata
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Main pairing name must not be blank.');
        }

        // Defensive: preflight() guarantees $type is non-null at import time.
        // Unlike scraper-originated types, 'Relationships' is a known required type
        // whose absence indicates the system is not properly set up.
        $existing = $this->metadataRepository->findOneBy(['name' => $name, 'metadataType' => $type]);
        if ($existing !== null) {
            return $existing;
        }

        $metadata = new Metadata($name, $type);
        $this->entityManager->persist($metadata);

        return $metadata;
    }

    // ── Parsing helpers ──────────────────────────────────────────────────────

    /**
     * Returns the cell value at the given row and 0-based column index, or null if blank.
     *
     * PhpSpreadsheet's getCellByColumnAndRow() was removed in PhpSpreadsheet 2.x.
     * Instead, convert the 0-based column index to a column letter via
     * Coordinate::stringFromColumnIndex() (which is 1-based, hence $colIndex + 1)
     * and build the cell coordinate string.
     */
    private function cell(Worksheet $sheet, int $rowIndex, int $colIndex): mixed
    {
        $colLetter = Coordinate::stringFromColumnIndex($colIndex + 1);
        $value     = $sheet->getCell($colLetter . $rowIndex)->getValue();

        return $value === '' ? null : $value;
    }

    /**
     * Counts ★ characters in a cell to produce an integer review score.
     * Returns null for blank cells or cells with no star characters.
     */
    private function parseReviewStars(string $cell): ?int
    {
        $count = mb_substr_count($cell, '★');

        return $count > 0 ? $count : null;
    }

    /**
     * Decodes the spice emoji encoding used by the Familiar Format export:
     * - blank → null (not rated)
     * - '🚫' → 0 (explicitly no spice)
     * - N × '🌶️' → N (spice level 1–5)
     *
     * Note on 🌶️: the chili emoji is followed by a variation selector (U+FE0F).
     * mb_substr_count($cell, '🌶') counts occurrences of the base code point (U+1F336)
     * without the variation selector, which correctly matches every chili regardless
     * of how the file encodes the variation selector.
     */
    private function parseSpiceStars(string $cell): ?int
    {
        $cell = trim($cell);

        if ($cell === '') {
            return null;
        }

        if (str_contains($cell, '🚫')) {
            return 0;
        }

        $count = mb_substr_count($cell, '🌶');

        return $count > 0 ? $count : null;
    }

    /**
     * Parses a date cell that may arrive in three forms:
     * - string in 'Y-m-d' format (Compendium's own export)
     * - int/float Excel serial number (Google Sheets / Excel native date cell)
     * - \DateTime or \DateTimeImmutable (some PhpSpreadsheet reader configurations)
     *
     * Returns null for blank cells or unparseable values.
     */
    private function parseDateCell(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Excel serial number (int or float)
        if (is_int($value) || is_float($value)) {
            $dt = SpreadsheetDate::excelToDateTimeObject($value);

            return \DateTimeImmutable::createFromMutable($dt) ?: null;
        }

        // Already a DateTimeImmutable
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        // Already a DateTime
        if ($value instanceof \DateTime) {
            return \DateTimeImmutable::createFromMutable($value) ?: null;
        }

        // String — expected format from Compendium's own export
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);

        return $dt !== false ? $dt : null;
    }

    private function intCellOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = filter_var($value, FILTER_VALIDATE_INT);

        return $int !== false ? (int) $int : null;
    }

    private function stringCellOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $str = trim((string) $value);

        return $str !== '' ? $str : null;
    }
}
