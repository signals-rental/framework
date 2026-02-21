<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Installation State
    |--------------------------------------------------------------------------
    |
    | These values track whether the Signals infrastructure and application
    | setup wizards have been completed. They are written to .env by the
    | signals:install and signals:setup commands respectively.
    |
    */

    'installed' => env('SIGNALS_INSTALLED', false),

    'setup_complete' => env('SIGNALS_SETUP_COMPLETE', false),

];
