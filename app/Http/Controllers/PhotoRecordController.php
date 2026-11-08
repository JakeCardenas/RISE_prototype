<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePhotoRecordRequest;
use App\Models\PhotoRecord;
use App\Services\Analysis\CrackDetector;
use App\Services\Photos\PhotoProcessor;
use App\Support\FollowUps;
use App\Support\Observations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PhotoRecordController extends Controller
{
    public function __construct(private readonly PhotoProcessor $photos) {}

    // CREATE — show the photo form; a resident needs a monitoring point first
    public function create(Request $request, CrackDetector $detector)
    {
        $points = $request->user()->monitoringPoints()->with('latestRecord', 'baselineRecord')->withCount('photoRecords')->orderBy('label')->get();

        if ($points->isEmpty()) {
            return redirect()->route('points.create')
                ->with('info', 'First, give the spot a short name.');
        }

        // Only the resident's own points can be preselected
        $selected = $points->firstWhere('id', old('monitoring_point_id', $request->query('point')))
            ?? ($points->count() === 1 ? $points->first() : null);

        return view('records.create', [
            'points' => $points,
            'selected' => $selected,
            'analysisReady' => $detector->isConfigured(),
            'provider' => $detector->name(),
        ]);
    }

    // CREATE — store the original privately, add a JPEG copy when needed, then save the record
    public function store(StorePhotoRecordRequest $request)
    {
        $user = $request->user();
        $point = $user->monitoringPoints()->findOrFail($request->validated('monitoring_point_id'));
        // Checked before saving: the first photo of a spot is its baseline, so it can't complete a planned follow-up
        $hadBaseline = $point->photoRecords()->exists();
        $photo = $request->file('photo');
        $mime = $photo->getMimeType();
        $disk = config('rise.photo_disk');
        $folder = "photo-records/{$user->id}/{$point->id}";

        // The server chooses the folder and a random file name; nothing the browser sends becomes part of the path
        $path = $photo->store($folder, $disk);

        if ($path === false) {
            return $this->rejected($request, 'Your photo couldn’t be saved. Please try again.');
        }

        // HEIC photos and photos carrying location data get a private, upright JPEG copy for viewing and analysis
        $reason = $this->photos->displayCopyReason($photo->getRealPath(), $mime);

        try {
            $copy = $reason ? $this->photos->displayCopy((string) file_get_contents($photo->getRealPath())) : null;
        } catch (Throwable) {
            Storage::disk($disk)->delete($path);

            return $this->rejected($request, $this->photos->isHeic($mime)
                ? 'This HEIC photo couldn’t be converted. On a Mac, open it in Photos, choose File › Export › Export 1 Photo, pick JPEG, then add the exported file.'
                : 'This photo couldn’t be prepared for viewing. Please try a different photo.');
        }

        $displayPath = $copy ? $folder.'/'.Str::random(40).'.jpg' : null;
        [$width, $height] = $this->photos->dimensions($photo->getRealPath(), $mime);
        [$takenAt, $takenOffset] = $this->photos->takenAt($photo->getRealPath(), $mime);

        try {
            if ($copy) {
                Storage::disk($disk)->put($displayPath, $copy->bytes);
            }

            $record = new PhotoRecord($request->safe()->only(['note']));
            $record->forceFill([
                'monitoring_point_id' => $point->id,
                'user_id' => $user->id,
                'image_disk' => $disk,
                'image_path' => $path,
                'image_mime' => $mime,
                'image_size' => $photo->getSize(),
                'image_width' => $width,
                'image_height' => $height,
                'display_path' => $displayPath,
                'display_mime' => $copy ? 'image/jpeg' : null,
                'display_width' => $copy?->width,
                'display_height' => $copy?->height,
                'display_reason' => $reason,
                'photo_taken_at' => $takenAt,
                'photo_taken_offset' => $takenOffset,
                'submitted_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            Storage::disk($disk)->delete(array_filter([$path, $displayPath]));

            throw $e;
        }

        // A follow-up photo saved once the planned day has arrived completes it; a date still ahead is kept
        $plannedFor = FollowUps::completedBy($point, $hadBaseline, FollowUps::today($request)) ? $point->follow_up_on : null;

        if ($plannedFor) {
            $point->update(['follow_up_on' => null]);
        }

        $request->session()->flash('success', match ($reason) {
            'converted_from_heic' => 'Photo saved to “'.$point->label.'”. RISE converted this HEIC photo to JPEG for viewing and kept the original privately.',
            'location_removed' => 'Photo saved to “'.$point->label.'”. Location details stored in the photo file aren’t shown in RISE.',
            default => 'Photo saved to “'.$point->label.'”.',
        }.($plannedFor ? ' That completes the follow-up you planned for '.$plannedFor->format('j F').'; you can plan the next one in its passport.' : ''));
        // Straight to the new photo, on whichever page of the history holds it
        $redirect = Observations::entryUrl($point, $record, Observations::position($record));

        return $request->expectsJson()
            ? response()->json(['redirect' => $redirect], 201)
            : redirect($redirect);
    }

    // DELETE — remove one record, its photo files and its analyses
    public function destroy(PhotoRecord $record)
    {
        Gate::authorize('delete', $record);

        $point = $record->monitoringPoint;
        $record->delete();

        return redirect()->route('points.show', $point)->with('success', 'Photo deleted.');
    }

    private function rejected(Request $request, string $message)
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message, 'errors' => ['photo' => [$message]]], 422)
            : back()->withErrors(['photo' => $message])->withInput();
    }
}
