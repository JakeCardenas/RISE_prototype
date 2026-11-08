<?php

namespace App\Http\Requests;

use App\Services\Photos\PhotoProcessor;
use App\Support\PhotoUploads;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePhotoRecordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Only the resident's own monitoring points are valid targets
            'monitoring_point_id' => [
                'required',
                'string',
                Rule::exists('monitoring_points', 'id')->where('user_id', $this->user()->id),
            ],
            'photo' => [
                'bail',
                'required',
                'file',
                'mimes:'.implode(',', [...PhotoUploads::EXTENSIONS, ...PhotoUploads::HEIC_EXTENSIONS]),
                'max:'.PhotoUploads::maxKilobytes(),
                $this->convertibleIfHeic(...),
                $this->largeEnough(...),
            ],
            // No date is accepted from the browser: RISE records when it received the photo, and reads any
            // "taken" date from the photo's own EXIF details (see PhotoProcessor::takenAt)
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        $limit = PhotoUploads::maxLabel();

        return [
            'monitoring_point_id.required' => 'Choose the monitoring point this photo belongs to.',
            'monitoring_point_id.exists' => 'Choose one of your monitoring points.',
            'photo.required' => 'Take or choose a photo to save.',
            'photo.file' => 'Take or choose a photo to save.',
            'photo.uploaded' => "The photo didn’t finish uploading. It may be larger than the {$limit} limit, or the connection dropped. Please try again.",
            'photo.mimes' => 'Use a '.PhotoUploads::formatLabel().' photo.',
            'photo.max' => "This photo is larger than {$limit}. Choose a smaller photo.",
            'note.max' => 'Keep the note under 1,000 characters.',
        ];
    }

    // HEIC/HEIF is only accepted where this server can convert it; otherwise explain how to export a JPEG
    private function convertibleIfHeic(string $attribute, mixed $photo, Closure $fail): void
    {
        $photos = app(PhotoProcessor::class);

        if ($photos->isHeic($photo->getMimeType()) && ! $photos->supportsHeic()) {
            $fail('This server can’t convert HEIC photos. On a Mac, open the photo in Photos, choose File › Export › Export 1 Photo, pick JPEG, then add the exported file.');
        }
    }

    private function largeEnough(string $attribute, mixed $photo, Closure $fail): void
    {
        [$width, $height] = app(PhotoProcessor::class)->dimensions($photo->getRealPath(), $photo->getMimeType());

        if ($width === null || $height === null) {
            $fail('This file can’t be read as a photo. Use a '.PhotoUploads::formatLabel().' photo.');
        } elseif ($width < 200 || $height < 200) {
            $fail('This photo is too small to compare. Use one at least 200 pixels wide and tall.');
        }
    }
}
