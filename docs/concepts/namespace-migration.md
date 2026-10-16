# Namespace and API Naming

Secure Custom Fields is based on the ACF codebase. Existing ACF names are part of the public compatibility surface in different ways, so migration must distinguish public APIs from internal implementation details.

The current production code contains hundreds of `acf_` functions and `acf/` hooks, while only a small number use `scf_` or `scf/`. A bulk rename would make security backports and compatibility testing harder.

## Current policy

- Keep existing public `acf_` functions, `acf/` hooks, ACF class names, and ACF constants stable.
- Use the `scf_` prefix for new procedural functions.
- Use the `scf/` prefix for new hooks and filters.
- Use a mapped `SCF\\...` namespace for new namespaced classes. Add the PSR-4 mapping in `composer.json` before adding a new namespace, or load the class explicitly through the plugin loader.
- Treat existing global `SCF_` classes as grandfathered code. Do not add new global `SCF_` classes unless the loader and compatibility reason are documented.
- Keep the existing plugin slug and main plugin filenames. The `acf.php` compatibility shim and `secure-custom-fields.php` entry point support existing installations and integrations.
- Internal files may be renamed only after checking direct includes, Composer mappings, documentation links, release tooling, and third-party integration risk. Plugin entry points, compatibility shims, and documented integration paths are protected.

This policy avoids breaking themes, plugins, and site code that call the current API directly or check plugin paths.

## Public API changes

A new SCF API must be reviewed before it is added alongside an existing ACF API. The proposal must state whether it is:

1. A new SCF API with no ACF equivalent.
2. A wrapper around an existing ACF API.
3. A replacement for an existing ACF API.

Replacement APIs must not remove or change the behavior of the ACF API in the same release. If an SCF wrapper is added, it must preserve the existing arguments, return values, errors, capability checks, sanitization, escaping, and hook behavior unless the change is documented as intentional.

## Hooks and filters

New SCF hooks should be introduced only when they represent a new SCF extension point. For an existing ACF hook, keep the ACF hook as the compatibility path. If both hooks must fire, document their order and test that callbacks are not run twice when a callback is registered through both names.

## Migration scope and decision criteria

Before changing a name, classify it in an inventory as one of the following:

- **Protected entry point:** plugin slug, `acf.php`, `secure-custom-fields.php`, activation paths, and update paths. These are not rename candidates.
- **Public API:** documented functions, hooks, filters, classes, constants, REST routes, and integration paths. These require a compatibility plan and tests for old and new names.
- **Internal implementation:** code used only by the plugin. This may be renamed when direct includes, autoloading, documentation, release tooling, and security backports have been checked.
- **Compatibility code:** shims, aliases, deprecated APIs, and migration helpers. These remain until their removal has an explicit version and upgrade plan.

A migration proposal should include an old-name to new-name mapping, the affected files, the supported versions, the deprecation period, and tests for both names. The proposal should be rejected when the rename provides little user benefit but increases the risk of broken integrations or makes security patches harder to backport.

## Staged migration

Namespace migration is gradual:

1. Use SCF names for new code.
2. Keep existing ACF names as stable public APIs.
3. Add aliases or SCF wrappers only for a specific API with a documented compatibility need.
4. Add tests for both names before changing a public API.
5. Track compatibility reports and adoption before proposing deprecation.
6. Consider internal filename changes separately from public API changes.
7. Reassess the migration after security backport and upgrade testing.

A legacy mode could provide a site-level switch for new hook names, but it would also create two execution paths, make hook ordering harder to reason about, and increase the chance that a security fix reaches one path but not the other. It should not be introduced unless the project can define its scope, default behavior, upgrade path, and test coverage. There is no general legacy mode or bulk rename until that roadmap is agreed.

## Security and backports

The ACF and SCF implementations should remain easy to compare while they share behavior. Security fixes should be made in the smallest common code path where possible. When separate SCF and ACF paths are required, add tests that cover both paths and describe the reason for the divergence.

Changes to public names, plugin loading, or compatibility shims require review for:

- Direct function and class calls from third-party code.
- Hook and filter registrations.
- Plugin activation and update checks.
- Sites moving from ACF to SCF.
- Backporting security fixes between related code paths.
