# Lens (PHP-client)

Stuur debug-payloads vanuit je PHP- of Laravel-project live naar de **Lens desktop-app**.
In plaats van `dd()` of `var_dump()` je response te laten vervuilen, stuur je met `lens(...)`
netjes weergegeven data naar een apart venster — met syntax-highlighting, labels, kleuren en
de regel waar het vandaan komt.

```php
lens('hallo');
lens($gebruiker)->color('green')->label('Ingelogde user');
lens(['order' => $order, 'totaal' => $bedrag]);
```

## Vereisten

- PHP 8.0 of hoger (met `ext-curl` en `ext-json`)
- De **Lens desktop-app** moet draaien — die ontvangt en toont de payloads.
  (Aparte applicatie; standaard luistert die op `127.0.0.1:23600`.)

## Installatie

Installeer als **dev-dependency** (het is een debug-tool, net als `dd()`):

```bash
composer require ultimatelemon/lens --dev
```

In Laravel wordt de package automatisch ontdekt (auto-discovery). Verder niets nodig.

> **Let op:** omdat dit een dev-dependency is, bestaat de `lens()`-helper in productie niet
> (`composer install --no-dev`). Laat dus geen `lens()`-aanroepen achter in code die naar
> productie gaat — behandel het als `dd()`. Zie [Voorkom commits met lens()](#voorkom-commits-met-lens).

## Gebruik

De globale helper `lens()` is overal beschikbaar:

```php
// Eenvoudige waarde
lens('checkpoint bereikt');

// Meerdere waarden in één keer
lens($request->all(), $user, $totaal);

// Chainen: label en kleur
lens($order)->label('Nieuwe order')->color('green');

// Scherm leegmaken
\UltimateLemon\Lens\Lens::clear();
```

Beschikbare kleuren: `red`, `green`, `blue`, `orange`, `purple`, `gray`.

## Exceptions

Exceptions verschijnen als rood item met uitklapbare stacktrace:

```php
lens($exception);                          // herkent een Throwable automatisch
\UltimateLemon\Lens\Lens::exception($e);   // expliciet
```

In Laravel worden gerapporteerde exceptions **automatisch** naar Lens gestuurd. Uitschakelen kan via:

```env
LENS_CATCH_EXCEPTIONS=false
```

## Artisan-commands

```bash
php artisan lens:test           # stuurt een testpayload naar de Lens-app
php artisan lens:check          # scant op achtergebleven lens()-aanroepen
php artisan lens:check --staged # alleen de staged bestanden (voor pre-commit)
php artisan lens:install-hooks  # installeert een git pre-commit hook
```

## Voorkom commits met lens()

`lens:check` zoekt naar achtergebleven `lens()`-aanroepen en geeft exit-code 1 als die er zijn
(handig in CI). Een git pre-commit hook blokkeert dan automatisch elke commit met een `lens()`-aanroep.

### Automatisch (aanbevolen)

De package installeert de pre-commit hook **vanzelf** bij `composer install`/`update` — maar
Composer vereist hiervoor eenmalig je toestemming. Zet dit in de `composer.json` van je project:

```json
"config": {
  "allow-plugins": {
    "ultimatelemon/lens": true
  }
}
```

De hook wordt alleen geïnstalleerd:
- in **dev** (nooit bij `composer install --no-dev` / productie / CI-deploy);
- in een **Laravel-project** (er moet een `artisan`-bestand zijn);
- als er een `.git`-map is en er nog **geen** pre-commit hook bestaat (een bestaande hook wordt nooit overschreven).

### Handmatig

```bash
php artisan lens:install-hooks
```

Bestaat er al een pre-commit hook, gebruik dan `--force` of voeg zelf deze regel toe:

```sh
php artisan lens:check --staged || exit 1
```

## Configuratie

### Laravel

Publiceer eventueel het config-bestand:

```bash
php artisan vendor:publish --tag=lens-config
```

Of stuur alles via je `.env`:

```env
LENS_ENABLED=true
LENS_HOST=127.0.0.1
LENS_PORT=23600
```

### Productie uitschakelen

Zet in productie simpelweg:

```env
LENS_ENABLED=false
```

Dan worden alle `lens()`-aanroepen no-ops — geen netwerkverkeer, geen overhead die je app raakt.

### Zonder Laravel (plain PHP)

```php
require __DIR__ . '/vendor/autoload.php';

use UltimateLemon\Lens\Lens;

Lens::configure('127.0.0.1', 23600); // optioneel; dit zijn de defaults
lens('werkt ook zonder framework');
```

## Hoe het werkt

`lens()` bouwt een JSON-payload en doet een korte HTTP POST naar de Lens desktop-app
(`http://LENS_HOST:LENS_PORT`). Mislukt dat (app niet open, time-out) dan wordt de fout
stil genegeerd — debuggen mag je applicatie nooit breken.

## Licentie

MIT — zie [LICENSE](LICENSE).
