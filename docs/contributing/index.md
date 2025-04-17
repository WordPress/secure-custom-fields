# Contributing to SCF

Guide for contributing to Secure Custom Fields development.

## Ways to Contribute

1. **Code Contributions**
   - Bug fixes
   - New features
   - Performance improvements
   - Security enhancements

2. **Documentation**
   - Writing tutorials
   - Improving reference docs
   - Fixing errors
   - Adding examples

3. **Testing**
   - Unit testing
   - Integration testing
   - Bug reporting
   - Feature validation

## Development Setup

1. Fork the repository
2. Set up local environment
   - The local environment runs with WP env, for setup, see: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/ along with prerequisites.
3. Install dependencies
   - run `composer install`
   - build the plugin files (JS/CSS) via `npm run build`
4. Run test suite
   - `composer run test`

## Release Management

There are some prerequisites for a release:
1. You will need npm, composer and GH cli (https://github.com/cli/cli?tab=readme-ov-file#installation) installed.
2. Use the scripts provided `composer prepare-release` (which includes `update-translations`) or `composer update-translations` to prepare the release.
3. Follow the instructions in the script to create a release.

## Contribution Guidelines

- Follow WordPress coding standards
- Write unit tests for new features
- Document all changes
- Keep pull requests focused
