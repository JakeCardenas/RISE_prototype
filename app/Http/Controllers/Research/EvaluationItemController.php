<?php

namespace App\Http\Controllers\Research;

use App\Http\Controllers\Controller;
use App\Models\EvaluationItem;
use App\Services\Photos\PhotoProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

// The research dataset: images a researcher adds with the authorization for using them, and their human labels.
// Nothing here reads or copies residents' RISE photos.
class EvaluationItemController extends Controller
{
    // READ — every research image, newest first
    public function index()
    {
        return view('research.items', [
            'items' => EvaluationItem::query()->orderByDesc('id')->paginate(30),
        ]);
    }

    // CREATE — up to 20 images at once, all under one stated authorization
    public function store(Request $request, PhotoProcessor $photos)
    {
        $request->validate([
            'images' => ['required', 'array', 'max:20'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:'.config('rise.max_upload_kb')],
            'authorization' => ['required', Rule::in(array_keys(EvaluationItem::AUTHORIZATIONS))],
            'authorization_note' => ['nullable', 'string', 'max:300'],
            'confirm' => ['accepted'],
        ], [
            'images.required' => 'Choose at least one image.',
            'images.max' => 'Add up to 20 images at a time.',
            'images.*.mimes' => 'Use JPEG, PNG or WebP images.',
            'images.*.max' => 'Each image must be under '.round(config('rise.max_upload_kb') / 1024).' MB.',
            'authorization.required' => 'Say what allows you to use these images.',
            'confirm.accepted' => 'Confirm that you’re authorized to use these images.',
        ]);

        $disk = config('rise.photo_disk');
        $added = 0;

        foreach ($request->file('images') as $file) {
            try {
                // Only a re-encoded copy is kept: no location or other embedded details, and not the uploaded file itself
                $copy = $photos->analysisCopy($file->get(), 2048);
            } catch (Throwable $e) {
                Log::info('RISE evaluation image could not be read.', ['exception' => $e::class]);

                continue;
            }

            $path = 'evaluation/'.Str::ulid().'.jpg';
            Storage::disk($disk)->put($path, $copy->bytes);

            (new EvaluationItem)->forceFill([
                'code' => EvaluationItem::nextCode(),
                'image_disk' => $disk,
                'image_path' => $path,
                'width' => $copy->width,
                'height' => $copy->height,
                'authorization' => $request->input('authorization'),
                'authorization_note' => filled($request->input('authorization_note')) ? trim($request->input('authorization_note')) : null,
                'created_by' => $request->user()->id,
            ])->save();
            $added++;
        }

        if ($added === 0) {
            throw ValidationException::withMessages(['images' => 'None of these images could be read. Try JPEG copies.']);
        }

        $skipped = count($request->file('images')) - $added;

        return redirect()->route('research.items.index')->with('success', trans_choice('{1} 1 image added|[2,*] :count images added', $added)
            .($skipped ? ' ('.trans_choice('{1} 1 couldn’t be read|[2,*] :count couldn’t be read', $skipped).')' : '').'. Label each one before running an evaluation.');
    }

    // READ — one image, to label and draw boxes on. Model results are never shown here, so they can't sway the label.
    public function edit(EvaluationItem $item)
    {
        return view('research.item', [
            'item' => $item,
            // The same order as the list: newest first
            'previous' => EvaluationItem::query()->where('id', '>', $item->id)->orderBy('id')->value('id'),
            'next' => EvaluationItem::query()->where('id', '<', $item->id)->orderByDesc('id')->value('id'),
            'nextUnlabelled' => EvaluationItem::query()->whereNull('label')->whereKeyNot($item->id)->orderByDesc('id')->value('id'),
        ]);
    }

    // UPDATE — the human label, conditions, split and drawn boxes
    public function update(Request $request, EvaluationItem $item)
    {
        $data = $request->validate([
            'label' => ['nullable', Rule::in(array_keys(EvaluationItem::LABELS))],
            'blur' => ['nullable', Rule::in(array_keys(EvaluationItem::BLUR))],
            'lighting' => ['nullable', Rule::in(array_keys(EvaluationItem::LIGHTING))],
            'surface' => ['nullable', Rule::in(array_keys(EvaluationItem::SURFACES))],
            'split' => ['required', Rule::in(array_keys(EvaluationItem::SPLITS))],
            'regions' => ['nullable', 'string', 'max:5000'],
        ]);

        $regions = EvaluationItem::regionsFrom($data['regions'] ?? null);

        if ($regions === null) {
            throw ValidationException::withMessages(['regions' => 'The drawn boxes couldn’t be read. Clear them and draw them again.']);
        }

        $labelChanged = ($data['label'] ?? null) !== $item->label;

        $item->forceFill([
            'label' => $data['label'] ?? null,
            // Boxes only mean something on an image labelled crack-like
            'regions' => ($data['label'] ?? null) === 'crack_like' && $regions ? $regions : null,
            'blur' => $data['blur'] ?? null,
            'lighting' => $data['lighting'] ?? null,
            'surface' => $data['surface'] ?? null,
            'split' => $data['split'],
            'labelled_by' => $labelChanged ? $request->user()->id : $item->labelled_by,
            'labelled_at' => $labelChanged ? (isset($data['label']) ? now() : null) : $item->labelled_at,
        ])->save();

        $next = $request->boolean('then_next')
            ? EvaluationItem::query()->whereNull('label')->whereKeyNot($item->id)->orderByDesc('id')->value('id')
            : null;

        return redirect()->route('research.items.edit', $next ?? $item)->with('success', $item->code.' saved.');
    }

    // DELETE — removes the image and its results in finished runs, for example when consent is withdrawn
    public function destroy(EvaluationItem $item)
    {
        Storage::disk($item->image_disk)->delete($item->image_path);
        $item->delete();

        return redirect()->route('research.items.index')->with('success', $item->code.' deleted, with its results in any runs.');
    }

    // READ — the stored research copy, to researchers only
    public function image(EvaluationItem $item)
    {
        $disk = Storage::disk($item->image_disk);

        abort_unless($disk->exists($item->image_path), 404);

        return $disk->response($item->image_path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
