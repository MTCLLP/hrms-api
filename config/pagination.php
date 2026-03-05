<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Pagination Settings
    |--------------------------------------------------------------------------
    |
    | This file is for storing the default pagination configuration for your
    | application. You can use this value across all controllers instead of
    | hardcoding the per-page limit everywhere.
    |
    | You can also override this globally by setting an environment variable.
    |
    */

    'per_page' => env('PAGINATION_PER_PAGE', 15),

];
