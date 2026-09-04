<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Board key
    |--------------------------------------------------------------------------
    |
    | The app lives at /{key}. Set this to something long and random in the
    | environment - it is the only thing standing between the scoreboard and
    | anyone who guesses the domain. It is not a password, it is an
    | unguessable address, which for a two-person household scoreboard is
    | the right amount of security.
    |
    */
    'key' => env('BOARD_KEY', 'scores'),

    /*
    |--------------------------------------------------------------------------
    | Optional PIN
    |--------------------------------------------------------------------------
    |
    | Leave BOARD_PIN unset and there is no PIN at all. Set it to four or more
    | digits and each device is asked once, then remembered for a year.
    |
    */
    'pin' => env('BOARD_PIN'),

    /*
    |--------------------------------------------------------------------------
    | Display
    |--------------------------------------------------------------------------
    */
    'name' => env('BOARD_NAME', 'Sue v Lee'),
];
