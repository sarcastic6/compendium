# Reading Stats — Design Decisions

This file records the *why* behind non-obvious logic decisions in the application.
It is complementary to CLAUDE.md (what was built) and DESIGN_SUMMARY.md (how it is built).
Update this file whenever a significant logic decision is made or revised.

---

## Test Suite — Known Full-Run Ordering Issue

When running the full test suite (`php bin/phpunit`), approximately 8 tests in classes like
`ReauthenticationTest` and `StatsUserIsolationTest` show as **errors** (not failures). The same
tests **pass when run in isolation**.

**Root cause:** `AbstractFunctionalTest::setUp()` drops and recreates the schema between tests,
but Symfony's test client boots a shared kernel. When multiple test classes share the kernel
instance, state from a previous class (authenticated session, cached services, etc.) can leak
into the next class's setUp and cause errors before the test body even runs.

**This is a known pre-existing issue — do not attempt to "fix" the individual failing tests.**
The tests themselves are correct. The issue is in test ordering and kernel lifecycle management.
If you need to verify a specific test class, run it in isolation.

If a future session wants to fix this properly, the correct approach is to ensure kernel
rebooting between test classes, or use `KernelTestCase::ensureKernelShutdown()` in tearDown.

---

## Status Flags

### Why two flags instead of one?

The original `is_finished` boolean captured "the user is done with this entry" — true for both
Completed and DNF. This turned out to be insufficient because two distinct questions need answers:

1. **Has the user read any of this work?** (word consumption)
2. **Did the user finish reading this work?** (completion tracking)

A single flag cannot answer both. DNF means the user read some of the work but did not finish it.
Completed means both. TBR means neither. These are orthogonal concepts.

### `has_been_started`

True when the user has actually read some portion of the work. Used to determine which entries
contribute to word consumption statistics.

| Status    | Value | Reasoning |
|-----------|-------|-----------|
| TBR       | false | No reading has occurred yet |
| Reading   | true  | Actively reading |
| On Hold   | true  | Was reading, may resume — see note below |
| Completed | true  | User read the work (also counts_as_read = true) |
| DNF       | true  | User read some of the work before abandoning it |

> **Note on DNF and words:** DNF entries have `has_been_started = true`, meaning their word
> counts contribute to word consumption statistics. The reasoning: the user did read a
> portion of the work. There is no "words read so far" field, so we use the full word count
> as the closest available approximation. This is intentional and consistent — the same
> word count is used for Reading and On Hold entries too.

> **Note on On Hold:** On Hold is `has_been_started = true`. The reasoning: if the user did not
> intend to return to the work, they would mark it DNF instead. On Hold implies reading happened
> and may continue.

### `counts_as_read`

True only when this status represents a successfully completed read. Typically only 'Completed'.

Used for:
- Trend charts (reads completed per month/year)
- Dashboard finished count card
- Finish rate numerator ("of what you started, how many did you finish?")
- Read Count column in rankings

DNF is explicitly `false` — "did not finish" is not a completed read, regardless of how much
was read. On Hold is `false` — the read has not concluded.

### Both flags are admin-controlled

Neither flag is hardcoded to a status name. The admin sets them at runtime when creating statuses.
The migration seeds reasonable defaults based on the expected status names (TBR, Reading, On Hold,
Completed, DNF), but these can be adjusted via the admin UI. This allows future statuses (e.g.,
"Abandoned", "Rereading") to be configured correctly without code changes.

---

## Rankings Column Logic

### Reads # and Reads %

**Reads #** is the number of reading entries that reference a work containing this metadata item.
Re-reads count separately (see Re-read Counting section below).

**Reads %** uses the sum of all Reads # values for the current metadata type as its denominator —
not the global total reading entry count. This is intentional:

One reading entry can reference a work with multiple items of the same type (e.g., 3 characters,
5 tags). Using the global entry count as the denominator would produce percentages that each look
reasonable in isolation but are meaningless in aggregate (22 characters each showing 25% when
the user has only 4 entries). The type-scoped denominator answers the more honest question:
"of all character appearances across my reading, what share went to this character?"

The trade-off: percentages for multi-value types (Character, Tag, Pairing) will not sum to 100%
because one entry contributes to multiple items. This is expected and correct.
For single-value types (Rating, Language), they will sum to approximately 100%.

(Previously labelled "Count #" / "Count %" — renamed to "Reads" for clarity.)

### Total Words and Words %

**Total Words** is the sum of work word counts across all reading entries for this item.
Re-reads multiply the word count (reading a 100k word fic twice = 200k words for that item).
This is intentional — it reflects actual reading effort, not just unique exposure.

**Words %** uses the global total words read by the user (year-scoped) as its denominator —
not the sum of Total Words across all items of this type. This is intentional:

Using the per-type sum as the denominator inflates it for multi-value types. A 100k word fic
with 3 characters would contribute 300k words to the denominator, making each character's
percentage smaller and less meaningful. The global denominator answers the honest question:
"what percentage of the words I actually read were in works featuring this item?"

### Finished and Finished %

**Finished** counts only entries where `status.counts_as_read = true`. DNF is explicitly
excluded. The intent is "how many times did you read this item to completion?" not "how many
times did you engage with it?"

**Finished %** is `Finished / Reads #` for that item — the finish rate within this item. It
answers "of all your reading entries involving this item, what fraction did you complete?"
This uses the item's own Reads # as the denominator, not the global finished count.

(Previously labelled "Read Count" / "Read %" — renamed to "Finished" for clarity.)

### Status ranking page omits Read Count and Read %

The status ranking page does not include the Read Count or Read % columns that appear on
all metadata ranking pages. The reasoning: the status *is* the read classification.
"Read Count" on a status page would simply be 100% for Completed and 0% for everything
else — information already conveyed by the status name itself. Showing those columns would
be misleading rather than informative.

### Year scoping

All six columns are scoped to the year filter when active. The year filter applies via
`dateFinished` — entries without a `dateFinished` in the selected year are excluded from all
counts. When no year is selected, all entries are included regardless of date.

---

## "Relationships" vs "Main Pairing" — Two Different Concepts

These are distinct concepts and must not be confused:

- **`Relationships`** — a MetadataType on the Work entity. Stores the relationship tags from the
  work itself (e.g. AO3's "Relationships" category). Many-to-many via WORK_METADATA. Represents
  all pairings tagged by the author. DB type name is `'Relationships'`, enforced in code.

- **`Main Pairing`** — a field on the ReadingEntry entity (`main_pairing_id`). The user's chosen
  focus pairing for *this specific read* — which pairing they cared about out of all the work's
  relationships. One per reading entry, nullable. Stored as a FK to METADATA (type='Relationships').

The type was originally named `'Pairing'` but was renamed to `'Relationships'` (data migration
`Version20260320230224`) specifically to eliminate this confusion. **Do not revert to 'Pairing'.**

### Dashboard "Top Reads" Pairing Card

The spotlight card uses the `main_pairing` field on each reading entry — not the work's
Relationships metadata tags.

`main_pairing` reflects the user's personal reading focus for each read. This makes the
spotlight card reflect personal reading interest rather than the work's full tag list.

The alternative approach — ranking by relationship metadata tags — is still available in
`ReadingEntryRepository::getTopMetadata('Pairing')` and `StatisticsService::getTopMetadataSpotlight`.
This could be surfaced as a separate card in the future if both perspectives are wanted.

---

## Re-read Counting

Re-reads (multiple reading entries for the same work) count separately throughout the
application. A work read three times contributes 3 to all entry counts and multiplies word
counts by 3.

This was a deliberate decision: re-reads reflect genuine engagement. If a user returns to the
same fandom or author repeatedly, that should rank higher than a single-read equivalent.
The original spreadsheet did not support re-read tracking due to data model limitations;
the new system supports it and leans into it.

The one place this requires care is the dashboard "Unique Works" card, which explicitly
counts distinct works to give a complementary view.

---

## Spice Field — UI Terminology

`spice_stars = 0` means **"no spice"**, displayed as 🚫. `spice_stars = 1–5` is displayed as 🌶️×N.

**Do not use "ice cold" or 🧊.** That was a discarded earlier design from the PLAN-FIX_SPICE.md
planning doc. The decision was changed before implementation to match the stats dashboard convention
(🚫 for absence, 🌶️ for presence). Every session this gets revisited, so the correct answer is:
0 = no spice = 🚫.

---

## Spice Filter Semantics

### Why `spice=0` is exact and `spice=1–5` is minimum

The spice field represents heat level: 0 = no spice, 1–5 = increasing intensity. Two meaningfully
different use cases exist:

- **"Show me clean reads"**: The user wants exactly `spice_stars = 0`. Showing spice=1 results when
  filtering for 0 would be wrong — the user is specifically filtering *out* spice content.
- **"Show me at least this spicy"**: The user wants `spice_stars >= N` for N ≥ 1. A minimum filter
  is the natural reading — "at least 3 chillies" means 3, 4, and 5 should all appear.

A single `spice` parameter handles both: `spice=0` uses `= 0`, `spice=1–5` uses `>= N`.

### Why `spiceExact` exists as a separate parameter

The stats dashboard's spice distribution bar chart links each bar to a filtered list showing only
entries at that exact spice level (clicking "🌶️🌶️" should show entries with exactly spice=2,
not all entries with spice ≥ 2).

Changing `spice` to minimum semantics would break those chart links. Rather than add a flag or
introduce a separate code path inside the `spice` handler, a separate `spiceExact=N` parameter
was introduced. It is:
- Always exact, regardless of value (including 0)
- Only emitted by `StatsController::buildChartUrls()` for chart drill-downs
- Never shown in the user-facing filter form

This keeps the user-facing filter intuitive (minimum above 0) while preserving correct chart
drill-down behavior.

---

## Language Link Removed

The `Language` entity had a `link` field intended to store the AO3 language page URL. It was removed because:
- AO3 language URLs cannot be reliably determined programmatically during scraping
- Language is a low-importance field — there is no meaningful action a user would take with a link to it
- It added schema complexity with no practical payoff

If language URLs become deterministic in a future scraping context, the field can be reintroduced.

---

## Stats Dashboard — Tab Layout

The dashboard was split into two tabs (Overview and Breakdown) to reduce scrolling as the number
of charts grew. The summary cards remain above the tabs since they are compact and always useful at a glance.

**Tab groupings:**
- **Overview**: trend chart + top reads spotlight. These are "narrative" data — what has happened
   over time and what stands out.
- **Breakdown**: all distribution charts (donuts, word length, review/spice). These are 
  "structural" data — what does the library look like.

The structure was intentionally designed to accommodate a third tab for Achievements/gamification
in a future session without restructuring.

---

## Comment Display — Offcanvas Over Tooltip

Reading entry comments can be paragraph-length. Tooltips were rejected for long content because:
- Not scrollable
- Truncation loses context
- Unpredictable dismiss behaviour on touch devices

An offcanvas drawer (slides in from the right) was chosen over a modal because it is less
disruptive — the reading list remains visible behind it. The comment icon lives in the title
cell (not the actions column) because comments are information, not an action.

---

## Word Length Distribution Buckets

The dashboard word length chart uses five buckets matching AO3's community conventions:
- < 1K words
- 1K–10K words
- 10K–50K words
- 50K–100K words
- > 100K words

These boundaries were chosen because they are the most widely recognised tiers in fanfiction
communities. A sixth bucket could be added later if real-world data shows a meaningful cluster
elsewhere (e.g., 100K–250K for very long but not epic works).

Bucket computation is done in PHP (not SQL) for cross-database portability.

---

## Abandon Rate Definition

Abandon Rate = `(startedCount - readCount) / startedCount × 100`, where:
- `startedCount` = entries where `status.has_been_started = true`
- `readCount` = entries where `status.counts_as_read = true`

Null is returned when `startedCount = 0` (nothing has been started, so rate is undefined — 
not zero).

DNF entries count toward `startedCount` but not `readCount`, which is the intended
behaviour — they represent started-but-not-finished reads.

The column is shown on metadata and Main Pairing rankings but not on Status or Language rankings,
where it would be redundant or misleading.

---

## Word Count Semantics

Word counts come from the Work entity (`words` field), not from reading entries directly.
This means:

- A work's word count is fixed regardless of how far the user read
- Re-reads of the same work each contribute the full word count
- Works without a word count (NULL) contribute 0 — they are not excluded from entry counts,
  only from word total calculations
- The dashboard "Avg. Words" card notes how many entries had a known word count, since the
  denominator may be smaller than the total entry count

Word counts are included in statistics for all entries where `has_been_started = true`
(Reading, On Hold, Completed, DNF). TBR is the only status excluded — no reading has occurred.

DNF entries are included. The user read some portion of the work before abandoning it.
Since there is no "words read so far" field on a reading entry, the full work word count
is used as the closest available approximation — the same basis used for all other statuses.

---

## Cover Art — Typographic Thumbnail Algorithm

Reading list and detail pages show a typographic "cover" thumbnail generated deterministically
from the work ID. The algorithm:

```
hue = 120 + (workId * 17) % 50
```

This constrains hue to the 120°–170° green/teal range (matching the app palette). The multiplier
**17** is prime — this spreads consecutive work IDs across the full hue range rather than stepping
linearly (e.g. IDs 1, 2, 3 would all cluster near hue 120 with a multiplier of 1; a prime
multiplier ensures adjacent IDs look visually distinct).

Two gradient stops: `hsl(hue, 35%, 18%)` dark → `hsl(hue + 10, 30%, 40%)` light.

Font class: `cover-font-book` (Lora italic) for Book type works, `cover-font-fanfic` (Manrope) for
Fanfiction. Implemented in `CoverArtExtension` Twig extension (`cover_gradient()` and
`cover_font_class()` helpers).

---

## Reading Entry Detail — Unified with Work Metadata

The reading entry detail page (`/reading-entries/{id}`) shows both the full work metadata and the
reading-specific data in a single page. The work detail page (`/works/{id}`) now serves a narrower
role: pre-entry context only (work metadata + "Add Reading Entry" CTA), with no reading-specific
data.

**Rationale:** Splitting work info and reading info across two pages required an extra navigation
step to see the full picture of a read. Since `entry.work` is always available on the entry
object, no additional queries are needed.

**Layout:**
- Left column: cover thumbnail, type/source badges, series, status chip, dates, review/spice, 
  main pairing
- Right column: title, authors, words/chapters/published/language stat boxes,
  metadata chips (Warning → Rating → Category → Fandom → Relationships → Character → Tag),
  comments, summary

The "Refresh from source" button lives in the reading entry page header (not the work page)
because the entry page is the primary destination from the reading list.

---

## Series Rankings — Words vs Coverage

The series rankings page exposes two word-related columns because they answer different questions:

- **Words** — total words consumed across all reading entries for that series, re-reads included.
  Consistent with how "Total Words" works on every other rankings page.
- **Coverage** — sum of `work.words` for distinct works the user has ever started in that series,
  compared to the series' total word count. Measures unique exposure to the series, not consumption.

### Why not just one column?

Using re-read-inclusive totals as the numerator against the series total as the denominator
produces a misleading ratio: reading a 100k word series twice yields "200k / 100k", implying
200% completion. Conversely, dropping the denominator entirely loses the useful information of
knowing how long the series actually is.

The split resolves this: "Words" is consumption (consistent, no denominator), "Coverage" is
progress (distinct-works basis, with denominator and progress bar).

### Why Coverage is year-independent

The year filter scopes most columns to entries with `dateFinished` in the selected year. Coverage
is deliberately exempt: it represents "how much of this series have you read at all?" — a
cumulative, all-time answer. A work finished in 2023 still contributes to your coverage of that
series when viewing 2024 stats. A footnote on the page explains this to the user.

### Why the progress bar is hidden rather than capped at 100%

When `coverageWords > totalWords` (can happen if series word count is stale from a scrape done
before new works were added), capping the bar at 100% would show a "full" bar for a series the
user has only partially read. Hiding the bar instead forces the user to read the actual numbers,
which already convey the situation correctly. A bar that is wrong is worse than no bar.

---

## Reading List Sort — Active Entries Float to Top

When sorting by completion date descending, entries with `status.is_active = true`
always float above all other entries, regardless of whether they have a `date_finished`.

### Why not sort NULLs to the top?

An earlier iteration simply floated NULL `date_finished` values to the top. This worked
for Reading entries (which typically have no finish date) but also surfaced DNF and On Hold
entries — both of which also lack a finish date. DNF is a closed status; there is nothing
actionable about it. Floating it to the top alongside actively-in-progress reads was noise.

### Why a flag on Status rather than hardcoded status names?

Hardcoding "Reading" would be fragile — status names are admin-controlled at runtime.
The `is_active` flag follows the same pattern as `has_been_started` and `counts_as_read`:
the admin decides the semantics per status. This also lets the admin decide whether
On Hold should count as active (default: false).

### Sort order

1. `is_active = true` entries — always first (no secondary sort within this group)
2. `is_active = false` entries — sorted by `date_finished` per the user's chosen direction,
   with NULL dates sinking to the bottom

### Expected defaults

| Status    | is_active |
|-----------|-----------|
| TBR       | false     |
| Reading   | true      |
| On Hold   | false (admin may toggle) |
| Completed | false     |
| DNF       | false     |

---

## Pinned Entries

### "Pinned" not "Starred" or "Favorites"

The field was originally named `starred` with the UI label "Favorite". It was renamed to `pinned`
because the application already uses stars for the review rating system (1–5 stars). Calling the
flag "starred" or showing a star icon for it would create direct visual and conceptual confusion
with review ratings. "Pinned" (pin icon) is unambiguous and communicates the purpose: quick access,
not rating.

### Pinning on ReadingEntry only — not on Work

An initial implementation put the `pinned` flag on both `Work` and `ReadingEntry`. This was
rolled back to `ReadingEntry` only. The reasons:

1. **Works are global, not user-scoped.** Pinning a Work would imply a shared state, but the
   intent is personal quick access to specific reads.
2. **No toggle UI existed.** The Work detail page had no pin button — only a checkbox on the
   creation form. There was no way to change a Work's pinned state after creation.
3. **The concept maps to a read, not a work.** A user may want quick access to a specific entry
   (perhaps a re-read, or one with detailed comments), not to the work itself.

### Pin/unpin as a shared form outside the bulk form

The reading list main table is wrapped in `<form id="bulk-form">` for bulk actions. HTML does not
allow nested `<form>` elements — the browser ignores inner form tags, and their submit buttons
submit the outer form instead. This caused pin buttons in the main list to silently submit the
bulk form rather than the pin endpoint.

The fix: a single `<form id="pin-form" method="post">` is placed outside the bulk form (after
it in the DOM). Pin buttons use `type="button"` with `data-pin-url` and `data-pin-token`
attributes; an `onclick` handler sets the hidden form's `action` and `_token` fields then calls
`form.submit()`. This pattern must be preserved whenever the pin toggle is added to any context
that is inside another form.

The `_entry_row.html.twig` partial (used for the pinned section, which is outside the bulk form)
uses a regular inline `<form>` — no JS required — since it is not nested inside anything.

---

## Scraper Async Hardening

### Why `RateLimitException` is a sibling of `ScrapingException`, not a subclass

A rate limit is not a scraping failure — it is a signal to try again later. If `RateLimitException`
extended `ScrapingException`, any catch block handling permanent scrape failures would silently
swallow retry signals too. Keeping them as independent siblings forces each caller to explicitly
decide how to handle each case, which is the correct behaviour for both the sync controller
(distinct flash messages) and the future Messenger handler (distinct retry logic).

### Why 502 and 504 are treated the same as 429 and 503

502 (Bad Gateway) and 504 (Gateway Timeout) are transient Cloudflare/infrastructure responses
that are indistinguishable in practice from a rate limit — they mean "try again shortly", not
"this URL is permanently broken". Throwing `RateLimitException` for all four codes gives callers
a single, consistent signal: apply backoff and retry. If the distinction between rate limiting
and infrastructure hiccups ever needs different handling, a separate exception can be introduced
at that point with a concrete use case to design against.

### Why the Retry-After cap is 120 seconds

AO3 occasionally returns very large `Retry-After` values that would block a Messenger worker for
an unreasonable amount of time. Values above 120 s are treated as null (no instruction) and the
caller applies its own backoff strategy instead. The cap is logged as a warning so the operator
knows AO3's instruction was received but exceeded the threshold — the information is not silently
discarded.

### Why `scrapeWorkPage()` is not on `ScraperInterface`

YAGNI. It is an AO3-specific implementation detail that may not generalise to other scrapers
(FFN, Wattpad, Manual). The future batch orchestrator will depend on the concrete `Ao3Scraper`
directly. If it turns out every scraper needs a two-phase split, the interface can be extended
at that point with a real use case to design against rather than speculating upfront.

### Why the throttle tracks elapsed time rather than sleeping before every request

A fixed pre-request sleep would impose the full delay even when the calling code has already
spent time doing other work (parsing, DB writes, etc.) between requests. Tracking
`$lastRequestAt` and sleeping only the *remaining* gap means the delay is a minimum inter-request
gap, not a fixed overhead added on top of real processing time. This is both more polite (AO3
cares about wall-clock request spacing) and more efficient (no unnecessary waiting).

### Why `$lastRequestAt` is an instance property rather than a shared lock

The property persists for the lifetime of the service instance. In the web path, the container is
rebuilt per-request, so `$lastRequestAt` resets each time — effectively no throttle applies,
which is fine for a human submitting one URL at a time. In a Messenger worker, the container is
reused across messages, so the throttle correctly limits inter-message request rate within a
single worker process.

A shared lock (e.g. a database semaphore or Redis rate limiter) across multiple concurrent
workers is intentionally deferred to the batch orchestration layer. The scraper has no opinion
on process topology — it only throttles what it can observe: its own outbound requests.

---

## Export — Two Formats, Strategy Pattern

The export feature ships two XLSX formats:

- **Data Dump** — raw values (integers, plain text, dates). For personal data manipulation and
  third-party portability. Not designed to be re-imported into Compendium.
- **Familiar Format** — presentation-oriented, modelled after the original Google Sheets tracker.
  Designed for round-trip import back into Compendium.

### Why a strategy pattern rather than a single service with a flag?

The two formats have entirely different column layouts, encoding choices, and purpose. A single
service with conditional branches per format would grow unwieldy and would need to change whenever
either format changes. The strategy pattern (`ExportFormatInterface`) keeps each format
self-contained and makes adding a third format in the future a non-invasive change.

### Why column definitions are not shared between formats

The formats serve different purposes with no shared import contract. Coordinating column indices
between them would couple two independent things and add complexity with no benefit. Each format
implementation owns its own column layout.

---

## Export — Familiar Format, Column A (Completion Status)

Column A derives a human-readable completion label from `Status` flag combinations rather than
the status name. This is intentional: status names are admin-controlled at runtime and cannot be
relied upon in code.

| Condition | Output |
|-----------|--------|
| `counts_as_read = true` | `"Complete"` |
| `has_been_started = true` AND `counts_as_read = false` AND `is_active = true` | `"WIP"` |
| `has_been_started = true` AND `counts_as_read = false` AND `is_active = false` | `"Abandoned"` |
| `has_been_started = false` | *(blank)* |

### Why On Hold must have `is_active = true` for this to work

DNF and On Hold share identical flag values under the original defaults
(`has_been_started = true`, `counts_as_read = false`, `is_active = false`). Without distinguishing
them, both would export as "Abandoned" — incorrect for On Hold.

Setting `is_active = true` on On Hold resolves the ambiguity: On Hold + Reading both become "WIP",
while DNF remains "Abandoned". This is semantically consistent with the existing `is_active`
definition (an actively in-progress read). The change is made via the admin status edit UI — no
migration required.

---

## Export — Emoji Encoding is Intentionally Round-Trippable

The Familiar Format uses emoji to encode review and spice values in columns J, K, and L:
- J: `reviewStars` × ★
- K: `reviewStars` × ♥  
- L: `spiceStars` encoded as 🌶️×N, with 0 → 🚫 and NULL → blank

This encoding was deliberately chosen so that the future import feature can **count the characters**
to recover the integer. It is not purely cosmetic — it is a lossless encoding scheme.

### Spice import special case

`spice_stars = 0` exports as 🚫 (not zero chili emojis). The import service must treat 🚫 as `0`
and any cell that does not match the chili or 🚫 pattern as `NULL`. This handles legacy
spreadsheet data that may use different notation for absence of spice.

---

## Export — Soft-Delete Filter Bypassed

The repository method used by both export formats temporarily disables the Doctrine soft-delete
filter on `Work`. This means reading entries that reference soft-deleted works are included in
the export.

This is intentional: the export is a personal data backup. A user who logged a read against a
work that was later soft-deleted should still see that entry in their export. Hiding it would
produce a silently incomplete backup, which defeats the purpose of the feature.

---

### Recommended backoff strategy for the future Messenger handler

When the handler catches `RateLimitException`, it should requeue the message with a `DelayStamp`
using this formula:

- If `getRetryAfterSeconds()` is non-null: use that value directly (AO3 told us how long to wait)
- Otherwise: exponential backoff with jitter — `2^attempt + random_int(0, 1000) / 1000` seconds

The jitter term prevents a thundering-herd problem if multiple workers hit the rate limit
simultaneously and all requeue with the same delay. Without jitter, they would all retry at the
same moment and immediately trigger another rate limit.
