<?php

use UltimateLemon\Lens\Lens;

if (! function_exists('lens')) {
    /**
     * Stuur een of meer waarden naar de Lens desktop-app.
     * Chainbaar: lens('hallo')->color('red')->label('Test')
     */
    function lens(mixed ...$args): Lens
    {
        return new Lens($args);
    }
}
