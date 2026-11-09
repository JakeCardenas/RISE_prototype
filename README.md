# RISE — residential crack photo records

RISE helps residents keep a private, dated photo record of visible cracks and surfaces at home. A resident names a
*monitoring point* (one repeatable spot, such as “Bedroom wall beside the window”), photographs it over time, reviews
the dated history, compares two photos side by side, and can optionally ask an AI model to describe a photo or mark
possible crack-like areas. It is a software-only workflow: no sensors, devices or hardware. RISE is not a structural
assessment tool: it never calls a crack safe, unsafe, good, bad or in need of repair.

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
  Saving a photo never waits for analysis. Comparisons and evaluation runs use the same worker. Because the worker
  and the web server share the SQLite file, the
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
- **Guided capture.** The Add photo page shows the spot's last photo as a framing guide and can fade it over the new
  one. Once a photo is chosen, quick checks in the browser (`resources/js/rise/photo-quality.js`) look for darkness,
  a washed-out or glary photo, possible blur, and, for a follow-up, a different orientation, shape, view or light
  from the baseline. They run on a small copy in the browser, send nothing, give plain advice instead of a score,
  and never stop the resident saving: they can retake the photo or keep it. Their thresholds were set on synthetic
  sample photos with added blur, darkness and glare, so they are a rough guide and can be wrong either way. HEIC
  photos a browser can't draw aren't checked.
- **Printable report.** Each point's report lists its photos oldest first in parts of 20 (`?part=2` and so on). Every
  part states which photos and dates it covers, out of how many in total.
- **Sign-in limits.** Login submissions are limited to 5 a minute per email address and IP, and 20 a minute per IP;
  registration to 3 a minute and 20 an hour per IP. Limits are defined in `AppServiceProvider`. Behind a proxy or load
  balancer, configure trusted proxies so the real client IP is used.

## AI photo analysis

Analysis is optional and never automatic: nothing is sent to a provider until the photo's owner presses
**Analyze with AI**, next to an explanation of what is sent, to whom and under which terms. Missing credentials give
an honest “AI analysis isn't set up” state; RISE never shows a placeholder, a sample result or a fallback.

Two providers are built in, chosen with `RISE_ANALYSIS_PROVIDER` (`gemini` or `roboflow`). Left blank, RISE uses
Gemini once its key and model are set, and Roboflow otherwise.

- **Google Gemini** describes the photo: a bounded finding (`possible_feature`, `no_clear_feature` or `uncertain`),
  up to four short cautious sentences, what limits the photo (blur, darkness, glare, distance, angle…) and one next
  step from a fixed list. It is never asked where anything is, so RISE draws no outline or box for it and shows no
  score.
- **Roboflow** runs a crack segmentation model that marks possible crack-like areas, drawn over the photo with the
  original always one tap away.

### Setup (Google Gemini)

1. Create a Gemini API key in Google AI Studio. Keep it on the server only: put it in `.env`, never in Git or in
   browser code.
2. Choose a current Gemini model that accepts images, from Google's models page, and set both values:

   | Variable | Purpose | Default |
   |---|---|---|
   | `GEMINI_API_KEY` | Private API key | — (required) |
   | `GEMINI_MODEL` | A current model ID that answers your key (check with `php artisan rise:ai-check --ping`) | — (required) |
   | `GEMINI_API_URL` | API base, ending before `/interactions` | `https://generativelanguage.googleapis.com/v1beta` |
   | `GEMINI_API_REVISION` | `Api-Revision` header that pins the request and response shape | `2026-05-20` |
   | `GEMINI_THINKING_LEVEL` | Optional `generation_config.thinking_level`, such as `minimal` or `low`, for faster answers | — (not sent) |
   | `GEMINI_TIMEOUT` | Seconds per request | `45` |
   | `GEMINI_MAX_IMAGE_SIDE` | Longest side of each copy sent, in pixels | `1536` |

3. Run `php artisan config:clear` if config is cached, and restart the queue worker. The same key also turns on the
   two-photo comparison (below) unless `RISE_COMPARISON_DRIVER` says otherwise.
4. Run `php artisan rise:ai-check --ping`. It shows the setup without any secret, names any missing setting, checks
   the time limits, and asks the model for a one-word text reply (no photo) to confirm it answers.

On 9 November 2026, with this project's key, `gemini-3.8-flash` (listed as new on Google's models page) gave no answer
at all within 30, 40, 60 or 90 seconds, even to a one-word text prompt, so every photo analysis timed out.
`gemini-3.6-flash` (listed as stable) answered the same Interactions request in under 2 seconds, and RISE's full photo
and comparison requests in 6 to 8 seconds, using generated test images. Model availability changes, so check with
`--ping` rather than relying on this note.

RISE calls Google's Interactions API, `POST {GEMINI_API_URL}/interactions` with the key in the `x-goog-api-key`
header, following Google's image-understanding and structured-output guides (checked 8 November 2026). The body holds
the model, RISE's fixed instructions, the photo as inline base64 JPEG (`type: image`), a `response_format` with a JSON
schema (`mime_type: application/json`) and `store: false`. The reply's last `model_output` step must be JSON that
follows the schema. This request was first checked against a local mock, then against the live API on 9 November 2026
with generated, non-personal test images (never a resident's photo).

### What Gemini receives, and what RISE can and can't promise

- Sent: one resized JPEG copy, upright, with all embedded details including location removed, plus RISE's fixed
  instructions. Never the original file, the resident's notes, spot or room names, or account details.
- `store: false` asks Google not to keep the interaction for follow-up turns. Google's Interactions API guide says
  interactions are stored by default for that purpose (55 days on the paid tier, 1 day on the free tier, as checked
  8 November 2026). It does not, by itself, settle how Google may otherwise log, review or use requests: that is
  governed by the Gemini API terms for your account, https://ai.google.dev/gemini-api/terms. RISE doesn't claim that
  Google won't keep or train on photos. Read the terms that apply to your account, including whether it is on the
  free tier, before letting residents send photos, and keep the consent screen's wording accurate if you change
  anything.
- Requests are bounded: up to three attempts, only for rate limits (429), server errors (5xx) and dropped
  connections, and only within the first 15 seconds; a request that times out isn't repeated. The job's 75-second
  timeout stays below the database queue's 90-second retry window.

### How every result is checked

Whatever a model returns, RISE stores only what passes its own checks (`App\Services\Analysis\Observation`,
`App\Support\CautiousText`), and checks stored results again when they are shown:

- The finding, each photo-quality value and the next step must be one of RISE's fixed values, or the result is
  rejected as `invalid_response`; nothing is guessed.
- “No clear feature” from a photo the model says is blurry, too dark, glary, obstructed or low in detail becomes
  “uncertain”, with “retake the photo” as the next step. The result page always says that “no clear feature found”
  doesn't prove there is no crack.
- Each sentence is limited in number and length and dropped whole, never shortened, if it gives a measurement,
  cause, judgement of condition (good, bad, normal, safe, unsafe, severe…), claim of growth or improvement, repair,
  replacement or inspection advice, or claimed certainty, or if it calls something a crack without a hedge such as
  “may” or “crack-like”.
- Raw provider responses and exception messages are never stored, shown or logged; failures keep a safe error code.
  Logs hold only an exception's class name, never keys, URLs or photo content.
- Region overlays are drawn only for a provider that returns locations (Roboflow), and only after RISE has checked
  that they refer to the copy it sent: the reported image must have the same shape, boxes are clipped to the photo,
  and boxes with no area are dropped.

Results move through these states: not set up, queued, processing, completed, timed out, failed but worth trying
again, and failed until a setting changes. Each analysis row records the provider, the requested model ID, the model
version the provider reports, its request ID, the request, sending and completion times and the status. Earlier
Roboflow results keep working and are shown as before.

### What residents see while it runs

The waiting panel (`x-ai-progress`, kept live by `resources/js/rise/analysis-status.js`) shows the real stage from the
server: waiting for the RISE queue, sending the photo to the provider (until the request, photo included, has been
fully sent, as recorded in `sent_at`), then waiting for the provider's result. It has a spinner, an indeterminate bar
(never a percentage), the time since the request, and a note that the resident can leave and come back. On phones the
panel comes before the photo, and the photo stays visible and uncropped. With reduced motion the spinner and bar hold
still. Stage changes are announced to screen readers; the clock isn't.

- The page checks the status every 2 seconds at first, then less often, and pauses while the tab is hidden. If a check
  fails, it says the status couldn't be refreshed and keeps trying with a back-off, with a Check now button; it never
  reports that the analysis itself failed. When the request finishes or fails, the page reloads and moves focus to
  the result.
- The Analyze and Try again buttons send once and show "Sending request…". The server also holds a short lock per
  photo (and per photo pair for comparisons), so simultaneous requests start one analysis and one provider charge.
- A request still in the queue after two minutes adds a hint that the queue worker may not be running.

### When an analysis doesn't finish

Start with `php artisan rise:ai-check --ping`. Each failure keeps a safe error code, and each finished or failed
request writes one log line ("RISE photo analysis completed." or "… failed.") with only the provider, model, error
code, HTTP status, duration in milliseconds and whether the photo had been sent; never the key, headers, the photo or
anything the provider sent back.

| Error code | What happened | What to check |
|---|---|---|
| `provider_timeout` | The provider didn't finish within `GEMINI_TIMEOUT` (or sent 504) | Whether the model answers at all (`--ping`); try another current model |
| `provider_unreachable` | The server couldn't connect | The server's internet connection and `GEMINI_API_URL` |
| `invalid_credentials` | The key was refused | `GEMINI_API_KEY` |
| `model_not_found` | The model doesn't exist for this key | `GEMINI_MODEL` |
| `invalid_request` | The request's settings were refused | `GEMINI_API_REVISION`, `GEMINI_THINKING_LEVEL` |
| `unsupported_image`, `image_too_large` | The photo couldn't be read | Try another photo; lower `GEMINI_MAX_IMAGE_SIDE` |
| `rate_limited` | Per-minute limit; retried twice, then given up | Wait a few minutes |
| `provider_quota` | Daily quota used up; not retried | The project's quota or billing in Google AI Studio |
| `provider_unavailable` | Temporary server error (500–503); retried twice | Try again later |
| `invalid_response` | An answer RISE couldn't check against its schema | Try again; check the model |
| `worker_timeout` | The queue worker stopped the job (75 s limit) | The worker (`composer run dev` or `php artisan queue:work`) |

The time limits nest so that each layer gives up before the one around it: a Gemini request has `GEMINI_TIMEOUT` (45 s;
a slow attempt is never repeated, so with retries the longest call is about 63 s), the job has 75 s, and the database
queue retries a job only after 90 s (`retry_after`). Raising `GEMINI_TIMEOUT` doesn't help a model that never answers,
and at 57 s or more it would break this chain; `rise:ai-check` warns when it does.

Residents can also rate a finished result as useful, incorrect or not sure, with an optional note of their own.
Ratings are stored apart from the model's wording and from photo notes (`ai_feedback`), are never sent to a provider
and are never used to train or tune a model. Asking for AI results is rate-limited per account (4 a minute, 30 an
hour, shared between analyses and comparisons); feedback is limited to 20 changes a minute.

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
   | `RISE_ANALYSIS_PROVIDER` | `roboflow` to use it while Gemini is also set up | — (Gemini when set up, else Roboflow) |

3. Run `php artisan config:clear` if config is cached, and restart the queue worker.

### What happens when a resident asks for analysis

- A `photo_analyses` row is created (`pending`) and the `AnalyzePhoto` job is queued. The job uses the provider the
  resident was told about, even if the server's choice changes while it waits.
- The steps below describe Roboflow; Gemini follows the same pattern with its own request (above).
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
`App\Services\Analysis\CrackDetector`, add it to `App\Services\Analysis\AnalysisProviders`, and select it with
`RISE_ANALYSIS_PROVIDER`.

## Optional AI photo comparison

On the Compare page, a resident can ask an AI model to describe possible differences between the two photos on
screen: possible visible differences, and possible differences in how they were taken (angle, distance, framing,
light). It's off until it's set up, never runs on its own, and never saves its wording as the resident's note.
The resident can dismiss it, copy it, or edit it into their own note and save that themselves.

The Roboflow integration above can't do this: it sends one photo to a single-image crack model and gets back marked
areas, with no way to compare two photos or describe them. So the comparison needs a vision model that accepts two
images: Google Gemini (set up as above), or a model behind an OpenAI-compatible chat completions API, hosted or on
your own server. Until one is set up, residents see "AI comparison isn't set up" and nothing is sent.

With Gemini, the model first says whether the two photos can be compared at all (`comparable`, `limited` or
`not_comparable`) and why (different angle, distance, framing or light, blur, something in the way, or a different
area). When they can't be compared, RISE lists no surface differences and explains why instead. It never reports
growth, cause, severity, urgency, safety or repair. The Compare page also has an overlay view, which fades the
later photo over the earlier one, beside the side-by-side and slider views; none of them line photos up or measure
anything.

### Setup

With `GEMINI_API_KEY` and `GEMINI_MODEL` set, the comparison uses Gemini unless `RISE_COMPARISON_DRIVER` names
another driver. For an OpenAI-compatible provider instead:

| Variable | Purpose | Default |
|---|---|---|
| `RISE_COMPARISON_DRIVER` | `gemini` or `openai_compatible` | — (Gemini when set up, else off) |
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
- The instruction asks for short, cautious sentences and forbids measurements, causes, severity, growth, repair
  advice and safety judgments. As a second guard, every sentence goes through the same checks as single-photo
  results (see “How every result is checked”) before it's stored or shown. Raw responses are not stored.
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

### Gemini

- RISE asks Gemini for a bounded description, not a detection: it doesn't localize cracks, gives no calibrated score,
  and RISE shows none. Which Gemini model produced a result is stored with it (`model_version`).
- No accuracy is claimed for Gemini, or for any model, on residents' photos. A figure can only come from an
  evaluation run on held-out images labelled by people (see below). As of this change there is no labelled dataset
  in RISE, so no metric exists yet.

## Capstone evaluation workspace

A researcher-only area at `/research` for measuring how well a provider does on human-labelled images. It is
separate from residents' records: it never reads residents' spots, photos, notes or accounts, and nothing in it is
used to train or tune a model.

- **Access.** Grant it from the command line; there is no form for it, and everyone else gets a 404:

      php artisan rise:researcher researcher@example.test
      php artisan rise:researcher researcher@example.test --revoke

- **Images.** A researcher uploads images (JPEG, PNG or WebP, up to 20 at a time) and states what allows their use:
  taken by the research team, shared by a participant with specific written consent, or public domain or openly
  licensed. RISE keeps a re-encoded copy without location or other embedded details, never the uploaded file. Each
  image gets an opaque code (`EV-0001`), the only identifier in exports. Deleting an image, for example when consent
  is withdrawn, also removes its results from every run.
- **Labels.** A person labels each image crack-like, not crack-like or uncertain, can draw boxes around crack-like
  lines, and records focus, lighting and surface. Model results are never shown on the labelling page. Images are
  either held out (the only ones ever sent in a run) or development (for trying wording, never measured).
- **Runs.** A run sends each labelled held-out image, up to `RISE_EVALUATION_MAX_ITEMS` (200), to one configured
  provider and model, at most `RISE_EVALUATION_PER_MINUTE` (6) a minute through the queue. Labels and conditions are
  copied into the run when it starts, so relabelling later never changes a finished run. Starting runs is limited to
  6 an hour per researcher.
- **Metrics.** A human label of crack-like is the positive class and the finding `possible_feature` a positive
  prediction. Images labelled uncertain by a person are left out and counted; a model answer of uncertain is an
  abstention, reported as coverage. Each run shows precision and recall with 95% Wilson intervals, F1, coverage and
  a confusion matrix, overall and by focus, lighting, surface and model version, with a warning below 30 decided
  images. Location is scored only for providers that return boxes (Roboflow): a drawn box is found when a predicted
  box overlaps it with an IoU of at least 0.5, one match per box, with the mean best IoU per drawn box. Gemini runs
  have no location score.
- **Export.** Each run downloads as a CSV with one row per image: its code, human label, box count, conditions,
  provider, model ID and version, status, finding, predicted box count, best IoU and any error code. No files,
  names, accounts, authorization notes or timestamps of who labelled what.
- The workspace also shows residents' ratings of live results as counts by provider and model, never their notes.
  Ratings measure usefulness as residents saw it, not accuracy.

Until labelled held-out images have been run, the workspace says that metrics are unavailable. Don't publish an
accuracy figure without such a run, and report its sample size and intervals with it.

## Tests

    php artisan test

Provider calls are faked with `Http::fake()`, so the tests never contact Roboflow. HEIC tests are skipped where
Imagick can't decode HEIC. There are no automated tests yet for the Gemini provider, the photo checks, resident
feedback or the evaluation workspace.

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
