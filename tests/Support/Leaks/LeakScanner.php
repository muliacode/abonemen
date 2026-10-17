<?php

namespace Tests\Support\Leaks;

use RuntimeException;
use ZipArchive;

/**
 * Searches files byte by byte for canary values (SEC-01, SEC-04).
 *
 * Compression would hide plaintext from a plain search, so the scanner opens
 * zip archives and gzip files and searches what is inside. Zip entries that
 * are encrypted cannot be opened and are searched as they are.
 */
final class LeakScanner
{
    /**
     * @param  list<Canary>  $canaries
     */
    public function __construct(private readonly array $canaries) {}

    /**
     * Searches every file under the given paths.
     *
     * @param  list<string>  $paths  Files or directories.
     * @return list<Leak>
     */
    public function scan(array $paths): array
    {
        $leaks = [];

        foreach ($paths as $path) {
            foreach ($this->files($path) as $file) {
                $contents = file_get_contents($file);
                if ($contents === false) {
                    throw new RuntimeException("Cannot read {$file}.");
                }

                array_push($leaks, ...$this->scanContents($file, $contents));
            }
        }

        return $leaks;
    }

    /**
     * @return list<Leak>
     */
    private function scanContents(string $location, string $contents, int $depth = 0): array
    {
        $leaks = [];

        foreach ($this->canaries as $canary) {
            foreach ($canary->forms as $form => $needle) {
                if ($needle !== '' && str_contains($contents, $needle)) {
                    $leaks[] = new Leak($canary->field, $form, $location);
                }
            }
        }

        // Archives inside archives are opened too, up to a sane depth.
        if ($depth < 3) {
            foreach ($this->unpack($contents) as $name => $inner) {
                array_push($leaks, ...$this->scanContents("{$location}!{$name}", $inner, $depth + 1));
            }
        }

        return $leaks;
    }

    /**
     * Returns the readable contents of a zip archive or gzip file, by entry name.
     *
     * @return array<string, string>
     */
    private function unpack(string $contents): array
    {
        if (str_starts_with($contents, "\x1f\x8b")) {
            $inflated = @gzdecode($contents);

            return $inflated === false ? [] : ['(gzip)' => $inflated];
        }

        if (! str_starts_with($contents, "PK\x03\x04")) {
            return [];
        }

        $temporary = tempnam(sys_get_temp_dir(), 'abonemen-leak-');
        file_put_contents($temporary, $contents);

        try {
            $zip = new ZipArchive;
            if ($zip->open($temporary, ZipArchive::RDONLY) !== true) {
                return [];
            }

            $entries = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if ($stat === false || $stat['encryption_method'] !== ZipArchive::EM_NONE) {
                    continue;
                }

                $entry = $zip->getFromIndex($index);
                if ($entry !== false) {
                    $entries[$stat['name']] = $entry;
                }
            }
            $zip->close();

            return $entries;
        } finally {
            @unlink($temporary);
        }
    }

    /**
     * @return iterable<string>
     */
    private function files(string $path): iterable
    {
        if (is_file($path)) {
            yield $path;

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                yield $file->getPathname();
            }
        }
    }
}
