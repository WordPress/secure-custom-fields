# AGENTS.md

## Repository info

-   Default branch: `trunk`
-   PRs target `trunk` unless stated otherwise

## Dev environment tips

```bash
# Setup
npm install && composer install
npm run wp-env status   # Always check status first
npm run wp-env start    # Only start if not already running

# Development
npm run watch           # Development with watch
npm run build          # Production build
```

### Key Directories

-   `/includes/` - Core PHP functionality
-   `/assets/src/` - Frontend source files
-   `/assets/build/` - Compiled assets
-   `/tests/` - E2E and PHPUnit tests
-   `/docs/` - Documentation

## Testing instructions

> **Note**: PHP/E2E tests require wp-env running.

```bash
# PHP (requires wp-env)
composer test             # All PHP tests (PHPUnit + PHPStan)
composer test:php         # PHPUnit tests only
composer test:php -- --filter=<TestName>  # Specific test
vendor/bin/phpunit <path_to_test_file.php>  # Specific file
vendor/bin/phpunit <path_to_test_directory>/              # Directory
composer test:phpstan     # Static analysis only

# JavaScript unit tests
npm run test:unit         # Jest unit tests
npm run test:unit:watch   # Jest in watch mode

# End-to-end tests (Playwright, requires wp-env)
# IMPORTANT: NEVER run `npx playwright test` directly — always use these npm scripts:
npm run test:e2e                               # All E2E tests
npm run test:e2e:debug                         # Debug mode
npm run test:e2e -- --headed                   # Run with browser visible
npm run test:e2e -- <path_to_test_file.spec.js>  # Specific test file

# Code Quality
npx wp-scripts lint-js    # Check JavaScript linting
npx wp-scripts format     # Fix JavaScript formatting
composer lint:php         # Check PHP standards (phpcs)
composer format:php       # Fix PHP standards (phpcbf)

# Specific files
vendor/bin/phpcs <path_to_php_file.php>   # Check specific file
vendor/bin/phpcbf <path_to_php_file.php>  # Fix specific file
```

## Code patterns

- **Naming**: New functions use `scf_` prefix and hooks use `scf/hook_name`, existing use `acf_` and `acf/hook_name` (backward compat)
- **Internationalization**: Use `__()`, `_e()` with text domain `'secure-custom-fields'`
- **Output escaping**: Always escape with `esc_html()`, `esc_attr()`, `esc_url()`
- **Input sanitization**: Use `sanitize_text_field()`, `sanitize_file_name()`

## PR instructions

-   Target branch: `trunk`
-   Always use the pull request template at `.github/PULL_REQUEST_TEMPLATE.md` and fill out all sections
-   Ensure build passes
-   Fix all formatting/linting issues; these are enforced through CI in PRs
