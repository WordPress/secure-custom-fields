# Porting Advanced Custom Fields Releases

This guide tracks how to port release commits from the local
`advanced-custom-fields-pro` checkout into Secure Custom Fields.

The local upstream checkout used for this investigation is:

```sh
/Users/carlos/Developer/wp-plugins/advanced-custom-fields-pro
```

## Current Map

| Repository | Current local state | Notes |
| --- | --- | --- |
| `advanced-custom-fields-pro` | `main` at `ceb377b` | Commit messages are version numbers. |
| `secure-custom-fields` | branch `branch/issue-419-gutenberg-custom-field-placeholders` | Plugin header currently says `6.8.3`. |

Important upstream commits:

| Version | Commit |
| --- | --- |
| `6.7.2` | `e2c878d` |
| `6.8.0` | `602eb74` |
| `6.8.0.1` | `ceb377b` |

Important SCF commits:

| Version or work | Commit |
| --- | --- |
| SCF `6.7.1` tag | `99a8b65` |
| `Backports: Updates from 6.7.2` | `4039684` |
| SCF `6.8.0` tag | `52ae7db` |
| SCF `6.8.3` tag | `0f21dd7` |

There is no SCF `6.7.2` tag in this worktree. The 6.7.2 parity work is
represented by `4039684`.

## Upstream 6.8.0 Scope

The upstream diff from ACF PRO `6.7.2` to `6.8.0` is large:

```sh
git -C /Users/carlos/Developer/wp-plugins/advanced-custom-fields-pro diff --shortstat e2c878d 602eb74
```

Result observed locally:

```text
258 files changed, 29525 insertions(+), 587 deletions(-)
```

Ignoring translations and vendor files, the functional diff is still large:

```text
118 files changed, 26542 insertions(+), 97 deletions(-)
```

The main functional buckets are:

- AI and WordPress Abilities API integration under `src/AI/**`.
- JSON-LD/GEO output and Schema.org data under `src/AI/GEO/**`.
- WP-CLI JSON import/export/sync commands under `src/CLI/**`.
- Field creation schemas under `schemas/fields/v1/*.json`.
- Field type PHP methods for JSON-LD and creation schema support.
- ACF Blocks v3 PHP and compiled block editor JS changes.
- New icons: `icon-experimental.svg` and `icon-tree-branch.svg`.
- Translations and generated assets.

## SCF Status

SCF has already implemented an SCF-specific abilities and schema framework:

- `includes/abilities/class-scf-abilities-integration.php`
- `includes/abilities/class-scf-internal-post-type-abilities.php`
- `includes/abilities/class-scf-field-abilities.php`
- `includes/class-scf-schema-builder.php`
- `includes/post-types/class-scf-field-manager.php`
- `schemas/*.schema.json`
- `schemas/field-fragments/**/*.schema.json`

This means the upstream ACF 6.8.0 PHP cannot be copied blindly. Some upstream
features map onto existing SCF concepts, while others are not present yet.

| Upstream 6.8.0 area | SCF status in this worktree |
| --- | --- |
| `src/AI/Abilities/**` | SCF has its own procedural/classic PHP implementation in `includes/abilities/**`. |
| `schemas/fields/v1/*.json` | SCF uses `schemas/field-fragments/**` and generated `schemas/field.schema.json` instead. |
| `acf_get_field_json_schema()` | Not present. SCF uses `SCF_Schema_Builder` and `SCF_JSON_Schema_Validator`. |
| `get_field_creation_schema()` on `acf_field` | Not present. Equivalent behavior should be routed through SCF schemas. |
| JSON-LD/GEO (`src/AI/GEO/**`) | Not present. This is separate from SCF abilities and must be product-reviewed before porting. |
| WP-CLI `acf json` (`src/CLI/**`) | Not present. Needs an SCF command name decision before porting. |
| `enable_acf_ai` setting | Not present. Needs naming and product policy decisions for SCF. |
| ACF Blocks v3 expanded editor settings | Partly absent from source; see the JS section below. |
| New icons | Not present. |

## PHP Porting Strategy

Start from upstream `e2c878d..602eb74`, not from the current upstream `HEAD`,
unless the target explicitly includes `6.8.0.1`.

Use this path list first:

```sh
git -C /Users/carlos/Developer/wp-plugins/advanced-custom-fields-pro diff --name-status e2c878d 602eb74 -- '*.php' ':(exclude)lang/**' ':(exclude)vendor/**'
```

Recommended order:

1. Port small compatibility changes first:
   - post type support order and `notes` behavior
   - field setting additions
   - block rendering fixes that do not depend on AI/GEO
2. Port schema-related behavior into the SCF schema system:
   - add or update `schemas/field-fragments/**`
   - regenerate `schemas/field.schema.json` if the repo workflow expects it
   - do not create `schemas/fields/v1/**` unless SCF decides to adopt that upstream layout
3. Port abilities behavior by comparing upstream ability outputs against SCF's existing ability classes.
4. Treat AI/GEO and WP-CLI as separate feature ports:
   - decide names (`acf` compatibility versus `scf` primary naming)
   - decide default settings
   - decide whether public hooks remain `acf/*` for compatibility
5. Update translations only after functional changes settle.

SCF adaptation rules:

- Keep backward-compatible `acf_` APIs when external plugins likely call them.
- Prefer `scf_` for new SCF-only APIs.
- Use text domain `secure-custom-fields`.
- Escape output with `esc_html()`, `esc_attr()`, and related WordPress helpers.
- Preserve existing SCF architecture instead of replacing it with upstream namespaces.

## JavaScript Reality Check

Upstream ACF PRO only ships compiled and minified JS bundles in this checkout.
There are no upstream `assets/src/js/**` sources or source maps.

SCF now uses recovered source files for the block editor bundle:

- `assets/src/js/pro/acf-pro-blocks.js` imports:
  - `./_acf-jsx-names.js`
  - `./_acf-blocks.js`
  - `./_acf-blocks-v3.js`
- `assets/src/js/pro/_acf-blocks.js` is the recovered source for legacy blocks.
- `assets/src/js/pro/_acf-blocks-v3.js` imports the recovered V3 modules under
  `assets/src/js/pro/blocks-v3/**`.

For ACF 6.8.0, the compiled upstream `acf-pro-blocks.min.js` includes block
editor behavior that SCF source does not fully expose as maintainable modules,
including:

- `expandedEditorButtons` mapped to `expanded_editor_buttons`
- `expandedEditorButtonText` mapped to `expanded_editor_button_text`
- controls for hiding the expanded editor button in the toolbar/sidebar
- custom expanded editor button text

The safest JS workflow is to decompile the upstream bundle, identify behavior,
then port it back into SCF source and rebuild.

## JS Decompilation Playbook

Pretty-print the upstream bundles for each side of the release diff:

```sh
git -C /Users/carlos/Developer/wp-plugins/advanced-custom-fields-pro show e2c878d:assets/build/js/pro/acf-pro-blocks.min.js | node_modules/.bin/prettier --parser babel > /tmp/acf-pro-blocks-6.7.2.js
git -C /Users/carlos/Developer/wp-plugins/advanced-custom-fields-pro show 602eb74:assets/build/js/pro/acf-pro-blocks.min.js | node_modules/.bin/prettier --parser babel > /tmp/acf-pro-blocks-6.8.0.js
diff -u /tmp/acf-pro-blocks-6.7.2.js /tmp/acf-pro-blocks-6.8.0.js > /tmp/acf-pro-blocks-6.8.0.diff
```

Search for stable strings, not minified variable names:

```sh
rg -n "expandedEditor|expanded_editor|blockToolbar|contenteditable|fetch-block|preloadedBlocks|Open Expanded" /tmp/acf-pro-blocks-6.8.0.js
```

Then map the behavior into SCF:

1. Port V3 behavior into real source under `assets/src/js/pro/blocks-v3/**`.
2. Port legacy block behavior into `assets/src/js/pro/_acf-blocks.js`.
3. Rebuild with `npm run build`.
4. Compare generated assets against expected behavior, not exact upstream bytes, because SCF has renamed strings, text domains, and additional bundles.

Useful pretty-print probes:

```sh
git -C /Users/carlos/Developer/wp-plugins/advanced-custom-fields-pro show 602eb74:assets/build/js/acf-field-group.min.js | node_modules/.bin/prettier --parser babel | rg -n "experimental|notes|field-type-icon|PRO Only|settings_tabs"
git -C /Users/carlos/Developer/wp-plugins/advanced-custom-fields-pro show 602eb74:assets/build/js/pro/acf-pro-blocks.min.js | node_modules/.bin/prettier --parser babel | rg -n "expandedEditor|expanded_editor|Open Expanded"
```

## 6.8.0 Port Log

Status in this worktree: ACF PRO `6.8.0` was ported from upstream range
`e2c878d..602eb74`. The later `6.8.0.1` commit at `ceb377b` has also been
reviewed and its functional block-rendering fix was ported.

Ported feature buckets:

- ACF Blocks v3 PHP rendering/cache changes, including split V3 render output,
  `blockToolbarFields`, inner block replacement, current block version tracking,
  and expanded editor button settings.
- Auto inline editing support for both render templates and render callbacks.
- Field type creation schema lookup via `acf_get_field_json_schema()`.
- Field type JSON-LD output type methods across the core field classes.
- Upstream field creation schemas under `schemas/fields/v1/**`.
- AI, Abilities, GEO, Site Health, and WP-CLI source trees under `src/**`.
- SCF autoload and bootstrap wiring for the new upstream namespaces.
- New upstream icons: `icon-experimental.svg` and `icon-tree-branch.svg`.
- Block editor JS support for `expanded_editor_buttons` and
  `expanded_editor_button_text`.

SCF adaptations made during the port:

- Public ACF-compatible API names were retained where upstream or third-party
  compatibility expects them.
- User-facing strings were moved to the `secure-custom-fields` text domain.
- WP-CLI registers both `acf json` and `scf json`.
- `enable_acf_ai` and `enable_schema` default to disabled.
- The copied upstream `src/**` files are kept close to source and carry a local
  PHPCS disable because the Schema.org data arrays and upstream style do not
  match this repository's standards profile.
- GEO block output keeps the block metadata key as `acf`, because that is the
  block.json compatibility namespace.

JavaScript notes:

- Upstream only ships compiled/minified bundles for this area.
- The 6.7.2 and 6.8.0 bundles were pretty-printed to `/tmp` and diffed.
- Stable strings such as `expandedEditorButtons`,
  `expandedEditorButtonText`, `expanded_editor_buttons`, and
  `Open Expanded Editor` were used to map the behavior back into SCF source.
- The active SCF block bundle now uses recovered source:
  `assets/src/js/pro/_acf-blocks.js` for legacy blocks and
  `assets/src/js/pro/blocks-v3/**` for V3 blocks.
- The old decompiled bundle source was replaced by the recovered legacy block
  source after `npm run build` succeeded against the recovered source path.
- `acf_enqueue_block_assets()` reads the generated
  `acf-pro-blocks*.asset.php` file so the recovered module build's WordPress
  package dependencies are enqueued.
- The V3 JSX parser keeps the legacy `acf.parseJSX()` behavior for v1/v2
  blocks and exposes the V3 parser as `acf.parseJSXV3`.

Validation observed:

- `composer dump-autoload` completed. Existing PSR-4 warnings for
  `SCF\Forms\` remain unrelated to this port.
- `php -l` passed for the touched bootstrap, block, auto-inline, field, and
  copied `src/**` PHP files.
- `vendor/bin/phpcs includes/blocks.php pro/blocks-auto-inline-editing.php`
  passed.
- Focused PHPCS over copied `src/**` entry points passed after applying local
  upstream-port disables.
- `npm run build` completed successfully.
- Focused JS lint passed for `acf-pro-blocks.js`, `_acf-blocks-v3.js`,
  `blocks-v3/components/jsx-parser.js`, and
  `blocks-v3/components/block-toolbar-fields.js`.
- Broad JS linting remains noisy in this area because the active block editor
  source still contains recovered V3 files that need style/global cleanup,
  especially `blocks-v3/components/block-edit.js`.
- Broad field-file PHPCS remains noisy because these field classes already do
  not match the stricter file/class documentation rules.

## 6.8.0.1 Port Log

The sibling upstream checkout has `6.8.0.1` at `ceb377b`. It changes only:

- `acf.php`
- `readme.txt`
- `pro/blocks.php`

Ported functional change:

- V3 block cache reads now only happen in preview/admin context, so two
  frontend blocks with identical attributes but different InnerBlocks content do
  not reuse the first block's rendered HTML.
- V3 block edit forms, validation, and field rendering now only happen in
  preview/admin context, so frontend rendering does not enqueue editor-only
  assets such as TinyMCE for WYSIWYG fields.

Skipped upstream release metadata:

- `acf.php` only bumps ACF PRO from `6.8.0` to `6.8.0.1`; SCF's plugin header
  is maintained separately and is currently `6.8.3`.
- `readme.txt` only adds the upstream ACF PRO changelog entry.
- `lang/**` changes are generated translation/catalog churn and should be
  handled by SCF's translation workflow, not copied from ACF PRO.

## Validation Checklist

After each port slice:

```sh
npm run build
npx wp-scripts lint-js
composer lint:php
composer test:phpstan
```

If PHP or E2E tests are needed, check wp-env first:

```sh
npm run wp-env status
```

Only start it if it is not already running:

```sh
npm run wp-env start
```

Then run targeted PHPUnit or E2E tests through the project scripts.

## Agent Skill

A repo-local skill for future agents lives at:

```text
.agents/skills/acf-release-porter/SKILL.md
```

Use it when the task is to compare, decompile, or port ACF release commits into
SCF.
