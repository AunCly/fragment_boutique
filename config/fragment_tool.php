<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | Limits checked before the image is fully decoded (protects against
    | decompression bombs).
    |
    */

    'max_upload_kb' => (int) env('FRAGMENT_MAX_UPLOAD_KB', 20 * 1024),

    'max_pixels' => (int) env('FRAGMENT_MAX_PIXELS', 40_000_000),

    /*
    | Long side (in pixels) the cropped area is scaled down to before the
    | classic reduction, to keep the PHP pixel loops fast.
    */
    'working_long_side' => 768,

    /*
    |--------------------------------------------------------------------------
    | Default essences
    |--------------------------------------------------------------------------
    |
    | Wood texture slugs. When the chosen palette does not contain the slug,
    | the tool falls back to the lightest (background, halo) or a mid-tone
    | (motif) essence of the palette.
    |
    */

    'default_background_slug' => 'hetre',

    'halo_slug' => 'sycomore',

    'default_motif_slug' => 'poirier_alisier',

    /*
    |--------------------------------------------------------------------------
    | Renders
    |--------------------------------------------------------------------------
    */

    'flat_cell_px' => 32,

    'wood_long_side_px' => 1536,

    'plan_cell_px' => 40,

    'font_path' => base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf'),

    'bold_font_path' => base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf'),

    /*
    |--------------------------------------------------------------------------
    | AI redraw (Gemini Interactions API)
    |--------------------------------------------------------------------------
    */

    'ai' => [
        'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/interactions',
        'api_revision' => '2026-05-20',
        'image_model' => env('FRAGMENT_AI_MODEL', 'gemini-nano-banana-2.1'),
        'judge_model' => env('FRAGMENT_AI_JUDGE_MODEL', 'gemini-3.1-flash-lite'),
        'suggest_model' => env('FRAGMENT_AI_SUGGEST_MODEL', 'gemini-3.6-flash'),
        'timeout' => 120,

        /*
        | Long side the model images are scaled to before projection (memory bound:
        | pixels are read into PHP arrays).
        */
        'projection_long_side' => 640,

        'counts' => [1, 2, 4, 6, 8],
        'hint_max_length' => 500,
        'max_suggestions' => 15,
        'max_problems' => 4,

        /*
        | Test mode (no Gemini call, no cost): pause before each simulated answer,
        | so the progress popup can be seen.
        */
        'test_mode_delay_ms' => (int) env('FRAGMENT_AI_TEST_DELAY_MS', 1500),

        /*
        | USD per million tokens. Values to check against Google's pricing page
        | (spec: ≈ 0.08 $ per generated image, a few thousandths per review).
        */
        'prices_per_m' => [
            'gemini-nano-banana-2.1' => ['input' => 0.50, 'output' => 3.00, 'image' => 60.00],
            'gemini-3.1-flash-lite' => ['input' => 0.10, 'output' => 0.40, 'image' => 0.0],
            'gemini-3.6-flash' => ['input' => 0.30, 'output' => 2.50, 'image' => 0.0],
        ],
    ],

];
