<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contact Form Recipients
    |--------------------------------------------------------------------------
    |
    | Email addresses that receive the messages sent through the contact form.
    | Set CONTACT_RECIPIENTS in the .env file as a comma-separated list, e.g.
    | CONTACT_RECIPIENTS="first@example.com,second@example.com".
    |
    */

    'recipients' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CONTACT_RECIPIENTS', 'aurelien.clugery@gmail.com'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Anti-bot Protection
    |--------------------------------------------------------------------------
    |
    | Minimum number of seconds between the display of the form and its
    | submission. Faster submissions are considered to come from a bot.
    |
    */

    'minimum_submit_seconds' => (int) env('CONTACT_MINIMUM_SUBMIT_SECONDS', 3),

];
