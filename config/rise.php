<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Photo storage disk
    |--------------------------------------------------------------------------
    |
    | Resident photos are written to this disk and only ever streamed back
    | through an authenticated, owner-only route. Keep it private: the
    | default "local" disk lives in storage/app/private, outside public/.
    |
    */

    'photo_disk' => env('RISE_PHOTO_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Upload size limit (kilobytes)
    |--------------------------------------------------------------------------
    |
    | The limit residents see is the lower of this value and PHP's own
    | upload_max_filesize and post_max_size settings.
    |
    */

    'max_upload_kb' => (int) env('RISE_MAX_UPLOAD_KB', 10240),

    /*
    |--------------------------------------------------------------------------
    | HEIC / HEIF conversion
    |--------------------------------------------------------------------------
    |
    | HEIC photos from Macs and iPhones are accepted when Imagick can decode
    | them; RISE keeps the original privately and shows a JPEG copy. Set to
    | false to refuse HEIC uploads with advice to export them as JPEG.
    |
    */

    'heic_conversion' => (bool) env('RISE_HEIC_CONVERSION', true),

    /*
    |--------------------------------------------------------------------------
    | Crack-image analysis provider
    |--------------------------------------------------------------------------
    |
    | Which CrackDetector implementation runs when a resident asks for an AI
    | analysis. Provider credentials live in config/services.php.
    |
    */

    'analysis_provider' => env('RISE_ANALYSIS_PROVIDER', 'roboflow'),

];
