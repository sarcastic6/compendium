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

## Production Deployment (Docker)

The app ships with a `Dockerfile` (FrankenPHP) and `docker-compose.yml` for production.

### Prerequisites

- Docker (or Podman) with Compose support installed on the host
- A `.env.prod` file in the `app/` directory (see below) — **never committed to version control**

### 1. Create `.env.prod`

Copy `.env` and fill in production values:

```bash
cp .env .env.prod
```

At minimum, set:

```dotenv
APP_ENV=prod
APP_SECRET=<random 32-char string>
MAILER_DSN=smtp://your-smtp-host:587
MAILER_FROM=noreply@yourdomain.com
TOTP_ENCRYPTION_KEY=<base64-encoded 32-byte key>
```

Generate `APP_SECRET`: `openssl rand -hex 16`
Generate `TOTP_ENCRYPTION_KEY`: `php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"`

### 2. Prepare the data directory

The compose file mounts the SQLite database and Caddy TLS data from a directory **outside** the repo so they survive image rebuilds. Adjust the paths in `docker-compose.yml` to suit your server layout:

```yaml
volumes:
  - ../compendium-data/data.db:/app/var/data.db:z   # adjust path as needed
  - ../compendium-data/caddy:/data:Z                 # adjust path as needed
```

> **Note:** The `:Z` suffix is a SELinux relabelling option (required on SELinux-enforcing systems such as Fedora/RHEL). Remove it if your host does not use SELinux.

Create the directory and an empty database file before first run:

```bash
mkdir -p ../compendium-data
touch ../compendium-data/data.db
```

### 3. Build and start

```bash
docker compose up -d --build
```

### 4. Run migrations

```bash
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
```

Statuses and metadata types are seeded automatically by the migrations — no manual setup required.

### 5. Create your first admin user

Register at `/register`, then grant admin rights:

```bash
docker compose exec app php bin/console app:promote-user your@email.com
```

### Updating to a new version

```bash
git pull
docker compose down
docker compose up -d --build
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
```

## Background Worker

Background scraping (bulk URL import and spreadsheet import) uses Symfony Messenger. The default transport stores jobs in the application database — no external queue service is needed.

**In production (Docker):** the `worker` service in `docker-compose.yml` runs automatically alongside the app — no extra configuration needed.

**In development:**
```bash
php bin/console messenger:consume async -vv
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
