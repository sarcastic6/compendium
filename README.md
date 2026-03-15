# Reading Stats

A web application for tracking reading consumption across books, fanfiction, and other written works. Built with Symfony 7.4 and designed for multi-user use from day one.

Reading Stats replaces a 2,100+ entry spreadsheet with a proper database-backed application, complete with AO3 metadata import, flexible tagging, and user authentication with MFA support.

## Features

- **Reading log** -- Track what you read with status, dates, star ratings, spice ratings, and comments
- **Work catalog** -- Shared catalog of books and fanfiction with authors, series, word counts, and rich metadata
- **AO3 import** -- Paste an Archive of Our Own URL to auto-populate work metadata (title, authors, tags, word count, chapters, series, and more)
- **Flexible tagging** -- Tag works with fandoms, characters, pairings, ratings, warnings, and custom tag types
- **Multi-user** -- Each user has their own private reading log with complete data isolation
- **Authentication** -- Email/password login with optional TOTP or email-based MFA, password reset, and remember-me
- **Admin tools** -- Manage statuses, metadata types, and system configuration

## Tech Stack

| Layer | Technology |
|-------|------------|
| Language | PHP 8.2+ |
| Framework | Symfony 7.4 |
| ORM | Doctrine 3.0+ |
| Database | SQLite (dev), MySQL, PostgreSQL |
| Frontend | Bootstrap 5.3, Twig, Stimulus |
| Auth | Symfony Security, scheb/2fa-bundle |
| Testing | PHPUnit |

## Requirements

- PHP 8.2 or higher
- Composer
- The following PHP extensions: `pdo_sqlite` (for development), `ctype`, `iconv`, `sodium`
- A mail server for password reset and email MFA (e.g., [Mailpit](https://mailpit.axllent.org/) for development)

## Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/your-username/reading-stats.git
   cd reading-stats/app
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
   - `DATABASE_URL` -- defaults to SQLite (`sqlite:///%kernel.project_dir%/var/data.db`)
   - `MAILER_DSN` -- SMTP connection for emails (e.g., `smtp://localhost:1025` for Mailpit)

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

6. **Create your first user:**
   Register at `/register` (registration is enabled by default). Then promote yourself to admin:
   ```bash
   php bin/console app:promote-user your@email.com
   ```

## First-Time Setup

After installation, log in as admin and configure reference data:

1. **Statuses** (`/admin/statuses`) -- Create reading statuses such as: TBR, Reading, On Hold, Completed, DNF
2. **Metadata Types** (`/admin/metadata-types`) -- Create tag categories such as: Rating, Warning, Category, Fandom, Character, Pairing, Tag

These are auto-created during AO3 import if they do not already exist, but creating them in advance gives you control over the `multiple_allowed` setting.

## Usage

### Manual entry
1. Navigate to **New Entry** and create a new work (or select an existing one)
2. Fill in work details: title, author(s), type, metadata tags
3. Create a reading entry: set status, dates, ratings, and comments

### AO3 import
1. Navigate to **Import**
2. Paste an AO3 work URL (e.g., `https://archiveofourown.org/works/12345`)
3. Review the pre-filled work form -- edit anything the scraper missed or got wrong
4. Save the work and create your reading entry

The scraper extracts **metadata only** (title, authors, tags, word count, etc.). It never accesses or stores story content.

## Running Tests

```bash
php bin/phpunit
```

Tests focus on security boundaries and data integrity:
- User isolation (users cannot access each other's data)
- Authentication and access control
- Work creation and metadata persistence
- Reading entry validation
- AO3 scraper HTML parsing (fixture-based)
- Import flow end-to-end

## Project Structure

```
app/
  src/
    Controller/     # Thin controllers (auth, admin, works, entries, import)
    Dto/            # Data transfer objects for forms and import
    Entity/         # Doctrine entities (9 core entities)
    Enum/           # PHP enums (WorkType, SourceType, UserRole)
    Form/           # Symfony form types
    Repository/     # Doctrine repositories with custom queries
    Scraper/        # Pluggable scraper interface + AO3 implementation
    Security/       # User checker, auth listeners
    Service/        # Business logic (work creation, import mapping, etc.)
  templates/        # Twig templates (Bootstrap 5.3)
  translations/     # i18n (messages.en.yaml)
  tests/
    Functional/     # WebTestCase tests (security, data integrity, import)
    Unit/           # Unit tests (scraper, services)
```

## Roadmap

- [x] **Phase 1** -- User auth, reading entry form, list view
- [x] **Phase 2** -- AO3 metadata import with pluggable scraper architecture
- [x] **Phase 2.5** -- Auto-create reference data during import, work detail page
- [ ] **Phase 3** -- Entry management (edit, delete, search/filter)
- [ ] **Phase 4** -- Analytics dashboards (yearly stats, author/tag/pairing rankings)

## License

This project is licensed under the [GNU General Public License v3.0](https://www.gnu.org/licenses/gpl-3.0.en.html). See `LICENSE` for details.
