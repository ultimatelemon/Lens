<?php

namespace UltimateLemon\Lens\Commands;

use Illuminate\Console\Command;

class LensTestCommand extends Command
{
    protected $signature = 'lens:test';

    protected $description = 'Send a test payload to the Lens desktop app';

    public function handle(): int
    {
        $config = config('lens', []);
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 23600;

        if (array_key_exists('enabled', $config) && ! $config['enabled']) {
            $this->warn('Lens is disabled (LENS_ENABLED=false). Nothing was sent.');
            return self::SUCCESS;
        }

        lens('Lens test payload', ['time' => now()->toDateTimeString()])
            ->label('artisan lens:test')
            ->color('green');

        $this->info("Sent a test payload to Lens ({$host}:{$port}).");
        $this->line('Open the Lens app — you should see a green "artisan lens:test" item.');

        return self::SUCCESS;
    }
}
