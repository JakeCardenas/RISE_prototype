<?php

namespace App\Http\Controllers;

use App\Models\PhotoRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PhotoRecordImageController extends Controller
{
    // READ — stream a private photo to its owner only; HEIC originals and photos with location data are shown as their JPEG copy
    public function __invoke(PhotoRecord $record)
    {
        Gate::authorize('view', $record);

        $disk = Storage::disk($record->image_disk);

        abort_unless($disk->exists($record->viewPath()), 404);

        return $disk->response($record->viewPath(), null, [
            'Content-Type' => $record->viewMime(),
            // Kept briefly by this browser only, never by shared caches
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
