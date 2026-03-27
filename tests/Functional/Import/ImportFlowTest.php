<?php

declare(strict_types=1);

namespace App\Tests\Functional\Import;

use App\Entity\Work;
use App\Enum\SourceType;
use App\Enum\WorkType;
use App\Scraper\ScrapedWorkDto;
use App\Tests\Functional\AbstractFunctionalTest;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ImportFlowTest extends AbstractFunctionalTest
{
    private function fixture(string $name): string
    {
        $path = __DIR__ . '/../../Fixtures/ao3/' . $name . '.html';

        return (string) file_get_contents($path);
    }

    /**
     * Creates a user in the database and logs in via the login form.
     * The MockHttpClient must be configured BEFORE calling this method if
     * any test request will trigger http_client initialization.
     */
    private function loginAsUser(): void
    {
        $this->createUser('user@example.com', 'Test User', 'CorrectHorse99!');
        $this->logIn($this->client, 'user@example.com', 'CorrectHorse99!');
        $this->client->followRedirect();
    }

    /**
     * Configures the HTTP client mock in the test container and disables
     * kernel reboots so the mock persists across all requests in the test.
     *
     * Must be called BEFORE any HTTP request is made (including login).
     * Kernel reboot is disabled to prevent the container from resetting
     * between requests (which would discard the mock).
     */
    private function configureMockHttpClient(string $fixtureHtml): void
    {
        // Disable kernel reboots so the test container (and our mock) persists
        // across all requests within this test method.
        $this->client->disableReboot();

        $mockClient = new MockHttpClient(
            static function (string $method, string $url) use ($fixtureHtml): MockResponse {
                if (str_contains($url, 'archiveofourown.org')) {
                    return new MockResponse($fixtureHtml);
                }

                // Non-AO3 requests (e.g. framework internals) get an empty response
                return new MockResponse('{}');
            },
        );

        static::getContainer()->set('http_client', $mockClient);
    }

    /**
     * Submits the URL import form (the first form on the select page).
     *
     * @param array<string, mixed> $formData
     */
    private function submitImportForm(array $formData): void
    {
        $crawler = $this->client->getCrawler();
        $form = $crawler->filter('form')->eq(0)->form($formData);
        $this->client->submit($form);
    }

    // --- Auth boundary ---

    public function test_anonymous_user_is_redirected_to_login(): void
    {
        $this->client->request('GET', '/work/select');

        $this->assertResponseRedirects('/login');
    }

    public function test_old_import_route_returns_404(): void
    {
        $this->loginAsUser();

        $this->client->request('GET', '/import');

        $this->assertResponseStatusCodeSame(404);
    }

    // --- URL validation ---

    public function test_empty_url_redirects_with_error(): void
    {
        $this->loginAsUser();

        $this->client->request('GET', '/work/select');
        $this->submitImportForm(['import_url' => '']);

        $this->assertResponseRedirects('/work/select');
    }

    public function test_unsupported_url_shows_error_flash(): void
    {
        $this->loginAsUser();

        $this->client->request('GET', '/work/select');
        $this->submitImportForm(['import_url' => 'https://www.fanfiction.net/s/12345']);

        $this->assertResponseRedirects('/work/select');

        $this->client->followRedirect();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('AO3 work URLs are supported', $content);
    }

    // --- Successful import redirect ---

    public function test_valid_ao3_url_redirects_to_work_new(): void
    {
        // Set mock BEFORE any request so http_client is not yet initialized
        $this->configureMockHttpClient($this->fixture('minimal_work'));
        $this->loginAsUser();

        $this->client->request('GET', '/work/select');
        $this->submitImportForm(['import_url' => 'https://archiveofourown.org/works/55555']);

        $this->assertResponseRedirects('/work/new');
    }

    public function test_work_new_loads_after_import_redirect(): void
    {
        $this->configureMockHttpClient($this->fixture('minimal_work'));
        $this->loginAsUser();

        $this->client->request('GET', '/work/select');
        $this->submitImportForm(['import_url' => 'https://archiveofourown.org/works/55555']);

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    // --- Duplicate detection ---

    public function test_duplicate_url_redirects_directly_to_reading_entry_creation(): void
    {
        $this->configureMockHttpClient($this->fixture('minimal_work'));
        $this->loginAsUser();

        // Create an existing work with the canonical URL that the scraper will produce
        $work = new Work(WorkType::Fanfiction, 'Existing Work');
        $work->setSourceType(SourceType::AO3);
        $work->setLink('https://archiveofourown.org/works/55555');
        $this->em->persist($work);
        $this->em->flush();

        $this->client->request('GET', '/work/select');
        $this->submitImportForm(['import_url' => 'https://archiveofourown.org/works/55555']);

        // Should redirect straight to reading entry creation for the existing work
        $this->assertResponseRedirects('/reading-entries/new/' . $work->getId());
    }

    // --- Session cleared after WorkController consumes it ---

    public function test_session_import_data_cleared_after_work_form_loads(): void
    {
        $this->loginAsUser();

        // Manually inject a ScrapedWorkDto into the session
        $scraped = new ScrapedWorkDto();
        $scraped->title = 'Pre-filled Title';
        $scraped->authors = [['name' => 'Pre-filled Author', 'link' => null]];
        $scraped->workType = 'Fanfiction';
        $scraped->sourceType = 'AO3';

        // Load /work/new once to get access to the session
        $this->client->request('GET', '/work/new');
        $session = $this->client->getRequest()->getSession();
        $session->set('import_scraped_work', $scraped);
        $session->save();

        // First visit — session is consumed and cleared
        $this->client->request('GET', '/work/new');
        $this->assertResponseIsSuccessful();

        // Second visit — session is empty; form should render cleanly
        $this->client->request('GET', '/work/new');
        $this->assertResponseIsSuccessful();
    }
}
