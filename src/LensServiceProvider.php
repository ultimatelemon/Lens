<?php

namespace UltimateLemon\Lens;

use Illuminate\Support\ServiceProvider;

class LensServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/lens.php', 'lens');
    }

    public function boot(): void
    {
        $config = $this->app['config']->get('lens', []);

        Lens::configure(
            $config['host'] ?? '127.0.0.1',
            (int) ($config['port'] ?? 23600)
        );

        if (array_key_exists('enabled', $config)) {
            $config['enabled'] ? Lens::enable() : Lens::disable();
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/lens.php' => $this->app->configPath('lens.php'),
            ], 'lens-config');
        }
    }
}
