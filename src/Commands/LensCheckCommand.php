<?php

namespace UltimateLemon\Lens\Commands;

use Illuminate\Console\Command;

class LensCheckCommand extends Command
{
    protected $signature = 'lens:check
        {--path=* : Directories to scan (default: app, routes, database, resources)}
        {--staged : Only scan files staged for commit}';

    protected $description = 'Check the codebase for leftover lens() debug calls';

    // Matches a lens( helper call, but not ->lens(, $lens(, getLens(, Lens::lens(
    protected string $pattern = '/(?<![\w$>:\-])lens\s*\(/';

    public function handle(): int
    {
        $files = $this->option('staged') ? $this->stagedPhpFiles() : $this->scanPaths();

        $hits = [];
        foreach ($files as $file) {
            if (! is_file($file)) {
                continue;
            }
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if ($lines === false) {
                continue;
            }
            foreach ($lines as $i => $line) {
                if (preg_match($this->pattern, $line)) {
                    $hits[] = [$file, $i + 1, trim($line)];
                }
            }
        }

        if (empty($hits)) {
            $this->info('✓ No leftover lens() calls found.');
            return self::SUCCESS;
        }

        $this->error('Found ' . count($hits) . ' leftover lens() call(s):');
        foreach ($hits as [$file, $line, $code]) {
            $this->line("  <fg=yellow>{$file}:{$line}</> — {$code}");
        }
        $this->newLine();
        $this->warn('Remove these debug calls before committing.');

        return self::FAILURE;
    }

    protected function scanPaths(): array
    {
        $paths = $this->option('path');
        if (empty($paths)) {
            $paths = ['app', 'routes', 'database', 'resources'];
        }

        $files = [];
        foreach ($paths as $path) {
            $full = base_path($path);

            if (is_file($full)) {
                $files[] = $full;
                continue;
            }
            if (! is_dir($full)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($full, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $entry) {
                if ($entry->isFile() && $entry->getExtension() === 'php') {
                    $files[] = $entry->getPathname();
                }
            }
        }

        return $files;
    }

    protected function stagedPhpFiles(): array
    {
        $output = [];
        exec('git diff --cached --name-only --diff-filter=ACM 2>/dev/null', $output);

        $files = [];
        foreach ($output as $relative) {
            if (substr($relative, -4) === '.php') {
                $files[] = base_path($relative);
            }
        }

        return $files;
    }
}
