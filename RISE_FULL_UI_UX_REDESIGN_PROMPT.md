# RISE — Full UI/UX Redesign Brief

Run this from the root of the existing RISE Laravel project. This is a full visual redesign pass and supersedes earlier visual direction when the two conflict. Inspect the current code first, then implement the redesign in the existing application. Do not stop after writing a plan or changing a few colors.

## Design read

RISE is a resident-facing photo journal for tracking visible cracks and surfaces at home. Give it the visual quality of a thoughtful premium home companion crossed with an architectural field notebook: confident, photo-led, editorial, calm, and unmistakably designed for this purpose.

Aim for **design variance 7/10, motion 3/10, visual density 4/10**. Make the pages memorable through composition, image scale, typography, precise spacing, and a few purposeful interactions. Keep the existing light-blue and white direction. The current app already has blue tokens; a palette-only revision will not satisfy this request.

## Start with this code review

The app is Laravel 13 with Blade, Tailwind CSS v4, Vite, and vanilla JavaScript. Preserve this stack and inspect the current files before editing; source code may have changed since these notes were written.

Current visual shortcomings found in the repository:

- The blue palette, Instrument Sans loading, light canvas, and subtle shadows are already implemented. The main problem is composition: most pages still repeat a centered heading followed by rounded white panels, thin borders, and small metadata.
- The shared shell still uses a short horizontal header with only two links and a repeated capstone disclosure footer. It feels like a generic utility page rather than a finished resident product.
- The overview has a recent-photo mosaic and monitoring-point list, but the page still reads as stacked sections with standard card styling. Make it feel like a distinctive personal home log.
- The point history has a large latest-photo area and date grouping, but entry rows and controls still look like the same generic panel system.
- Upload, compare, AI result, login, registration, create/edit point, alerts, field errors, and destructive confirmations need the same level of visual craft as the overview.
- `resources/views/components/note-card.blade.php` still has separate hard-coded gray and blue styles. Notes routes and views also remain, even though Notes are not in the main RISE navigation. Audit whether these routes are still intended to be user-facing; do not leave any reachable route looking like a forgotten starter app, and do not delete note data.

The redesigned interface should look materially different from the current one in a screenshot. Replacing green with blue, increasing border radii, or adding more shadows alone is not a redesign.

## Product and capstone context

RISE lets a resident sign in, name a wall or surface, upload or take a photo, keep notes, return over time, compare images, and optionally ask an AI model to mark possible crack-like areas. It is a software-only capstone prototype. Keep the user’s own photos at the center of the experience.

Montana Homes places Montana Misora Homes in **Maddarulug Norte, Enrile, Cagayan, near Tuguegarao**; its project page says the development is about a 10-minute drive from Tuguegarao City’s commercial center. It describes the neighborhood as a modern Filipino and Japanese-inspired residential development. Use this as quiet context for the product’s atmosphere, not as permission to make RISE look like a real-estate sales site or an official Montana Homes service.

The site does not expose a confirmed public UI color specification. Keep RISE’s light blue and white palette, and use the Montana Homes site and project photos as visual context only. Do not claim proposed RISE color values are official brand colors or reproduce Montana Homes’ logo without authorization.

Sources:

- [Montana Homes — Projects](https://montanahomes.com.ph/projects/)
- [Montana Misora Homes Phase 1 — official project page](https://montanahomes.com.ph/project/montana-misora-homes-phase-1/)
- [Montana Misora Homes grand-launch article](https://montanahomes.com.ph/project-news/montana-misora-homes-grand-launching-marks-the-beginning-of-a-new-chapter/)

## Design research to apply

Study these sources before choosing page layouts. Use each for a different job:

- [Refero Styles](https://styles.refero.design/) — study product design systems, spacing, type hierarchy, colors, and reusable component decisions. Look for calm, light product interfaces and image-heavy records.
- [Refero screens and flows](https://refero.design/) — compare real patterns for dashboards, timelines, image galleries, upload flows, and settings.
- [Mobbin](https://mobbin.com/) — study shipped web and mobile app screens and complete flows. Focus on navigation, onboarding, photo selection, galleries, comparison, confirmation, and empty/error states.
- [Aceternity UI](https://ui.aceternity.com/) — use selectively for visual ideas such as [Bento Grid](https://ui.aceternity.com/components/bento-grid), [File Upload](https://ui.aceternity.com/components/file-upload), [Compare](https://ui.aceternity.com/components/compare), and [Card Spotlight](https://ui.aceternity.com/components/card-spotlight).

Aceternity’s examples use React and Motion. This app uses Blade and vanilla JavaScript: translate a useful visual pattern into the current stack instead of installing React or copying code that cannot run here. Avoid spreading spotlight effects or motion across every card. Choose at most two interaction patterns that improve RISE’s actual tasks.

### Images for visual direction

The photos below are references for residential context and composition only. Do not hotlink them into the shipped app or use them as pretend resident records. Use existing private resident photos in the product, or create original, clearly illustrative local assets if a screen needs an empty-state graphic.

![Montana Misora Homes project photo from the official Montana Homes site](https://montanahomes.com.ph/wp-content/uploads/2025/06/homes-edit-1024x683.jpg)

![Refero UI gallery showing real interface references and pattern categories](https://refero.design/static/media/mediakit-visuals%40x2.c12c1d0a6c155570c6d5.jpg)

![Mobbin Discover screen showing its app, screen, UI-element, and flow browsing modes](https://cdn.sanity.io/images/rh3yfke9/production/7a73f6fb33fda73b7d616928fd6fe1d221a0ca70-1499x784.png?auto=format&fit=min&h=1004&w=1920)

![Aceternity feature-card grid — composition inspiration only, not RISE colors or copy](https://aceternity.com/cdn-cgi/image/width%3D2048/https%3A/assets.aceternity.com/features-section.png)

![Example of an AI overlay marking a possible wall crack — visual reference only; do not copy its claims](https://static.wixstatic.com/media/d060aa_c3c25a51b5bd4282a1f233a1b3a6460c~mv2.png/v1/fill/w_980%2Ch_1124%2Cal_c%2Cq_90%2Cusm_0.66_1.00_0.01%2Cenc_auto/d060aa_c3c25a51b5bd4282a1f233a1b3a6460c~mv2.png)

The Aceternity image is from its [feature-grid reference](https://ui.aceternity.com/categories/bento-grid). The crack-overlay image is from [Somia’s article on AI in architecture](https://www.somiasolutions.com/news/somia-brings-ai-innovation-to-architecture).
The Refero and Mobbin screenshots are examples of design-research libraries, not screens to reproduce in RISE; the Mobbin screenshot is from [this overview](https://www.polipo.io/blog/what-is-mobbin-and-why-it-matters).

## Art direction: “The home observation journal”

Create a coherent system that feels like documenting a real place over time—not a generic SaaS admin template and not a property listing page.

- **Color:** Keep white and pale sky blue as the main surfaces. Use a more assertive deep blue/navy for navigation, important headings, selected states, and primary actions. Add tonal range and intentional color blocks; do not tint every panel the same pale blue. Keep amber/orange for AI-marked regions. Keep semantic success and error colors distinct.
- **Typography:** Keep Instrument Sans for readable interface text. Use strong display sizing and weight contrast for page titles and date stamps; if adding a display face, use one complementary face and load it through the existing build setup. Avoid tiny low-contrast metadata.
- **Composition:** Break up the repeated card stack. Mix open canvas, asymmetrical photo layouts, editorial section headings, compact timelines, strong photo frames, and a few elevated surfaces. Use a consistent grid and alignments rather than arbitrary decoration.
- **Signature detail:** Build a restrained architectural line motif or observation-mark detail for the brand and empty states. It may suggest a house corner, a dated photo, or a notebook margin. Do not draw fake detections over sample walls or make the illustration look like a safety diagram.
- **Texture and imagery:** The resident’s actual photos provide color and texture. Give them space, natural aspect ratios, useful cropping, and clear date labels. Never fill an empty account with stock photos presented as the resident’s home.
- **Motion:** Use brief, intentional feedback for navigation, image selection, upload progress, and hover/focus. Respect reduced motion. No scroll hijacking, parallax-heavy dashboard, endless shimmer, or decorative animation with no task purpose.
- **Cards:** Do not wrap every element in a rounded card. Use boundaries only when they help group content. Avoid generic gradient blobs, repeated icon circles, over-rounded “pill” controls, and the familiar three-equal-card template.

## Redesign every user-facing screen and reusable component

Inventory all Blade views and shared components under `resources/views` before editing. Redesign all of them in one visual system; do not polish only the overview. At minimum cover:

1. **Application shell:** brand mark, desktop navigation, account menu, mobile navigation/action, footer/disclosure, active states, and page widths. Consider a desktop side rail with a mobile bottom bar if it gives the product a more distinctive, usable structure. Keep the Montana Misora capstone disclosure accurate and visually quiet.
2. **Overview:** create a distinct photo-led “home log” composition. Make the latest observation visually dominant, organize monitoring spots and recent activity for quick scanning, and make the primary “Add photo” task unmistakable. Use only real counts and current account data.
3. **Monitoring point create/edit:** refine labels, helper text, form grouping, save/cancel actions, and the destructive-delete area. Make destructive actions clearly separate and confirm them accessibly.
4. **Monitoring point detail/history:** treat the latest image as a documentary record, make date, note, and next actions easy to scan, and make the older history feel like a clear timeline or photo journal. Preserve pagination and links to individual records.
5. **Photo upload:** design a polished desktop and mobile file-selection experience. Keep the Mac file picker, camera/library actions, drag-and-drop, local preview, HEIC/HEIF guidance/conversion, previous-photo framing reference, upload progress, retry/error states, and privacy disclosure. The user must still be able to complete the task with keyboard and with the native file input if JavaScript fails.
6. **Photo comparison:** give the earlier and later images equal visual priority, make date selection and the elapsed time legible, and retain the current side-by-side view. You may add a before/after wipe control if it is accessible, works with keyboard/touch, and provides a clear side-by-side fallback. Never describe visual difference as measured change.
7. **AI analysis:** redesign the unconfigured, consent/submit, waiting, failed/retry, and completed states. Keep the actual original/marked image switch, model provenance, score explanation, provider disclosure, and warning visible without making the page feel alarming. AI labels must say “possible crack-like area”; do not imply confirmation, severity, cause, measurement, or structural safety.
8. **Login/register:** redesign the shared auth experience to feel like RISE, with a distinctive but lightweight visual and a clear form hierarchy. Preserve existing auth behavior and field validation.
9. **Shared components:** buttons, links, text fields, select, textarea, alerts, inline errors, status summaries, thumbnails, date stamps, empty states, upload progress, dropdowns, segmented control, deletion confirmations, iconography, and focus/disabled/loading states.
10. **Legacy Notes pages:** the authenticated Notes routes/views still exist outside the main navigation. Check whether they are intentionally part of the product. If retained, bring them into the RISE shell and design system, but do not add them to primary navigation without a product reason. Do not delete existing notes or tables.
11. **Large-upload error page:** keep the 413 recovery path visually consistent and make it easy to return to photo upload.

## Product rules and implementation guardrails

- Preserve the existing Laravel routes, authentication, ownership checks, private photo storage, real photo history, comparison, upload code, HEIC conversion, and real optional model integration. Do not replace real behavior with a mockup.
- Do not add hardware, maps, property sales, rental/lease features, fake records, fake residents, fake statistics, fake analyses, or fake annotations.
- Keep AI analysis opt-in. The user must see what external provider receives a photo before submitting analysis. Keep provider credentials on the server.
- Never expose private photos through public URLs. Do not use Montana Homes or other copyrighted images in production without permission.
- Keep all account and privacy copy truthful. A resident’s label is not a verified postal address. RISE is a capstone prototype and must not imply an official Montana Homes partnership.
- Use the existing Blade/Tailwind/Vite architecture. Avoid new dependencies unless the current stack genuinely cannot meet a requirement. Do not add a React runtime.
- Preserve accessible contrast, keyboard operation, visible focus, screen-reader labels, useful image alt text, mobile layouts, and reduced-motion behavior.
- Do not reset/revert the existing uncommitted work. Do not delete user data, database tables, or unrelated files.
- Do not add or run automated tests unless the user asks. If a local browser preview is available, inspect desktop and phone layouts; otherwise say clearly that visual rendering was not checked.

## Done means

- Every active screen and shared component feels like the same thoughtfully art-directed product.
- The interface is recognizably more polished and expressive than the current blue-card layout at first glance, while remaining calm and usable for residents.
- Photo upload, history, comparison, authentication, and AI states still behave as before.
- The result uses the requested blue/white identity, actual user data, and accurate capstone context.

At completion, summarize the pages/components changed, the design direction and tokens used, preserved behavior, and any part that could not be previewed. Do not claim that screenshots or tests were checked unless they were actually checked.
