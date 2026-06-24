<?php

namespace UltimateLemon\Lens\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

class LensPlugin implements PluginInterface, EventSubscriberInterface
{
    public function activate(Composer $composer, IOInterface $io): void {}
    public function deactivate(Composer $composer, IOInterface $io): void {}
    public function uninstall(Composer $composer, IOInterface $io): void {}

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'installHook',
            ScriptEvents::POST_UPDATE_CMD => 'installHook',
        ];
    }

    public function installHook(Event $event): void
    {
        // Only for dev installs — never on `composer install --no-dev` (CI/production).
        if (! $event->isDevMode()) {
            return;
        }

        $root = getcwd();

        // Only inside a Laravel project (otherwise `php artisan` in the hook is meaningless).
        if (! is_file($root . '/artisan')) {
            return;
        }

        // Only when this really is a git repository.
        if (! is_dir($root . '/.git')) {
            return;
        }

        $hooksDir = $root . '/.git/hooks';
        if (! is_dir($hooksDir)) {
            @mkdir($hooksDir, 0755, true);
        }

        $hookPath = $hooksDir . '/pre-commit';

        if (is_file($hookPath)) {
            $existing = (string) @file_get_contents($hookPath);
            if (str_contains($existing, 'lens:check')) {
                return; // already installed — idempotent
            }

            // Never clobber a foreign pre-commit hook.
            $event->getIO()->writeError(
                '<comment>Lens: bestaande pre-commit hook gevonden, niet aangepast. '
                . 'Voeg zelf toe: php artisan lens:check --staged</comment>'
            );
            return;
        }

        $script = "#!/bin/sh\n"
            . "# Lens — block commits that contain leftover lens() debug calls\n"
            . "php artisan lens:check --staged\n";

        if (@file_put_contents($hookPath, $script) !== false) {
            @chmod($hookPath, 0755);
            $event->getIO()->write('<info>Lens: pre-commit hook geïnstalleerd (.git/hooks/pre-commit)</info>');
        }
    }
}
