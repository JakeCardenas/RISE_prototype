# RISE — residential crack photo records

RISE helps residents keep a private, dated photo record of visible cracks and surfaces at home. A resident names a
*monitoring point* (one repeatable spot, such as “Bedroom wall beside the window”), photographs it over time, reviews
the dated history, compares two photos side by side, and can optionally ask an image model to mark possible
crack-like areas. It is a software-only workflow: no sensors, devices or hardware.

Laravel 13 · PHP 8.3+ · SQLite · Blade, Vite and Tailwind CSS

## Running it locally

Requirements: PHP 8.3+ with GD (Imagick with HEIC support is recommended, see below), Composer, and Node 20+.

    composer run setup     # first time: installs packages, creates .env and the database, builds assets
    composer run dev       # web server, queue worker, logs and Vite together

Open http://localhost:8000 and register an account.

- **Upload limit.** `RISE_MAX_UPLOAD_KB` (default 10240, i.e. 10 MB) is capped by PHP's `upload_max_filesize` and
  `post_max_size`. The Add photo page always shows the limit actually in effect. PHP's defaults (2 MB per file)
  are too small for most phone photos; raise them in `php.ini` (for example `upload_max_filesize=12M`,
  `post_max_size=16M`) and restart the server.
- **Queue.** AI analysis runs as a queued job (`QUEUE_CONNECTION=database`). `composer run dev` starts a worker;
  otherwise run `php artisan queue:work`. Without a worker, a requested analysis stays “waiting” and the page says so.
  Saving a photo never waits for analysis. Because the worker and the web server share the SQLite file, the
  SQLite connection uses WAL journaling and `IMMEDIATE` transactions (`DB_JOURNAL_MODE`, `DB_TRANSACTION_MODE`);
  with SQLite's defaults the worker can fail to claim a job with “database is locked”.

## Photos, privacy and conversion

- Photos are stored on the private `local` disk (`storage/app/private`, configurable with `RISE_PHOTO_DISK`) and
  are only streamed to their owner through an authenticated route. Other residents get “not found” for every
  point, photo, comparison and analysis URL. RISE never creates public or signed image links.
- **HEIC/HEIF** photos from Macs and iPhones are decoded with the Imagick PHP extension (ImageMagick with libheif).
  RISE keeps the original HEIC file privately and stores an upright JPEG copy for viewing and analysis; HEIC bytes
  are never relabelled as JPEG. Check support with `php -r 'print_r(Imagick::queryFormats("HEIC"));'`. Where it
  isn't available (or `RISE_HEIC_CONVERSION=false`), HEIC uploads are refused with instructions to export a JPEG
  (Photos › File › Export › Export 1 Photo › JPEG).
- **Location data.** If a photo file contains GPS details (EXIF or XMP), RISE shows a JPEG copy with all embedded
  metadata removed and keeps the original privately; the original is not served. Photos without location data
  are shown exactly as uploaded.
- **Photo dates.** `submitted_at` (“Saved to RISE”) is when the server received the photo and is the date RISE
  relies on. `photo_taken_at` (“Photo taken”) is read on upload from the file's EXIF `DateTimeOriginal`, with
  `photo_taken_offset` from `OffsetTimeOriginal` when present; it is the camera clock's time, shown as-is and labelled
  unverified, and left blank (“Not provided”) when the file has no readable date. The browser no longer sends a date.
  Photos saved before this change kept the value the old form sent, which was the file's last-modified time; the
  migration renames that column to `file_modified_at` without changing its values, and it is shown as “File modified”.
- **Printable report.** Each point's report lists its photos oldest first in parts of 20 (`?part=2` and so on). Every
  part states which photos and dates it covers, out of how many in total.
- **Sign-in limits.** Login submissions are limited to 5 a minute per email address and IP, and 20 a minute per IP;
  registration to 3 a minute and 20 an hour per IP. Limits are defined in `AppServiceProvider`. Behind a proxy or load
  balancer, configure trusted proxies so the real client IP is used.

## AI crack-image analysis

Analysis is optional and never automatic: nothing is sent to a provider until the photo's owner presses
**Analyze with AI**, next to an explanation of what is sent. Missing credentials give an honest “AI analysis isn't
set up” state; RISE never shows a placeholder or sample detection.

### Setup (Roboflow Serverless API)

1. Create a Roboflow account and copy a private API key.
2. Set these in `.env` (never commit a real key; the key is only used on the server):

   | Variable | Purpose | Default |
   |---|---|---|
   | `ROBOFLOW_API_KEY` | Private API key | — (required) |
   | `ROBOFLOW_MODEL_ID` | Model as `project/version`, e.g. `concrete-crack-ver.1/2` | — (required) |
   | `ROBOFLOW_API_URL` | Inference host | `https://serverless.roboflow.com` |
   | `ROBOFLOW_CONFIDENCE` | Score threshold sent to the model | `0.4` |
   | `ROBOFLOW_TIMEOUT` | Seconds per request | `20` |
   | `ROBOFLOW_MAX_IMAGE_SIDE` | Longest side of the copy sent, in pixels | `1280` |
   | `RISE_ANALYSIS_PROVIDER` | Which `CrackDetector` to use | `roboflow` |

3. Run `php artisan config:clear` if config is cached, and restart the queue worker.

### What happens when a resident asks for analysis

- A `photo_analyses` row is created (`pending`) and the `AnalyzePhoto` job is queued.
- The job builds an in-memory JPEG copy (no temporary files): upright, at most `ROBOFLOW_MAX_IMAGE_SIDE` pixels,
  with all embedded metadata, including location, removed. Roboflow documents a 20 MB upload limit and downsizes
  images to the model's input size anyway, so the smaller copy doesn't reduce quality in practice.
- It calls `POST {ROBOFLOW_API_URL}/{project}/{version}` with the base64 image as the body and the query parameters
  `api_key`, `confidence`, `response_mask_format=polygon` and `disable_active_learning=true` (so Roboflow doesn't add
  the photo to a training dataset). Rate limits (429), server errors (5xx) and dropped connections are retried up
  to three attempts in total with 1 s and 3 s waits; other errors are not retried. The job's 75-second timeout
  stays below the database queue's 90-second retry window.
- The response is validated and normalized before anything is stored: at most 100 regions, each with a short
  label, a model score, a box and (when returned) the model's polygon outline, all as fractions of the image.
  Only boxes are drawn when the model returns no outline. Raw responses are not stored.
- The row records the provider, the requested model ID, the model version the provider reports, its request ID,
  the threshold, timestamps and, on failure, a safe error code (`invalid_credentials`, `model_not_found`,
  `rate_limited`, `provider_unavailable`, `provider_timeout`, `unsupported_image`, …). Exception messages are never
  logged because they can contain the request URL and key. The owner can retry a failed analysis.

RISE can't promise how long Roboflow keeps images it receives; that is governed by Roboflow's own terms and privacy
policy. If that matters for a deployment, use a self-hosted or dedicated inference server and point
`ROBOFLOW_API_URL` at it.

To use another provider, or a model the team trains on its own labelled images, implement
`App\Services\Analysis\CrackDetector`, bind it in `AppServiceProvider`, and select it with `RISE_ANALYSIS_PROVIDER`.

## Optional AI photo comparison

On the Compare page, a resident can ask an AI model to describe possible differences between the two photos on
screen: possible visible differences, and possible differences in how they were taken (angle, distance, framing,
light). It's off until it's set up, never runs on its own, and never saves its wording as the resident's note.
The resident can dismiss it, copy it, or edit it into their own note and save that themselves.

The Roboflow integration above can't do this: it sends one photo to a single-image crack model and gets back marked
areas, with no way to compare two photos or describe them. So the comparison needs a vision model that accepts two
images. RISE talks to it through an OpenAI-compatible chat completions API, so it can be a hosted service or a model
running on your own server. Nothing is chosen for you; until the variables below are set, residents see "AI comparison
isn't set up" and nothing is sent.

### Setup

| Variable | Purpose | Default |
|---|---|---|
| `RISE_COMPARISON_DRIVER` | Set to `openai_compatible` to turn the feature on | — (off) |
| `RISE_COMPARISON_PROVIDER_NAME` | The provider's name, shown to residents before they send anything | — (required) |
| `RISE_COMPARISON_API_URL` | Base URL of the API, ending before `/chat/completions` | — (required) |
| `RISE_COMPARISON_API_KEY` | The provider's API key, if it needs one (server only; never commit it) | — |
| `RISE_COMPARISON_MODEL` | A vision model that accepts two images and can reply in JSON | — (required) |
| `RISE_COMPARISON_TIMEOUT` | Seconds per request | `45` |
| `RISE_COMPARISON_MAX_IMAGE_SIDE` | Longest side of each copy sent, in pixels | `1024` |

Then run `php artisan config:clear` if config is cached, and restart the queue worker. A hosted provider may charge
per request; check its pricing and data terms before turning it on.

### What is sent, and what residents see

- Before the button, residents see who receives the photos, what is sent (smaller copies of the two photos with
  location and other embedded details removed) and what isn't (notes, spot names, account details).
- The `ComparePhotos` job builds both copies in memory the same way as the crack analysis: upright, resized and
  stripped of all metadata. The originals are never sent. The request holds a fixed instruction and the two images.
- The instruction asks for short, cautious sentences and forbids measurements, causes, severity, repair advice and
  safety judgments. As a second guard, any sentence that mentions sizes, causes, severity, risk, repairs,
  inspections or safety is dropped before it's stored or shown. Raw responses are not stored.
- Results are kept per pair of photos, owner-only, and removed with either photo. Failures are stored as safe error
  codes, as with the crack analysis.

## Model note

- **Candidate model:** Concrete Crack Ver.1 by “Concrete Crack” on Roboflow Universe,
  https://universe.roboflow.com/concrete-crack-shg7t/concrete-crack-ver.1
- **Model ID:** `concrete-crack-ver.1/2` · **Task:** instance segmentation · **Architecture:** `roboflow-3-n-seg`
- **License:** MIT · **Dataset:** about 10,000 images · **Classes:** `Crack`, `crack` (as listed on the model page,
  checked 4 November 2026)
- **Provider-reported metrics:** mAP@50 83.0%, precision 82.3%, recall 75.6%. These were measured on that model's own
  evaluation data. They are not RISE results and do not show how it performs on residential photos; do not describe
  RISE as “83% accurate”.
- **Known limitations:** trained on concrete-crack images rather than RISE residents' photos; it can miss cracks and
  can mark joints, texture, shadows, edges or stains. It does not measure width, length or change, and says nothing
  about cause, severity, code compliance or structural safety. Results depend on framing, light and focus. RISE
  presents every marking as a “possible crack-like area detected by AI” for the resident to review.
- **Evaluating it for the capstone:** before claiming any performance for local homes, test it on a separate,
  labelled, adviser-approved set of images. Report precision and recall separately from the capstone's seven
  data-quality criteria, and use the `model_version` stored with each result to trace which model produced it.

## Tests

    php artisan test

Provider calls are faked with `Http::fake()`, so the tests never contact Roboflow. HEIC tests are skipped where
Imagick can't decode HEIC.

## Original notes exercise (ITE 116)

RISE was built on a notes CRUD exercise, which still works at `/notes` for signed-in users; its migrations and data
are kept. It is separate from RISE: nothing in the RISE navigation or workflow links to it, and notes are not part of
a monitoring point's timeline, comparison or report.

| Method | URL                | Controller | Name          |
|--------|--------------------|------------|---------------|
| GET    | /notes             | index      | notes.index   |
| GET    | /notes/create      | create     | notes.create  |
| POST   | /notes             | store      | notes.store   |
| GET    | /notes/{note}/edit | edit       | notes.edit    |
| PUT    | /notes/{note}      | update     | notes.update  |
| DELETE | /notes/{note}      | destroy    | notes.destroy |

| Error                                   | Fix                                              |
|-----------------------------------------|--------------------------------------------------|
| 419 Page Expired                        | add `@csrf` to the form                          |
| 405 Method Not Allowed                  | add `@method('PUT')` or `@method('DELETE')`      |
| Add [title] to fillable property        | list the field in the model's `$fillable`        |
| no such table: notes                    | run `php artisan migrate`                        |
| Route [notes.x] not defined             | check `->name(...)` in `routes/web.php`          |
