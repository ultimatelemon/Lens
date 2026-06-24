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

    /*
     | Stream every database query to Lens. Handy while debugging, noisy if
     | left on permanently — toggle per environment.
     */
    'queries' => env('LENS_QUERIES', false),

    /*
     | Extra Laravel streams. Toggle per environment — handy while debugging,
     | noisy if left on permanently.
     */
    'mails'  => env('LENS_MAILS', false),
    'jobs'   => env('LENS_JOBS', false),
    'events' => env('LENS_EVENTS', false),
    'models' => env('LENS_MODELS', false),

];
