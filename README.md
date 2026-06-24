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

```bash
composer require ultimatelemon/lens
```

In Laravel wordt de package automatisch ontdekt (auto-discovery). Verder niets nodig.

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
