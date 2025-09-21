# AGENTS.md

## Dev environment tips

```bash
# Setup
npm install && composer install
npx wp-env start # Start WordPress environment
npx wp-env status # Check if wp-env running

# Development
npm run watch # Development with watch
npm run build # Production build
```

### Key Directories

-   `/includes/` - Core PHP functionality
-   `/assets/src/` - Frontend source files
-   `/assets/build/` - Compiled assets
-   `/tests/` - E2E and PHPUnit tests

## Testing instructions

> **Note**: PHP/E2E tests require wp-env running.

```bash
# PHP (requires wp-env)
composer test # All PHP tests (PHPUnit + PHPStan)
composer test:php # PHPUnit tests only
composer test:php -- --filter=Test_REST_Types_Endpoint # Specific test
vendor/bin/phpunit tests/php/includes/forms/ # Test directory
vendor/bin/phpunit # PHPUnit directly
composer test:phpstan # Static analysis only

# E2E (requires wp-env)
npm run test:e2e
npm run test:e2e:debug # Debug mode
npm run test:e2e -- --headed # Run with browser visible
npm run test:e2e -- tests/e2e/specs/field-groups.spec.js # Specific test

# Code Quality
npm run lint:js # Check JavaScript linting
npm run fix:js # Fix JavaScript formatting
composer lint:php # Check PHP standards
vendor/bin/phpcs # Check PHP standards
vendor/bin/phpcbf # Fix PHP standards

# Specific files
vendor/bin/phpcbf includes/class-acf.php
```

## Code patterns

- **Naming**: New functions use `scf_` prefix and hooks use `scf/hook_name`, existing use `acf_` and `acf/hook_name` (backward compat)  
- **Internationalization**: Use `__()`, `_e()` with text domain `'secure-custom-fields'`
- **Output escaping**: Always escape with `esc_html()`, `esc_attr()`, `esc_url()`
- **Input sanitization**: Use `sanitize_text_field()`, `sanitize_file_name()`

## PR instructions

-   Ensure build passes
-   Fix all formatting/linting issues, these are enforced through CI in PRs
-   Run relevant tests
