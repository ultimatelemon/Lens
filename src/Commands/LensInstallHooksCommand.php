<?php

namespace UltimateLemon\Lens\Commands;

use Illuminate\Console\Command;

class LensInstallHooksCommand extends Command
{
    protected $signature = 'lens:install-hooks {--force : Overwrite an existing pre-commit hook}';

    protected $description = 'Install a git pre-commit hook that blocks leftover lens() calls';

    public function handle(): int
    {
        $gitDir = base_path('.git');
        if (! is_dir($gitDir)) {
            $this->error('No .git directory found in ' . base_path() . ' — is this a git repository?');
            return self::FAILURE;
        }

        $hooksDir = $gitDir . '/hooks';
        if (! is_dir($hooksDir)) {
            mkdir($hooksDir, 0755, true);
        }

        $hookPath = $hooksDir . '/pre-commit';

        if (file_exists($hookPath) && ! $this->option('force')) {
            $existing = (string) file_get_contents($hookPath);
            if (str_contains($existing, 'lens:check')) {
                $this->info('Lens pre-commit hook is already installed.');
                return self::SUCCESS;
            }

            $this->error('A pre-commit hook already exists. Re-run with --force to overwrite, or add this line yourself:');
            $this->line('  php artisan lens:check --staged || exit 1');
            return self::FAILURE;
        }

        $script = "#!/bin/sh\n"
            . "# Lens — block commits that contain leftover lens() debug calls\n"
            . "php artisan lens:check --staged\n";

        file_put_contents($hookPath, $script);
        chmod($hookPath, 0755);

        $this->info('✓ Installed Lens pre-commit hook at .git/hooks/pre-commit');
        $this->line('Commits will now fail if a staged file contains a lens() call.');

        return self::SUCCESS;
    }
}
