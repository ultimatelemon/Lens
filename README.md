# Lens (PHP client)

Send debug payloads from your PHP or Laravel project straight to the **Lens desktop app**.
Instead of polluting your response with `dd()` or `var_dump()`, use `lens(...)` to send neatly
rendered data to a separate window — with syntax highlighting, labels, colors and the line it
came from.

```php
lens('hello');
lens($user)->color('green')->label('Signed-in user');
lens(['order' => $order, 'total' => $amount]);
```

## Requirements

- PHP 8.0 or higher (with `ext-curl` and `ext-json`)
- The **Lens desktop app** must be running — it receives and displays the payloads.
  (Separate application; listens on `127.0.0.1:23600` by default.)

## Installation

Install as a **dev dependency** (it's a debugging tool, just like `dd()`):

```bash
composer require ultimatelemon/lens --dev
```

In Laravel the package is auto-discovered. Nothing else to configure.

> **Note:** because this is a dev dependency, the `lens()` helper does not exist in production
> (`composer install --no-dev`). So don't leave `lens()` calls in code that ships to production —
> treat it like `dd()`. See [Prevent commits with lens()](#prevent-commits-with-lens).

## Usage

The global `lens()` helper is available everywhere:

```php
// A single value
lens('checkpoint reached');

// Multiple values at once
lens($request->all(), $user, $total);

// Chaining: label and color
lens($order)->label('New order')->color('green');

// Clear the screen
\UltimateLemon\Lens\Lens::clear();
```

Available colors: `red`, `green`, `blue`, `orange`, `purple`, `gray`.

## Exceptions

Exceptions show up as a red item with an expandable stack trace:

```php
lens($exception);                          // a Throwable is detected automatically
\UltimateLemon\Lens\Lens::exception($e);   // explicit
```

In Laravel, reported exceptions are sent to Lens **automatically**. Disable it with:

```env
LENS_CATCH_EXCEPTIONS=false
```

## Laravel streams

Stream Laravel internals straight to Lens. Toggle per environment in your `.env`:

```env
LENS_QUERIES=true   # every DB query (SQL + bindings + time)
LENS_MAILS=true     # outgoing mails, with a rendered HTML preview
LENS_JOBS=true      # queue jobs (processing / processed / failed)
LENS_EVENTS=true    # application events (framework noise filtered out)
LENS_MODELS=true    # Eloquent created / updated / deleted / restored
```

Or enable them in code:

```php
\UltimateLemon\Lens\Lens::showQueries();
\UltimateLemon\Lens\Lens::showMails();   // shows the email's HTML in a sandboxed preview
\UltimateLemon\Lens\Lens::showJobs();
\UltimateLemon\Lens\Lens::showEvents();
\UltimateLemon\Lens\Lens::showModels();
```

## Pause execution

Pause your code until you click **Continue** or **Stop** in the Lens app:

```php
\UltimateLemon\Lens\Lens::pause();
```

Returns immediately if Lens is disabled or the app is not running, so it never hangs your app.

## Artisan commands

```bash
php artisan lens:test           # send a test payload to the Lens app
php artisan lens:check          # scan for leftover lens() calls
php artisan lens:check --staged # only staged files (for pre-commit)
php artisan lens:install-hooks  # install a git pre-commit hook
```

## Prevent commits with lens()

`lens:check` scans for leftover `lens()` calls and returns exit code 1 when it finds any
(useful in CI). A git pre-commit hook then automatically blocks any commit containing a `lens()` call.

### Automatic (recommended)

The package installs the pre-commit hook **by itself** on `composer install`/`update` — but Composer
requires your one-time consent for this. Add this to your project's `composer.json`:

```json
"config": {
  "allow-plugins": {
    "ultimatelemon/lens": true
  }
}
```

The hook is only installed:
- in **dev** (never on `composer install --no-dev` / production / CI deploy);
- in a **Laravel project** (an `artisan` file must be present);
- when there is a `.git` directory and **no** pre-commit hook exists yet (an existing hook is never overwritten).

### Manual

```bash
php artisan lens:install-hooks
```

If a pre-commit hook already exists, use `--force` or add this line yourself:

```sh
php artisan lens:check --staged || exit 1
```

## Configuration

### Laravel

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=lens-config
```

Or configure everything through your `.env`:

```env
LENS_ENABLED=true
LENS_HOST=127.0.0.1
LENS_PORT=23600
```

### Disable in production

Simply set:

```env
LENS_ENABLED=false
```

All `lens()` calls then become no-ops — no network traffic, no overhead touching your app.

### Without Laravel (plain PHP)

```php
require __DIR__ . '/vendor/autoload.php';

use UltimateLemon\Lens\Lens;

Lens::configure('127.0.0.1', 23600); // optional; these are the defaults
lens('works without a framework too');
```

## How it works

`lens()` builds a JSON payload and makes a short HTTP POST to the Lens desktop app
(`http://LENS_HOST:LENS_PORT`). If that fails (app not open, timeout) the error is silently
ignored — debugging should never break your application.

## License

MIT — see [LICENSE](LICENSE).
