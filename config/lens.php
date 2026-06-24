<?php

return [

    /*
     | Zet Lens aan of uit. Handig om in productie volledig uit te schakelen.
     */
    'enabled' => env('LENS_ENABLED', true),

    /*
     | Host + poort waar de Lens desktop-app op luistert.
     */
    'host' => env('LENS_HOST', '127.0.0.1'),
    'port' => env('LENS_PORT', 23600),

    /*
     | Vang Laravel-exceptions automatisch op en stuur ze naar Lens.
     | Zet op false als je alleen handmatig wilt loggen.
     */
    'catch_exceptions' => env('LENS_CATCH_EXCEPTIONS', true),

];
