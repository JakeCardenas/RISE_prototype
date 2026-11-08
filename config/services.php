<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // RISE crack-image analysis (see README.md). The key stays on the server and is never sent to browsers.
    'roboflow' => [
        'api_url' => env('ROBOFLOW_API_URL') ?: 'https://serverless.roboflow.com',
        'api_key' => env('ROBOFLOW_API_KEY'),
        'model_id' => env('ROBOFLOW_MODEL_ID'),
        'confidence' => (float) (env('ROBOFLOW_CONFIDENCE') ?: 0.4),
        'timeout' => (int) (env('ROBOFLOW_TIMEOUT') ?: 20),
        'max_image_side' => (int) (env('ROBOFLOW_MAX_IMAGE_SIDE') ?: 1280),
    ],

    // Optional AI comparison of two photos. Off until all of these are set: Roboflow's crack model looks at one photo at
    // a time, so this needs a vision model that accepts two images, behind an OpenAI-compatible chat completions API.
    'photo_comparison' => [
        'driver' => env('RISE_COMPARISON_DRIVER'),                // "openai_compatible" turns it on
        'provider_name' => env('RISE_COMPARISON_PROVIDER_NAME'),  // shown to residents before anything is sent
        'api_url' => env('RISE_COMPARISON_API_URL'),
        'api_key' => env('RISE_COMPARISON_API_KEY'),
        'model' => env('RISE_COMPARISON_MODEL'),
        'timeout' => (int) (env('RISE_COMPARISON_TIMEOUT') ?: 45),
        'max_image_side' => (int) (env('RISE_COMPARISON_MAX_IMAGE_SIDE') ?: 1024),
    ],

];
