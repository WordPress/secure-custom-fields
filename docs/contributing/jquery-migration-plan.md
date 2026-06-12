# jQuery Reduction Migration Plan

This document describes the phased plan for reducing SCF's dependency on
jQuery and jQuery-era libraries, while preserving the public `acf.*`
JavaScript API and the jQuery-triggered events that third-party code relies
on. It is a living document: each phase records its rationale, risk,
ecosystem-compatibility strategy, and the test gates that must pass before
it ships.

## Current State

### Interactivity API usage

All admin-side interactive behavior is delivered through classic scripts
built around jQuery. The only `@wordpress/interactivity` code is the
opt-in front-end forms view bundle shipped by Phase 3
(`assets/src/js/frontend/scf-form-view.js`).

### jQuery load points

Script registrations in `includes/assets.php` declare these dependencies:

| Handle | jQuery-era dependencies |
| --- | --- |
| `acf` | `jquery` |
| `acf-input` | `jquery`, `jquery-ui-sortable`, `jquery-ui-resizable` |
| `acf-field-group` | inherits via `acf-input` |
| `acf-internal-post-type` | inherits via `acf-input` |
| `acf-escaped-html-notice` | `jquery` |

In addition, individual field types enqueue jQuery plugins at runtime:
Select2 (select, post object, page link, user, taxonomy, relationship),
jQuery UI datepicker/timepicker (date, time, date-time), and WordPress
core's jQuery-based `wp.media` and wpLink integrations (image, file,
gallery, link).

### Module classification

All modules in `assets/src/js/` subclass `acf.Model`
(`_acf-model.js`), whose `events` map (for example
`'click .selector': 'method'`) is implemented with jQuery event
delegation. The modules fall into three buckets:

1. **Leaf modules** — self-contained field types and utilities whose
   jQuery usage is purely internal DOM traversal/manipulation. These can
   be converted to vanilla DOM without touching the `acf.Model` API.
   Examples: url, range, radio, button group, checkbox, true/false,
   oembed, link, unload.
2. **Framework modules** — `_acf-model.js`, `_acf-field.js`,
   `_acf-validation.js`, `_acf-conditions.js` and friends. Their jQuery
   surface (events map, `$el`, `$()` helpers, jQuery-triggered
   `change`/`acf/*` events) **is** the ecosystem API and must be migrated
   behind a compatible facade, not removed.
3. **Library-bound modules** — anything that depends on Select2, jQuery
   UI widgets, `wp.media`, TinyMCE, or wpLink. These stay jQuery-era
   until the underlying library is replaced (phases 4–5), and the
   `wp.media`/TinyMCE-bound modules (image, file, gallery, wysiwyg, and
   the `acf.wpLink` manager) remain jQuery-era for as long as WordPress
   core itself exposes them through jQuery.

## Conversion patterns

These rules apply to every phase:

- **Internals vanilla, boundary jQuery.** Methods that other modules (or
  third-party code) consume — `$input()`, `$control()`, `$el`, and
  anything passed to helpers such as `acf.val()` or `acf.showLoading()` —
  keep returning jQuery objects. Inside a method, unwrap once
  (`this.$el[ 0 ]`, `this.$input()[ 0 ]`, `Array.from( this.$inputs() )`)
  and use native DOM from there.
- **Events.** Synthetic events with no extra arguments and no namespace
  are dispatched natively:
  `el.dispatchEvent( new Event( 'change', { bubbles: true } ) )`. jQuery
  listeners (including delegated ones) receive native bubbled events, so
  this is a strict superset of `jQuery.trigger()` for plain events. When
  a trigger passes extra parameters or uses a namespaced event (for
  example `acf.val()`'s `change` trigger consumed with handler data, or
  `wplink-open`/`wplink-close` fired by core), the jQuery trigger is
  **kept**, because jQuery trigger data and namespaces do not translate
  to native events.
- **HTML injection.** `jQuery.html( string )` executes embedded
  `<script>` tags; `innerHTML` does not. Call sites that may receive
  script-bearing markup (such as oEmbed provider responses) keep
  `.html()`.
- **Measurement.** jQuery's `.width()` (content-box width regardless of
  `box-sizing`) has no one-line native equivalent; measurement call sites
  keep jQuery until the affected UI is restyled.

## Phase 1: Vanilla internals for leaf modules (this PR)

Convert the internals of leaf modules from jQuery to native DOM while
still subclassing `acf.Model` and keeping every public behavior intact:
unload, url, range, radio, button group, checkbox, true/false, oembed,
and link (field portion).

- **Rationale:** these modules have no library dependencies, so they are
  the cheapest way to shrink jQuery usage and to establish the
  conversion patterns above with low blast radius.
- **Risk:** low. The `events` map, `$*()` helper methods, and value
  semantics are unchanged; only method bodies change.
- **Ecosystem compatibility:** the `acf.Field`/`acf.Model` API is
  untouched. Synthetic `change` events become native bubbled events,
  which jQuery delegation still catches. jQuery boundaries (per the
  patterns above) are kept where third-party or core code requires them.
- **Test gates:** full Jest suite, webpack production build, and the
  Playwright specs for every converted field type plus
  `conditional-logic.spec.ts` and `validation.spec.ts` as event-bubbling
  canaries.

## Phase 2: Vanilla event delegation inside `acf.Model`

Reimplement the `events` map binding in `_acf-model.js` with native
`addEventListener` plus selector matching (`Element.closest()`), while
preserving the exact callback signature `( e, $el )` — handlers continue
to receive a jQuery-wrapped `currentTarget` — and the `on()`, `off()`,
`trigger()` methods' jQuery-compatible behavior.

- **Rationale:** removes the framework-level jQuery dependency that every
  module inherits, without requiring any module or third-party change.
- **Risk:** medium-high. jQuery and native delegation differ in
  propagation details (e.g. `focus`/`blur` need capture-phase listeners,
  jQuery simulates bubbling for them), and namespaced removal
  (`.cid`-suffixed events) must be reimplemented.
- **Ecosystem compatibility:** third-party subclasses pass jQuery-style
  `events` maps and expect jQuery objects in handlers; both are
  preserved. Events triggered *by* third parties with
  `jQuery.trigger()` must still reach Model handlers, so Model listeners
  must remain reachable from jQuery's event system (or dual-bind during
  a transition).
- **Test gates:** same battery as phase 1, plus the full E2E suite,
  because every interactive screen exercises Model events.

## Phase 3: Interactivity API front-end forms bundle (this PR, tranche 2)

Provide an opt-in `@wordpress/interactivity`-powered bundle for front-end
forms rendered via `acf_form()`, so public-facing pages can render and
validate SCF forms without enqueueing jQuery at all.

- **Rationale:** front-end forms are the place where shipping
  admin-grade jQuery bundles hurts most (page weight, conflicts with
  themes); they also use only a subset of field types, making them a
  realistic first Interactivity API target.
- **Risk:** medium. The bundle is additive and opt-in; the default
  `acf_form()` pipeline is unchanged unless the integrator opts in.
- **Ecosystem compatibility:** opt-in flag only; sites relying on
  `acf.*` JS hooks on the front end keep the classic bundle. Fields that
  require jQuery-era libraries fall back to the classic bundle.
- **Test gates:** new E2E specs covering opt-in front-end form rendering,
  validation, and submission, plus the standard battery to prove the
  classic path is untouched.

### Implementation record

- **Opt-in flag:** the `frontend_interactivity_form` setting (default
  `false`), toggled via `acf_update_setting()` or the standard
  `acf/settings/frontend_interactivity_form` filter. With the flag off,
  behavior is byte-identical to before.
- **Files:** `includes/forms/form-front-view.php` (SCF-owned `scf_*`
  helpers), `assets/src/js/frontend/scf-form-view.js` (the
  `scf/form` interactivity store), and
  `assets/src/js/frontend/scf-form-validation.js` (pure, Jest-tested
  error-rendering/validation helpers). `includes/forms/form-front.php`
  carries two small flag-gated blocks; everything else is untouched.
- **Enqueue mechanics:** the field list is only known at render time, so
  when the flag is on `acf_form_head()` enqueues only the shared
  `acf-input` styles and defers the script decision to
  `acf_form_front::render_form()`. There, if the `<form>` wrapper is
  rendered, the classic stack is not already enqueued, and every field is
  a "simple" type, the `scf-form-view` script module is enqueued
  (modules print in the footer, so render-time enqueueing is safe) and
  Interactivity API directives (`data-wp-interactive="scf/form"`,
  `data-wp-on--submit/--input/--change`, `novalidate`) are added to the
  form attributes. Otherwise the classic stack is enqueued late through
  the established `ACF_Assets` late-enqueue path (scripts then print in
  the footer, which the classic bundle already supports on the front
  end).
- **Eligibility guard:** simple types are text, textarea, number, email,
  url, password, range, select (only with `ui`/`ajax` off), checkbox,
  radio, button_group, and true_false
  (`scf_frontend_form_fields_are_view_compatible()`). Anything else —
  repeater, flexible content, gallery, media, wysiwyg, pickers,
  Select2-backed types, clone/group, etc. — falls back to classic.
  Mixed pages resolve safely in both orders: once a complex form
  enqueues the classic stack, later simple forms render classic; if a
  slim form rendered first, its store defers to `window.acf` at runtime
  so the classic validator owns the page.
- **Submit-flow decision:** server-side validation failures on a native
  POST are terminal (`acf_validate_save_post( true )` calls `wp_die()`;
  form-front does **not** re-render the form with errors — verified in
  source), so pre-submit validation is required for usable UX, exactly
  as in the classic stack. The slim bundle therefore (1) runs native
  constraint validation first (required, email/url format, number
  min/max/step) and renders the browser's `validationMessage` — the same
  message source the classic `invalid`-event handler uses — in classic
  error markup (`.acf-error` on the field wrap, an
  `acf-notice -error acf-error-message` notice in `.acf-input`, plus the
  classic form-level summary wording); then (2) posts the form data to
  the same `acf/validate_save_post` admin-ajax endpoint via `fetch()` so
  custom `acf/validate_value` filters, checkbox-group required rules,
  and the honeypot stay authoritative; and (3) on success submits the
  form natively, leaving the `form-front.php` POST
  validate/save/redirect pipeline unchanged. The `.acf-spinner`
  lock/unlock behavior mirrors `acf.lockForm()`/`acf.unlockForm()`.
- **Module registration:** built as a real ES module via a separate
  webpack config (`experiments.outputModule`), with
  `@wordpress/dependency-extraction-webpack-plugin` externalizing
  `@wordpress/interactivity` and emitting a module-type `.asset.php`,
  registered with `wp_register_script_module()`. The hand-rolled
  fallback was not needed. WP < 6.5 (no script modules / Interactivity
  API) always uses the classic stack. `withSyncEvent` is feature-detected
  through a namespace import so the module still links on WP 6.5–6.7,
  where that export does not exist.
- **Scope cuts:** the form-level summary notice is non-dismissible (the
  classic dismiss link is jQuery-driven, and directives on
  runtime-inserted nodes are not hydrated); the classic stack has no
  maxlength character counter on these fields, so none was added; jQuery
  `change` triggers/`acf/*` JS hooks are intentionally absent from the
  slim bundle — integrations needing them should keep the flag off.

## Phase 4: SortableJS and native date/time inputs

Replace `jquery-ui-sortable`/`jquery-ui-resizable` (field reordering,
repeater/flexible content, gallery) with SortableJS, and offer a native
`<input type="date|time|datetime-local">` rendering option for the
date/time pickers.

- **Rationale:** jQuery UI is the heaviest jQuery-era dependency on
  `acf-input` and is in maintenance-only mode upstream.
- **Risk:** high for sortable (drag-and-drop semantics, sort events that
  other modules listen to, `sortstart`/`sortstop` actions exposed as
  `acf/*` hooks); medium for date/time (formatting and localization
  parity).
- **Ecosystem compatibility:** the `acf.addAction( 'sortstart' ... )`
  style hooks keep firing with the same arguments. jQuery UI datepicker
  remains the default; native inputs ship as a per-field opt-in until a
  major release flips the default.
- **Test gates:** E2E specs for repeater/flexible/gallery ordering and
  date/time fields, plus the full battery.

## Phase 5: Select2 to `wp-components`

Migrate Select2-backed fields (select, post object, page link, user,
taxonomy, relationship search) to `@wordpress/components`-based UI.

- **Rationale:** Select2 is the largest remaining jQuery plugin and the
  most common source of version conflicts with other plugins.
- **Risk:** high. Select2 markup and the `acf/select2/*` JS filter hooks
  (`select2_args`, `select2_ajax_data`, etc.) are widely customized by
  third parties.
- **Ecosystem compatibility:** the `acf/select2/*` JS hooks are
  deprecated over **two major releases**: in the first major they keep
  working against a compatibility layer and log a deprecation notice;
  in the second major they are removed. Server-side AJAX contracts
  (`acf/fields/post_object/query` and friends) are unaffected.
- **Test gates:** E2E specs for every Select2-backed field, AJAX
  pagination/search flows, and the full battery.

## What stays jQuery-era

Modules bound to `wp.media` (image, file, gallery), TinyMCE (wysiwyg),
and wpLink (the `acf.wpLink` manager) keep their jQuery internals until
WordPress core moves those APIs off jQuery. Wrapping them now would add
risk without removing the underlying dependency, since core itself
enqueues and drives them through jQuery.
