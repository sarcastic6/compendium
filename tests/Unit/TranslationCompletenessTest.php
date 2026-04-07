<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Ensures every translation key used in PHP controllers and Twig templates
 * is defined in messages.en.yaml.
 *
 * Missing keys silently render as raw key strings in production (e.g. "reading.entry.add"
 * instead of "Add Entry"), so this test catches that class of bug at CI time.
 */
class TranslationCompletenessTest extends TestCase
{
    public function test_all_used_translation_keys_are_defined(): void
    {
        $messagesKeys  = $this->collectMessagesKeys();
        $validatorKeys = $this->collectValidatorConstraintKeys();

        $missingFromMessages   = array_diff($messagesKeys, $this->loadDefinedKeys('messages.en.yaml'));
        $missingFromValidators = array_diff($validatorKeys, $this->loadDefinedKeys('validators.en.yaml'));

        $missing = array_values(array_unique(array_merge($missingFromMessages, $missingFromValidators)));
        sort($missing);

        self::assertEmpty(
            $missing,
            "The following translation keys are used in code but not defined in their expected translation file:\n" .
            implode("\n", $missing),
        );
    }

    /**
     * Collects keys that belong to the messages domain:
     *   - $this->addFlash('type', 'translation.key')          (controllers)
     *   - 'label' => 'translation.key'                        (form types)
     *   - 'invalid_message' => 'translation.key'              (form types)
     *   - 'placeholder' => 'translation.key'                  (form types)
     *   - 'some.translation.key'|trans                        (Twig templates)
     *
     * @return list<string>
     */
    private function collectMessagesKeys(): array
    {
        $keys = [];

        $keyPattern = "[a-z][a-z0-9_]*(?:\\.[a-z0-9_]+)+";

        $phpPatterns = [
            // addFlash('type', 'some.key')
            "/addFlash\\s*\\(\\s*'[^']+'\\s*,\\s*'($keyPattern)'/",
            // 'label' => 'some.key' | 'invalid_message' => 'some.key' | 'placeholder' => 'some.key'
            "/'(?:label|invalid_message|placeholder)'\\s*=>\\s*'($keyPattern)'/",
        ];

        foreach ($this->phpSourceFiles() as $content) {
            foreach ($phpPatterns as $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    foreach ($matches[1] as $key) {
                        $keys[] = $key;
                    }
                }
            }
        }

        foreach ($this->twigSourceFiles() as $content) {
            // 'some.translation.key'|trans  (without an explicit domain argument)
            if (preg_match_all(
                "/'([a-z][a-z0-9_]*(?:\\.[a-z0-9_]+)+)'\\s*\\|trans(?:\\s*\\(\\s*\\{[^}]*\\}\\s*\\))?(?!\\s*,)/",
                $content,
                $matches,
            )) {
                foreach ($matches[1] as $key) {
                    $keys[] = $key;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Collects keys that belong to the validators domain:
     *   - 'message' => 'translation.key'   (validator constraints, array style)
     *   - message: 'translation.key'        (validator constraints, named arg style)
     *
     * These are looked up via the validators translation domain and must be
     * defined in validators.en.yaml, not messages.en.yaml.
     *
     * @return list<string>
     */
    private function collectValidatorConstraintKeys(): array
    {
        $keys = [];

        $keyPattern = "[a-z][a-z0-9_]*(?:\\.[a-z0-9_]+)+";

        $patterns = [
            // 'message' => 'some.key'   (array style)
            "/'message'\\s*=>\\s*'($keyPattern)'/",
            // message: 'some.key'       (named argument style)
            "/\\bmessage:\\s*'($keyPattern)'/",
        ];

        foreach ($this->phpSourceFiles() as $content) {
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    foreach ($matches[1] as $key) {
                        $keys[] = $key;
                    }
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Yields the string content of each PHP source file under src/.
     *
     * @return iterable<string>
     */
    private function phpSourceFiles(): iterable
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(__DIR__ . '/../../src', \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield (string) file_get_contents($file->getPathname());
            }
        }
    }

    /**
     * Yields the string content of each Twig template file under templates/.
     *
     * @return iterable<string>
     */
    private function twigSourceFiles(): iterable
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(__DIR__ . '/../../templates', \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && $file->getExtension() === 'twig') {
                yield (string) file_get_contents($file->getPathname());
            }
        }
    }

    /** @return list<string> */
    private function loadDefinedKeys(string $filename): array
    {
        $yamlFile = __DIR__ . '/../../translations/' . $filename;
        /** @var array<string, mixed> $data */
        $data = Yaml::parseFile($yamlFile);

        return $this->flattenKeys($data);
    }

    /**
     * Recursively flattens a nested array into dot-notation keys.
     *
     * ['nav' => ['home' => 'My List']] → ['nav.home']
     *
     * @param array<string, mixed> $array
     * @return list<string>
     */
    private function flattenKeys(array $array, string $prefix = ''): array
    {
        $keys = [];

        foreach ($array as $key => $value) {
            $fullKey = $prefix !== '' ? "$prefix.$key" : (string) $key;

            if (is_array($value)) {
                $keys = array_merge($keys, $this->flattenKeys($value, $fullKey));
            } else {
                $keys[] = $fullKey;
            }
        }

        return $keys;
    }
}
