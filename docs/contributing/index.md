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
   - The local environment runs with WP env, for setup, see: <https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/> along with prerequisites.
3. Install dependencies
   - run `npm install` and `composer install`
   - build the plugin files (JS/CSS) via `npm run build`
4. Run test suite
   - `composer run test`

## Building from Source

The repository contains the plugin source. The JavaScript and CSS that ship with the plugin are compiled from `assets/src` into `assets/build` using [`@wordpress/scripts`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/).

### Prerequisites

- Node.js >= 20.15.1 and npm >= 10.8.1 (see `engines` in `package.json`)
- PHP >= 7.4 and [Composer](https://getcomposer.org/)

### Build the plugin

```sh
npm install
composer install
npm run build
```

After this, the repository checkout is a working plugin: the compiled assets live in `assets/build`, and the checkout can be mapped into a WordPress install (e.g. via `wp-env`) as the `secure-custom-fields` plugin. During development, use `npm run watch` to rebuild assets on every change.

### Create a distribution zip

To produce the same zip that gets published on WordPress.org, run (from a clean git working directory):

```sh
./bin/create-release-zip.sh
```

The script verifies that all required files are present, installs production-only Composer dependencies, and creates `release/secure-custom-fields.zip` containing only the files needed for distribution.

### How releases reach WordPress.org

Releases are **not** deployed automatically — there is no GitHub Action that pushes builds to the WordPress.org plugin directory. A maintainer prepares the release on GitHub and then manually commits the distribution build to the WordPress.org SVN repository. The full process is documented in [Releasing a New Version](releases.md).

## Contribution Guidelines

- Follow WordPress coding standards
- Write unit tests for new features
- Document all changes
- Keep pull requests focused
