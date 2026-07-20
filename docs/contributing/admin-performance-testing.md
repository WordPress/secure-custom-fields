# Performance Testing for Admin Screens

Use this checklist before opening a PR that changes how an SCF admin screen renders or loads. It catches performance regressions that automated tests miss, since the project has no dedicated performance harness.

## When to run this

Run through this checklist when a PR touches any of the following:

- Field group or options page rendering
- Field list tables or field group editors
- The Tools screens (import or export)
- Asset enqueuing on admin screens

For docs-only or non-admin changes, the regular test suite is enough.

## Prerequisites

Make sure your environment is running and assets are current:

```sh
npm install
composer install
npm run wp-env -- start
npm run build
```

## Screens to test

These are the heaviest admin screens and the most likely to regress:

- Field Groups list: `edit.php?post_type=acf-field-group`
- Field Group editor: `post-new.php?post_type=acf-field-group` to create a group, or `post.php?post=<id>` to edit an existing one
- UI Options Pages list: `edit.php?post_type=acf-ui-options-page`
- UI Options Page editor: `post-new.php?post_type=acf-ui-options-page`
- Runtime options page: `admin.php?page=<your-options-page-slug>`
- Tools: `admin.php?page=acf-tools`

## Browser checks

Open each screen in your browser with DevTools open. There is no new tooling required for these checks:

1. Open the Network panel and disable cache. Reload the screen and confirm no new large or duplicate asset requests were introduced.
2. Check the Console for new JavaScript errors or warnings.
3. For PHP warnings, inspect the response body in the Network panel or check the server debug log (`wp-content/debug.log` when `WP_DEBUG_LOG` is on). The browser Console does not reliably surface these.
4. Confirm the layout matches the previous state. No shifted columns, broken metaboxes, or missing icons.
5. In the Field Group editor, add several fields and drag them around. The drag and drop should stay responsive with no visible lag.
6. Open a field group with many fields (20 or more) via its `post.php?post=<id>` edit URL. It should render without a long pause.

## Playwright smoke

The existing end-to-end tests do not measure performance, but they confirm the screens still load after your change. Run the specs that cover the affected screens:

```sh
npm run wp-env -- start
npm run test:e2e -- tests/e2e/field-group-duplication.spec.ts
npm run test:e2e -- tests/e2e/options-page-admin.spec.ts
```

If your PR touches a different screen, pick the matching spec from `tests/e2e/` instead.

## Optional ad-hoc profiling

For a deeper look, use your browser's built-in profiler on a logged-in session. This needs no new tooling and does not run in CI:

1. Open the admin screen in your browser while logged in to wp-admin.
2. Open DevTools and go to the Performance panel.
3. Start a recording, reload the page, then stop the recording once it finishes loading.
4. Compare the script timing and layout cost against the same recording on `trunk`. A meaningful jump on the same screen is a regression to investigate.

The Network panel's "Disable cache" option and the CPU throttling preset (for example, 4x slowdown) make the comparison more consistent across runs.
