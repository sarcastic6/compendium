# Compendium

A web application for tracking reading consumption across books, fanfiction, and other written works; built with Symfony 7.4.

Compendium replaces a reading list spreadsheet with a proper database-backed application, complete with AO3 metadata import, 
flexible tagging, analytics dashboards, and user authentication with MFA support.

## Features

- **Reading log** — Track what you read with status, dates, star ratings, spice ratings, and comments
- **Work catalog** — Shared catalog of books and fanfiction with authors, series, word counts, and rich metadata
- **AO3 import** — Paste an AO3 URL to auto-populate work metadata (title, authors, tags, word count, chapters, series, and more)
- **Bulk AO3 import** — Paste a list of AO3 URLs to queue multiple works for background scraping at once
- **Analytics** — Reading trend charts, distribution breakdowns, metadata rankings, series coverage, and reading goals
- **Achievements** — Automatic achievement tracking as you log reads
- **Import / export** — Import from a Familiar Format XLSX spreadsheet; export as a Data Dump or Familiar Format XLSX
- **Flexible tagging** — Tag works with fandoms, characters, pairings, ratings, warnings, and custom tag types
- **Multi-user** — Each user has their own private reading log with complete data isolation
- **Authentication** — Email/password login with optional TOTP or email-based MFA, password reset, and remember-me
- **Admin tools** — Manage statuses, metadata types, and system configuration

## Tech Stack

| Layer | Technology |
|-------|------------|
| Language | PHP 8.2+ |
| Framework | Symfony 7.4 |
| ORM | Doctrine 3.0+ |
| Database | SQLite (dev), MySQL, PostgreSQL |
| Frontend | Bootstrap 5.3, Twig, Stimulus |
| Auth | Symfony Security, scheb/2fa-bundle |
| Queue | Symfony Messenger (Doctrine transport by default) |
| Testing | PHPUnit |

## Requirements

- PHP 8.2 or higher with extensions: `pdo_sqlite` (dev), `ctype`, `iconv`, `sodium`
- Composer
- A mail server for password reset and email MFA (e.g. [Mailpit](https://mailpit.axllent.org/) for development)

## Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/sarcastic6/compendium.git
   cd compendium
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Configure environment:**
   ```bash
   cp .env .env.local
   ```
   Edit `.env.local` and set at minimum:
   - `DATABASE_URL` — defaults to SQLite (`sqlite:///%kernel.project_dir%/var/data.db`)
   - `MAILER_DSN` — SMTP connection for emails (e.g. `smtp://localhost:1025` for Mailpit)

4. **Create the database and run migrations:**
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

5. **Start the development server:**
   ```bash
   symfony server:start
   ```
   Or use PHP's built-in server:
   ```bash
   php -S localhost:8000 -t public
   ```

6. **Start the Messenger worker** (required for background AO3 scraping):
   ```bash
   php bin/console messenger:consume async --time-limit=3600
   ```
   See [Background worker](#background-worker) below for details.

7. **Create your first user:**
   Register at `/register` (enabled by default), then promote yourself to admin:
   ```bash
   php bin/console app:promote-user your@email.com
   ```

## First-Time Setup

After installation, log in as admin and configure reference data:

1. **Statuses** (`/admin/statuses`) — Create reading statuses: TBR, Reading, On Hold, Completed, DNF
2. **Metadata Types** (`/admin/metadata-types`) — Create tag categories: Rating, Warning, Category, Fandom, Character, Relationships, Tag

These are auto-created during AO3 import if they do not already exist, but creating them in advance lets you configure `multiple_allowed` and `show_as_dropdown` correctly from the start.

## Background Worker

Background scraping (bulk URL import and spreadsheet import) uses Symfony Messenger. The default transport stores jobs in the application database — no external queue service is needed.

**Run the worker in development:**
```bash
php bin/console messenger:consume async -vv
```

**Run the worker in production** (keep it alive with a process supervisor):

Using Supervisor (`/etc/supervisor/conf.d/compendium-worker.conf`):
```ini
[program:compendium-worker]
command=php /path/to/app/bin/console messenger:consume async --time-limit=3600 --memory-limit=128M
autostart=true
autorestart=true
user=www-data
stderr_logfile=/var/log/compendium-worker.err.log
stdout_logfile=/var/log/compendium-worker.out.log
```

**Environment variables:**

| Variable | Default | Description |
|----------|---------|-------------|
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=0` | Queue transport. Use `doctrine://default` for DB-backed queue, or `redis://localhost:6379/messages` / `amqp://...` for dedicated brokers |
| `SCRAPER_REQUEST_DELAY_MS` | `2000` | Minimum milliseconds between AO3 HTTP requests within a single worker. Increase if you are hitting rate limits. |

**Monitoring scrape jobs:**

Navigate to **Data → Scrape status** (`/data/scrape-status`) to see pending and failed scrape jobs. Failed jobs can be retried individually from that page.

## Usage

### Manual entry
1. Go to **New Entry**, create or select a work
2. Fill in work details, then create your reading entry

### AO3 import (single URL)
1. Go to **New Entry**
2. Paste an AO3 work URL — the scraper pre-fills the work form
3. Review, save the work, and create your reading entry

### Bulk AO3 import
1. Go to **Data → Import AO3 URLs** (`/data/import/urls`)
2. Paste one AO3 URL per line
3. Works are created immediately and queued for background scraping
4. Monitor progress at **Data → Scrape status**

The scraper extracts **metadata only** (title, authors, tags, word count, etc.). It never accesses or stores story content.

## Running Tests

```bash
php bin/phpunit tests/Functional/
php bin/phpunit tests/Unit/
```

Tests focus on security boundaries and data integrity:
- User isolation (users cannot access each other's data)
- Authentication and access control
- CSRF protection on all mutation endpoints
- Reading entry validation and data integrity
- AO3 scraper HTML parsing (fixture-based)
- Import and export flows

## Project Structure

```
app/
  src/
    Controller/     # Thin controllers
    Dto/            # Data transfer objects for forms and import
    Entity/         # Doctrine entities
    Enum/           # PHP enums (WorkType, SourceType, ScrapeStatus, ...)
    Form/           # Symfony form types
    Message/        # Messenger message classes (ScrapeWorkMessage)
    MessageHandler/ # Messenger handlers (ScrapeWorkMessageHandler)
    Repository/     # Doctrine repositories with custom queries
    Scraper/        # Pluggable scraper interface + AO3 implementation
    Security/       # User checker, auth listeners
    Service/        # Business logic (import, export, stats, achievements, ...)
  templates/        # Twig templates
  translations/     # i18n (messages.en.yaml)
  tests/
    Functional/     # WebTestCase tests (security, data integrity, import)
    Unit/           # Unit tests (scraper, services, stats calculations)
```

## License

This project is licensed under the [GNU General Public License v3.0](https://www.gnu.org/licenses/gpl-3.0.en.html). See `LICENSE` for details.
