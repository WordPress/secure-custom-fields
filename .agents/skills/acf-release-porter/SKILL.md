---
name: acf-release-porter
description: Port Advanced Custom Fields PRO release commits into Secure Custom Fields, including PHP diffs, SCF naming/text-domain adaptation, schema and abilities mapping, compiled/minified JavaScript decompilation, asset rebuilds, and release-parity verification. Use when Codex is asked to copy, compare, decompile, backport, or make SCF 1:1 with a specific ACF version or commit.
---

# ACF Release Porter

## Overview

Use this skill to move an upstream Advanced Custom Fields PRO release into
Secure Custom Fields without losing SCF-specific architecture, names, security
hardening, or local user changes.

## First Moves

- Check both worktrees and current dirty state:

```sh
git status --short
git -C /Users/carlos/Developer/wp-plugins/advanced-custom-fields-pro status --short
```

- Identify upstream release commits from commit messages:

```sh
git -C /Users/carlos/Developer/wp-plugins/advanced-custom-fields-pro log --oneline --decorate -n 40
```

- Build the release diff from the previous upstream version to the target
  version. Do not infer parity from SCF tags alone.

## Porting Workflow

- Generate a high-level upstream summary:

```sh
git -C /Users/carlos/Developer/wp-plugins/advanced-custom-fields-pro diff --stat OLD_SHA NEW_SHA
git -C /Users/carlos/Developer/wp-plugins/advanced-custom-fields-pro diff --name-status OLD_SHA NEW_SHA -- ':(exclude)lang/**' ':(exclude)vendor/**'
```

- Classify changes into buckets:
  - simple PHP behavior changes
  - schema and abilities changes
  - new features needing SCF naming/product decisions
  - generated assets
  - translations, which must be skipped
  - vendor/autoload churn

- Do not port ACF PRO translation/catalog files from `lang/**`. SCF handles
  translations through its own workflow, so upstream language churn should be
  recorded as skipped unless the user explicitly asks for translation work.

- Port PHP in small slices. Keep public `acf_` APIs where backward
  compatibility requires them. Prefer `scf_` for new SCF-only APIs. Use
  `secure-custom-fields` as the text domain. Preserve SCF's existing
  procedural/classic PHP architecture unless the repo has already adopted an
  upstream namespace for that area.

- Map upstream schemas and abilities into SCF's existing schema system before
  adding new schema directories. In this repo, SCF uses
  `schemas/field-fragments/**`, `schemas/field.schema.json`,
  `SCF_Schema_Builder`, and `includes/abilities/**`.

- Treat AI/GEO and WP-CLI as explicit feature ports, not mechanical file
  copies. Decide whether names, settings, commands, hooks, and output strings
  should remain `acf` for compatibility or become `scf`.

## JavaScript Decompilation

Upstream ACF PRO may only provide minified bundles. Pretty-print both sides of
the upstream release and diff the readable output:

```sh
git -C /Users/carlos/Developer/wp-plugins/advanced-custom-fields-pro show OLD_SHA:assets/build/js/pro/acf-pro-blocks.min.js | node_modules/.bin/prettier --parser babel > /tmp/acf-pro-blocks-old.js
git -C /Users/carlos/Developer/wp-plugins/advanced-custom-fields-pro show NEW_SHA:assets/build/js/pro/acf-pro-blocks.min.js | node_modules/.bin/prettier --parser babel > /tmp/acf-pro-blocks-new.js
diff -u /tmp/acf-pro-blocks-old.js /tmp/acf-pro-blocks-new.js > /tmp/acf-pro-blocks.diff
```

Search by stable strings and public API names, not minified variable names:

```sh
rg -n "expandedEditor|expanded_editor|contenteditable|blockToolbar|fetch-block|preloadedBlocks|Open Expanded" /tmp/acf-pro-blocks-new.js
```

In SCF, inspect the active bundle entry before editing:

```sh
sed -n '1,80p' assets/src/js/pro/acf-pro-blocks.js
sed -n '1,120p' assets/src/js/pro/_acf-blocks.js
sed -n '1,120p' assets/src/js/pro/_acf-blocks-v3.js
```

Prefer to port behavior into maintainable source under `assets/src/js/**`.
Legacy block behavior should go into `assets/src/js/pro/_acf-blocks.js`, and
V3 behavior should go into `assets/src/js/pro/blocks-v3/**`. The
`_acf-blocks.js` file is recovered source now; do not replace it with a
pretty-printed compiled bundle unless the user explicitly asks for a fallback
comparison artifact.

## Validation

Run the narrowest useful checks first, then broaden:

```sh
npm run build
npx wp-scripts lint-js
composer lint:php
composer test:phpstan
```

For PHP or E2E tests, check wp-env status before starting it:

```sh
npm run wp-env status
```

Use the repository scripts for E2E. Do not run `npx playwright test`
directly.

## Reporting

When reporting back, include:

- upstream range and target version
- files or feature buckets ported
- upstream features intentionally deferred
- confirmation that `lang/**` translation churn was skipped
- JS decompilation method used
- validation commands and results
