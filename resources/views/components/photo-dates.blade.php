@props(['record', 'stamped' => false])

{{--
    The dates kept with a photo, as rows of a <dl>: when RISE received it (the authoritative date), when the photo's own
    EXIF details say it was taken, and, only for photos saved before RISE read that, when the file was last modified.
    The last two come from the file and are unverified. A "taken" time is the camera clock's, so it isn't converted
    to the viewer's time zone.
--}}
<dt class="text-muted">Saved to RISE</dt>
<dd class="font-medium"><x-local-time :time="$record->submitted_at" :format="$stamped ? 'stamped' : 'datetime'" /></dd>

<dt class="text-muted">Photo taken</dt>
<dd class="flex flex-wrap items-center gap-x-2">
    @if ($record->photo_taken_at)
        <time datetime="{{ $record->photo_taken_at->format('Y-m-d\TH:i:s').($record->photo_taken_offset ?? '') }}"
            data-datetime="{{ $record->photo_taken_at->format('Y-m-d\TH:i:s') }}Z" data-local-time="wall" class="font-medium">{{ $record->photo_taken_at->format('j M Y, H:i') }}</time>
        <span class="text-muted">{{ $record->photo_taken_offset ? 'UTC'.$record->photo_taken_offset : 'camera’s local time' }}</span>
        <span class="tag">Unverified</span>
    @else
        <span class="text-muted">Not provided</span>
    @endif
</dd>

@if ($record->file_modified_at)
    <dt class="text-muted">File modified</dt>
    <dd class="flex flex-wrap items-center gap-x-2">
        <x-local-time :time="$record->file_modified_at" :format="$stamped ? 'stamped' : 'datetime'" class="font-medium" />
        <span class="tag">Unverified</span>
    </dd>
@endif
