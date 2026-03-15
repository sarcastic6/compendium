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
        $usedKeys = $this->collectUsedKeys();
        $definedKeys = $this->loadDefinedKeys();

        $missing = array_values(array_diff($usedKeys, $definedKeys));
        sort($missing);

        self::assertEmpty(
            $missing,
            "The following translation keys are used in code but not defined in messages.en.yaml:\n" .
            implode("\n", $missing),
        );
    }

    /** @return list<string> */
    private function collectUsedKeys(): array
    {
        $keys = [];

        $this->collectFromPhpFiles($keys);
        $this->collectFromTwigFiles($keys);

        return array_unique($keys);
    }

    /**
     * Extracts keys from PHP source files.
     * Covers:
     *   - $this->addFlash('type', 'translation.key')           (controllers)
     *   - 'label' => 'translation.key'                         (form types)
     *   - 'invalid_message' => 'translation.key'               (form types)
     *   - 'placeholder' => 'translation.key'                   (form types, top-level and inside attr)
     *   - 'message' => 'translation.key'                       (validator constraints, array style)
     *   - message: 'translation.key'                           (validator constraints, named arg style)
     *
     * @param list<string> $keys
     */
    private function collectFromPhpFiles(array &$keys): void
    {
        $srcDir = __DIR__ . '/../../src';

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($srcDir, \FilesystemIterator::SKIP_DOTS),
        );

        // Key pattern: must start with a lowercase letter and contain at least one dot,
        // which distinguishes translation keys from bare words like 'success' or 'false'.
        $keyPattern = "[a-z][a-z0-9_]*(?:\\.[a-z0-9_]+)+";

        $patterns = [
            // addFlash('type', 'some.key')
            "/addFlash\\s*\\(\\s*'[^']+'\\s*,\\s*'($keyPattern)'/",
            // 'label' => 'some.key'
            // 'invalid_message' => 'some.key'
            // 'placeholder' => 'some.key'  (both top-level option and inside attr array)
            // 'message' => 'some.key'      (validator constraints, array style)
            "/'(?:label|invalid_message|placeholder|message)'\\s*=>\\s*'($keyPattern)'/",
            // message: 'some.key'          (validator constraints, named argument style)
            "/\\bmessage:\\s*'($keyPattern)'/",
        ];

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());

            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    foreach ($matches[1] as $key) {
                        $keys[] = $key;
                    }
                }
            }
        }
    }

    /**
     * Extracts keys from Twig template files.
     * Covers: 'some.translation.key'|trans
     *
     * @param list<string> $keys
     */
    private function collectFromTwigFiles(array &$keys): void
    {
        $templatesDir = __DIR__ . '/../../templates';

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($templatesDir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile() || $file->getExtension() !== 'twig') {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());

            // 'some.translation.key'|trans
            // Keys must contain at least one dot to distinguish them from bare strings.
            if (preg_match_all(
                "/'([a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+)'\s*\|trans/",
                $content,
                $matches,
            )) {
                foreach ($matches[1] as $key) {
                    $keys[] = $key;
                }
            }
        }
    }

    /** @return list<string> */
    private function loadDefinedKeys(): array
    {
        $yamlFile = __DIR__ . '/../../translations/messages.en.yaml';
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
