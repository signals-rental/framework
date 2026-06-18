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

    /*
    |--------------------------------------------------------------------------
    | Signals Cloud
    |--------------------------------------------------------------------------
    |
    | When true, the application is running on Signals Cloud (multi-tenant
    | SaaS). Tenant resolution middleware will extract the subdomain and
    | switch the database connection accordingly. Self-hosted installations
    | should leave this false.
    |
    */

    'cloud' => env('SIGNALS_CLOUD', false),

    'cloud_domain' => env('SIGNALS_CLOUD_DOMAIN', 'signals.cloud'),

];
