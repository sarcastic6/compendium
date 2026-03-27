# AGENTS.md

## AI Contribution Guidelines

Welcome, AI assistant. Follow these rules when contributing to this
repository. If a rule here conflicts with a general coding habit, follow
this document.

------------------------------------------------------------------------

# Project Overview

Reading Stats (branded as **Compendium**) is a Symfony web application
that tracks reading activity across books, fanfiction, and other written
works.

Users can:

-   record and manage reading entries
-   import works automatically from AO3 via URL
-   track authors, fandoms, characters, pairings, and tags
-   search and filter their reading list
-   analyze reading trends through dashboards and statistics
-   pin entries for quick access
-   earn achievements and track yearly reading goals

## Tech Stack

-   PHP 8.2+
-   Symfony 7.4
-   Doctrine ORM 3.0+
-   Twig
-   Bootstrap 5.3
-   SQLite / MySQL / PostgreSQL compatibility

------------------------------------------------------------------------

# Core Architecture

Follow Symfony best practices.

## Directory Structure

src/ 
  Controller/ 
  Entity/ 
  Repository/ 
  Service/ 
  Security/ 
  Form/

templates/ 
  components/

config/ 
  services/

tests/ 
  Functional/ 
  Unit/

## Architectural Rules

-   Controllers must stay thin
-   Business logic belongs in services
-   Database access belongs in repositories
-   Entities contain only data and simple helpers
-   Do not place business logic inside controllers
-   Use PHP Attributes for all routing, validation, and Doctrine mapping

------------------------------------------------------------------------

# AI Contribution Rules

When generating code:

-   Follow existing patterns before creating new ones
-   Prefer modifying existing code over creating new abstractions
-   Do not introduce new dependencies unless absolutely necessary
-   Do not introduce new frameworks or build systems
-   Do not add JavaScript frameworks
-   Keep controllers thin
-   Check for existing services/components before creating new ones

If unsure, choose the simpler implementation.

------------------------------------------------------------------------

# Files You Must Not Modify

Directories never to edit:

vendor/ node_modules/ var/ public/bundles/

Files only modified by package managers:

composer.lock yarn.lock

------------------------------------------------------------------------

# General Rules

## Language

-   American English
-   Code strings use single quotes
-   Use straight quotes only (' and ")

## Security

Always prevent:

-   XSS
-   CSRF
-   SQL injection
-   authentication bypass
-   open redirects

Never trust user input.

------------------------------------------------------------------------

# Git and Pull Requests

## Commit Messages

Use conventional commit style:

feat: add reading entry form
fix: prevent unauthorized entry deletion
refactor: move stats logic to service

Rules:

-   Subject line ≤ 72 characters
-   Use imperative mood
-   No period at the end

Common prefixes:

feat -- new feature\
fix -- bug fix\
refactor -- restructure code without behavior change\
test -- add/update tests\
docs -- documentation changes\
chore -- maintenance tasks

## Branch Naming

Use lowercase with dashes.  The prefix should be followed by a slash.

Examples:

fix/123 (referring to an issue number)
feature/add-reading-entry
refactor/stats-service

------------------------------------------------------------------------

# PHP Code Standards

Follow:

-   PSR-1
-   PSR-4
-   PSR-12

## Syntax Rules

-   Every file must begin with `declare(strict_types=1);`
-   Strict comparisons only (===, !==)
-   Braces required for control structures
-   Trailing commas in multiline arrays
-   Always use parentheses when instantiating
-   Prefer early returns instead of nested else

Example:

if (!$user) {
    throw new AccessDeniedException();
}

return $user;

## Naming Conventions

Variables -- camelCase\
Methods -- camelCase\
Classes -- UpperCamelCase\
Constants -- SCREAMING_SNAKE_CASE\
Templates -- snake_case

Suffix conventions:

*Controller\
*Dto\
*Event\
*Field\
*Filter\
*Subscriber\
*Type\
*Test

Interfaces use Interface, traits use Trait, exceptions use Exception.

## PHPDoc

Follow PSR-5 for PHPDoc formatting.

------------------------------------------------------------------------

# Class Organization

Order inside classes:

1.  properties
2.  constructor
3.  public methods
4.  protected methods
5.  private methods

------------------------------------------------------------------------

# Code Practices

Prefer:

-   enums instead of constant sets
-   project constants instead of hardcoded strings

Avoid:

-   else after return or throw
-   silent exception catches
-   unnecessary comments

Comments should explain why, not what.

------------------------------------------------------------------------

# Service Configuration

Use a hybrid configuration approach.

YAML for simple services, PHP for complex configuration.

Use PHP when services require environment-dependent logic, factories,
or conditional configuration.

Example `config/services.php`:

    return static function (ContainerConfigurator $configurator): void {
        $configurator->import('services/base.yaml');
        $configurator->import('services/repositories.yaml');

        $services = $configurator->services();

        $services->set(PaymentProcessor::class)
            ->arg('$apiKey', env('PAYMENT_API_KEY'))
            ->arg('$timeout', 'prod' === env('APP_ENV') ? 60 : 30);
    };

Example `config/services/base.yaml`:

    services:
      _defaults:
        autowire: true
        autoconfigure: true

      App\Service\EmailService:
        arguments:
          $smtpHost: '%env(MAIL_HOST)%'
          $fromAddress: '%env(MAIL_FROM_ADDRESS)%'

------------------------------------------------------------------------

# Database and ORM

The application must work with:

-   SQLite
-   MySQL
-   PostgreSQL

without modification.

Rules:

-   Use Doctrine ORM for entity operations
-   Use Doctrine attributes for entity mapping
-   Use Doctrine QueryBuilder for complex queries
-   Avoid raw SQL

Avoid database-specific syntax and functions.

If raw SQL is unavoidable, use parameterized queries.

## Entity Guidelines

Entities must:

- use Doctrine attributes for mapping
- use typed properties
- use `DateTimeImmutable` for dates
- avoid public properties
- use public readonly properties where mutation is not needed,
otherwise use getters/setters

Primary keys should use:

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]

## DTO Usage

Entities represent database state and must not be used directly as
request or response payloads.

Templates should not depend directly on entity structure when a DTO 
is provided.

Use DTOs (*Dto suffix) when:

-   handling form input that does not map 1:1 to an entity
-   performing partial updates
-   exposing data to templates or APIs
-   combining data from multiple entities

Rules:

-   DTOs must be simple data containers
-   No business logic inside DTOs
-   Mapping between DTOs and entities should be handled in services

------------------------------------------------------------------------

# Templates (Twig)

Rules:

-   Use translation keys for all user-visible text
-   Never hardcode text
-   Use components from templates/components/ when available
-   Ensure accessibility (aria attributes, labels, semantic HTML)

Icons use Bootstrap

------------------------------------------------------------------------

# Forms

Use Symfony Form classes for all user input handling.

Rules:

-   Define forms in `src/Form/`
-   Use Symfony Form Types (*Type suffix)
-   Do not manually process $_POST data

Validation:

-   Use Symfony Validator via PHP attributes on entities or DTOs
-   Do not perform validation inside controllers
-   Use the `validators` translation domain for validation messages

Controllers:

-   Controllers must delegate form handling to Symfony Forms
-   Do not manually map request data to entities

If a form already exists for a use case, reuse or extend it instead of creating a new one.

------------------------------------------------------------------------

# Translations

All user-facing text must use the `|trans` filter. Never hardcode
strings in templates or PHP.

Translation files live in `translations/`. Example:

    translations/messages.en.yaml
    translations/messages.fr.yaml

Key naming pattern: `section.entity.label`

Example keys and structure:

    reading:
      entry:
        title: 'Reading entry'
        add: 'Add entry'
        delete: 'Delete entry'
      dashboard:
        heading: 'Your reading stats'
    nav:
      home: 'Home'
      logout: 'Log out'
    action:
      save: 'Save'
      cancel: 'Cancel'

In a template:

    {{ 'reading.entry.add'|trans }}

When adding a new string:

1.  Add the key to `translations/messages.en.yaml`
2.  Use the key in the template with `|trans`
3.  Do not use hardcoded fallback text

Validator messages use the `validators` domain:

    {{ 'reading.entry.title.not_blank'|trans({}, 'validators') }}

------------------------------------------------------------------------

# Frontend Architecture

Use Symfony's AssetMapper:
-   Serve assets directly from the `assets/` directory
-   Manage external libraries via `importmap.php`
-   Use standard ES6 modules for JavaScript
-   Load Bootstrap 5.3 via AssetMapper or CDN
-   Place images in `assets/images/` and reference via `asset()` Twig helper

The UI must be mobile-first.

Principles:

-   mobile-first layouts
-   minimum 44px touch targets
-   avoid hover-only interactions
-   semantic HTML

## JavaScript

Use:

-   ES6+
-   progressive enhancement
-   event delegation

Avoid:

-   inline event handlers
-   tightly coupling JS to templates

------------------------------------------------------------------------

# CSS

Use standard CSS only.

Rules:
-   4-space indentation
-   Bootstrap 5.3 utilities preferred
-   kebab-case class names
-   mobile-first styling

Breakpoints:

md ≥ 768px\
lg ≥ 992px\
xl ≥ 1200px

Avoid:

-   SCSS / LESS
-   nested rules
-   fixed widths

Prefer fluid layouts.

------------------------------------------------------------------------

# Anti-Patterns

Never:

-   hardcode user-facing text
-   use typographic quotes
-   use database-specific SQL
-   use SCSS or LESS
-   add nested CSS rules

------------------------------------------------------------------------

# Testing

Focus testing on behavior, security, and data integrity.

## Core Principle

Tests must verify externally observable behavior, not internal
implementation.

Do not write tests that depend on:

- private methods
- internal class structure
- specific implementation details

Refactoring internal logic must not require rewriting tests if behavior
remains unchanged.

## What to Test

Test public behavior through:

- public service methods
- HTTP endpoints (controllers)
- repository results (when behavior is query-dependent)

Focus on:

Security:

- users cannot access other users' data
- authentication boundaries

Data integrity:

- entries save correctly
- updates affect correct records
- deletes remove only intended records

Business behavior:

- statistics calculations return correct results
- filters and queries return expected data

## What Not to Test

Do not test:

- private or protected methods directly
- trivial getters and setters
- simple data passthrough
- framework behavior (Symfony internals, Doctrine internals)
- implementation details

## Test Types

Unit tests:

- test service classes
- test business logic in isolation
- avoid database access where possible

Functional tests:

- test full request/response flow
- verify routing, forms, and security
- use Symfony WebTestCase

Do not test visual output or styling.

## Test Design Rules

- Each test should verify one complete behavior
- Use descriptive test names:

  test_user_cannot_view_other_users_entries

- Prefer real objects over mocks unless isolation is required
- Mock only external dependencies (e.g., HTTP clients)
- Tests should use the same public interfaces as production code.
- Avoid bypassing services unless testing repository-specific behavior.

- Write the minimum number of tests required to confidently verify behavior
- Avoid redundant or low-value tests
- Prefer fewer, high-value tests over many narrow tests

## Creating Test Data

Before writing any test data setup, explain your approach and wait
for confirmation before proceeding.

------------------------------------------------------------------------

# Running Tests

php bin/phpunit tests/Functional/

------------------------------------------------------------------------

# Documentation

Documentation lives in:

doc/

Rules:

-   reStructuredText format
-   line length 72--78 characters
-   realistic examples
-   include use statements in code examples

------------------------------------------------------------------------

# Additional AI Quality Rules

## Do Not Invent Libraries

Do not introduce:

-   new PHP libraries
-   frontend frameworks
-   build systems

Use only dependencies already present in composer.json.
If a new dependency is needed, stop and ask before adding it.

## Follow Existing Patterns First

Before creating new classes or patterns:

1.  search for similar implementations
2.  reuse existing services/components
3.  match naming conventions

Consistency is more important than novelty.

## Prefer Simpler Solutions

Avoid overengineering.

Prefer:

-   small classes
-   clear services
-   straightforward queries

## External HTTP Requests

For all external requests (e.g., AO3 metadata), use the native Symfony HttpClient.

------------------------------------------------------------------------

# Summary

When contributing:

1.  Follow existing project patterns
2.  Keep architecture clean
3.  Protect security and data integrity
4.  Use translations for user-visible text
5.  Ensure database portability

If uncertain, choose the simplest correct implementation.
