# RISE — Visual Redesign and Product Polish

Use this brief from the root of the existing RISE Laravel project. Make the improvements directly in the current application. Start by inspecting the repository and its local instructions, then implement the redesign without replacing working features.

## Product and scope

RISE is a capstone software prototype for residents in the Montana Misora Homes community to keep dated photo records of visible cracks and surfaces at home. Residents can create a named monitoring point, take or upload a photo from a phone or computer, optionally request AI image analysis, review the history, and compare two photos.

The project is software-only. Do not add sensors, installed cameras, modems, structural diagnosis, safety certification, or claims about a crack’s cause, severity, width, or length. AI is an optional visual aid that can mark a possible crack-like area; it can miss or mislabel things.

The current user request is the active product direction. Treat previous planning prompts or copied planning documents as background only if they are still present; they do not override this brief. Follow repository-specific instructions in `AGENTS.md`. Preserve current user changes and do not reset, clean, stage, or commit the repository.

## Repository review findings to address

The current app is Blade, Tailwind CSS v4, Vite, and vanilla JavaScript. Keep that stack; do not convert the project to React or add a new UI framework.

- The palette in `resources/css/app.css` is still forest green (`--color-primary`, `--color-primary-strong`, and `--color-tint`), which no longer matches the requested light-blue direction.
- The design uses mostly white pages, thin outlined panels, modest corner rounding, and a system sans-serif stack. This makes the pages read as a basic starter CRUD interface. `vite.config.js` already configures Instrument Sans, but the shared app layout does not load it.
- The overview currently introduces the user with a plain heading and counts, then shows monitoring-point cards and a simple recent-photo list. Give the page a stronger, photo-led hierarchy and a clearer sense of place without inventing data.
- The monitoring-point page has a useful photo timeline and thumbnail strip, but the repeated stacked layout could make dates and comparisons easier to scan.
- The add-photo page already supports a native file picker, drag and drop, camera/library actions, preview, upload progress, HEIC conversion when the server supports it, privacy copy, and a previous-photo framing reference. Improve its visual hierarchy and clarity; do not replace or break that workflow.
- AI analysis already has explicit user initiation, provider disclosure, status/error states, model-result overlays, and an original-photo view. Preserve the real implementation and make its states feel deliberate. Never display fabricated detections or example metrics as real results.
- The compare-page copy says “RISE doesn’t analyse photos automatically.” Make it clearer that analysis is optional and only runs after the resident asks for it.
- The unused Laravel welcome template and legacy files may still exist. Check whether a file is actually routed or referenced before removing it.

These are static code-review findings. Inspect the current files before changing anything; code may have changed since this brief was written.

## Montana Misora Homes research

Use the project’s real setting to shape the visual tone and wording:

- Montana Homes identifies Montana Misora Homes Phase 1 as being in Enrile, Cagayan. Its project page says the development is about a 10-minute drive from Tuguegarao City’s commercial center. Refer to it accurately as **Maddarulug Norte, Enrile, Cagayan, near Tuguegarao**—do not describe it as being inside Tuguegarao City.
- Montana Homes describes the 14.4-hectare community as combining modern Filipino and Japanese-inspired design. Let that inform a calm, bright, orderly residential feel, with natural materials and restrained details. Avoid stereotypical Japanese motifs or making the monitoring app look like a property-sales website.
- The official project page lists two- and three-bedroom home layouts, parks/playground, basketball court, gated access, guards, CCTV, and street lighting. These are context, not data or features to add to RISE.
- The official public site does not provide an accessible UI brand guide with confirmed color values. Use the user-requested **light blue and white** direction, and sample the current Montana Homes site/logo visually if useful. Do not claim that your chosen hex values are official corporate brand colors. Keep the RISE identity distinct and do not imply Montana Homes endorses the app.

Sources:

- [Montana Homes — Projects](https://montanahomes.com.ph/projects/)
- [Montana Misora Homes Phase 1 — official project page](https://montanahomes.com.ph/project/montana-misora-homes-phase-1/)
- [Montana Misora Homes grand-launch article](https://montanahomes.com.ph/project-news/montana-misora-homes-grand-launching-marks-the-beginning-of-a-new-chapter/)

### Visual images

Use these as references for the real residential context and for how a crack overlay can be shown. The crack image is a visual reference only: do not copy its measurement or diagnostic claims into RISE. Do not hotlink these images in the shipped app or reuse them there without permission; prefer residents’ own photos or properly licensed/local project assets.

![Montana Misora Homes project imagery — official Montana Homes site](https://montanahomes.com.ph/wp-content/uploads/2025/06/homes-edit-1024x683.jpg)

![Example of a wall crack highlighted in an image — visual reference only](https://static.wixstatic.com/media/d060aa_c3c25a51b5bd4282a1f233a1a3b6460c~mv2.png/v1/fill/w_980%2Ch_1124%2Cal_c%2Cq_90%2Cusm_0.66_1.00_0.01%2Cenc_auto/d060aa_c3c25a51b5bd4282a1f233a1b3a6460c~mv2.png)

The official Montana Homes photo is also available at [its original project-page image URL](https://montanahomes.com.ph/wp-content/uploads/2025/06/homes-edit-1024x683.jpg). The crack-overlay reference is from [Somia’s architecture AI article](https://www.somiasolutions.com/news/somia-brings-ai-innovation-to-architecture).

## Design references

Use these to study interaction patterns and visual craft. Adapt references to RISE’s Blade/Tailwind stack rather than copying screens or importing incompatible React components.

- [Refero — real product screens and flows](https://refero.design/)
- [Refero Styles — design-system references](https://styles.refero.design/)
- [Mobbin — web and mobile app patterns](https://mobbin.com/discover/apps/web)
- [Aceternity UI — component inspiration](https://ui.aceternity.com/components)
- [Dribbble — residential property dashboard concept](https://dribbble.com/shots/27205876-Real-Estate-App-Dashboard-UI-Design-SaaS-Property-Management)
- [Godly Design — curated web inspiration](https://godly.design/)
- [Variant — visual design-direction exploration](https://variant.com/)

Prefer Refero and Mobbin to understand usable app patterns. Treat Dribbble, Godly, Variant, and Aceternity as visual inspiration; do not let marketing-site patterns overwhelm the resident workflow.

## Visual direction

Build a polished, calm, photo-first resident tool that feels trustworthy and specific to a real neighborhood. Make the user’s own wall photos the main visual content. Avoid generic SaaS gradients, glassmorphism, oversized empty hero art, excessive pills, over-rounded cards, and decorative animation without a purpose.

Replace the green brand palette with a coherent light-blue and white system. A starting point to tune for contrast and the Montana Homes visual reference:

- White: `#FFFFFF`
- Cool canvas: `#F5FAFD`
- Pale sky surface: `#EAF4FA`
- Soft blue border: `#D4E4EE`
- Primary blue: `#2F6F92`
- Deep blue for headings and pressed states: `#214F6A`
- Slate ink: `#203746`
- Muted slate: `#566A78`
- Keep amber/orange for AI-marked regions so AI annotations remain visually distinct from the blue brand color.

These are proposed interface tokens, not official Montana Homes values. Check text and controls for WCAG AA contrast; pale blue should be used for surfaces, not low-contrast body text or primary buttons. Update the theme-color metadata, focus states, links, buttons, selected navigation, form controls, progress indicators, empty states, and all old hard-coded green accents consistently. Preserve semantic success, warning, and error colors where users need them.

Use the already configured Instrument Sans if it can be loaded cleanly through the existing Vite setup; otherwise choose a suitable readable sans-serif and remove or correct unused font configuration. Establish a deliberate type scale, strong page headings, clear metadata, consistent spacing, and restrained depth. Use accessible focus states, reduced-motion support, useful alt text, and comfortable touch targets.

## Screens and interactions to improve

1. **Shared application shell:** Give desktop navigation and the account menu a more considered hierarchy. Keep the main “Overview” and “Add photo” actions easy to find. Keep mobile navigation and the existing thumb-reachable photo action usable. Do not add links to routes that do not exist.
2. **Overview:** Make the first screen immediately explain the user’s monitoring workflow. Give recent resident photos visual priority, make monitoring-point cards feel connected to a location/history, and show only truthful totals already available from the database. Make first-use empty state welcoming and instructional, not a generic blank panel. Any “Montana Misora Homes · Enrile, Cagayan” context label must be clear that RISE is a capstone prototype and not an official developer service.
3. **Monitoring-point history:** Make the photo history easier to scan by date, show the latest saved photo prominently, and make “Add photo,” “Compare,” and optional analysis easy to discover without crowding each entry. Retain the same monitoring-point owner/privacy rules.
4. **Add photo:** Make the drop zone and choose/take-photo actions obvious, especially on a Mac where residents will upload from the file picker. Make the selected-photo preview, file details, HEIC note, framing guidance, and upload states visually clear. Keep keyboard operation and the native no-JavaScript file input functional. Do not remove working HEIC conversion or Mac/iPhone guidance.
5. **Compare:** Present the selected earlier/later photos and timestamps clearly on desktop and mobile. Keep the existing caveat that visual differences can come from lighting, distance, or angle. Clarify that AI analysis only happens when the user requests it.
6. **AI analysis:** Make the real original/marked image switch, waiting, unconfigured, failure, retry, and completed states consistent and easy to understand. Keep provider disclosure before transmission and keep provider credentials server-side. Describe results as “possible crack-like areas.” Never imply the AI has confirmed a crack, measured it, found its cause, or assessed building safety.
7. **Sign-in and registration:** Bring the existing authentication screens into the RISE visual system while keeping the existing login and registration behavior intact.

## Implementation constraints

- Preserve the existing authentication, account ownership checks, private image storage, photo history, compare route, current upload behavior, HEIC handling, and real analysis integration.
- Do not expose images through public URLs or send a photo for analysis without the existing user-initiated action and clear consent disclosure.
- Do not use fake dashboard counts, fake resident names, fake photo records, fake AI outcomes, or unsupported safety/structural claims.
- Do not create a generic property-management product: no sales listings, rent, pricing, leases, leads, maps, or maintenance ticket system.
- Use the existing components and CSS tokens where practical; improve them coherently instead of layering conflicting one-off styles on top.
- Keep implementation compatible with Laravel Blade, Tailwind CSS v4, Vite, and the project’s existing JavaScript. Do not add a React runtime or copy-paste a component that requires an incompatible stack.
- Do not add tool/assistant credits or assistant identity to the app UI, README, or code comments. Keep technical service names that are necessary for configuration and photo-transmission disclosure.
- Make only changes needed for this brief. Do not delete user data, database tables, or unrelated features.

## Completion report

When finished, report the main screens changed, the final blue/white color tokens, any implementation limitations, and what you actually inspected or previewed. Do not claim that a page was visually checked if you could not render it. Do not run automated tests unless the user asks for them.
