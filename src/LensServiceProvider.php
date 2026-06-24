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

        $enabled = ! array_key_exists('enabled', $config) || $config['enabled'];

        if (array_key_exists('enabled', $config)) {
            $config['enabled'] ? Lens::enable() : Lens::disable();
        }

        if ($enabled && ($config['catch_exceptions'] ?? true)) {
            $this->registerExceptionHandler();
        }

        if ($enabled) {
            if ($config['queries'] ?? false) Lens::showQueries();
            if ($config['mails'] ?? false) Lens::showMails();
            if ($config['jobs'] ?? false) Lens::showJobs();
            if ($config['events'] ?? false) Lens::showEvents();
            if ($config['models'] ?? false) Lens::showModels();
            if ($config['notifications'] ?? false) Lens::showNotifications();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\LensCheckCommand::class,
                Commands\LensTestCommand::class,
                Commands\LensInstallHooksCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/lens.php' => $this->app->configPath('lens.php'),
            ], 'lens-config');
        }
    }

    /**
     * Haak Lens in op Laravels exception handler, zodat gerapporteerde
     * exceptions automatisch in de Lens-app verschijnen (zoals Ray).
     */
    protected function registerExceptionHandler(): void
    {
        try {
            $handler = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);

            if (method_exists($handler, 'reportable')) {
                $handler->reportable(function (\Throwable $e) {
                    Lens::exception($e);
                });
            }
        } catch (\Throwable $e) {
            // Stil negeren: een debug-tool mag de app nooit breken.
        }
    }
}
