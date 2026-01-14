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
   - run `composer install`
   - build the plugin files (JS/CSS) via `npm run build`
4. Run test suite
   - `composer run test`

## Contribution Guidelines

- Follow WordPress coding standards
- Write unit tests for new features
- Document all changes
- Keep pull requests focused

## Contributor Acknowledgement

All contributors are automatically tracked and acknowledged in [`CONTRIBUTORS.md`](/CONTRIBUTORS.md). The system recognizes:

- **Commits**: Authors of merged commits
- **Reviews**: Pull request reviewers
- **Comments**: Pull request commenters
- **Issues**: Authors of issues that are closed by merged PRs

### How It Works

1. **Props Bot**: Posts a comment on each PR listing contributors (runs while PRs are open)
2. **Release Process**: During releases, the Update Contributors workflow is manually triggered to refresh `contributors.json` from the GitHub API and generate `CONTRIBUTORS.md` and `readme.txt`

### Linking Your WordPress.org Account

To have your WordPress.org username appear alongside your GitHub username, link your accounts at [WordPress.org profile settings](https://profiles.wordpress.org/me/profile/edit/).

### Known Limitations

- **PR History Limit**: The contributor tracking system processes up to 10,000 merged pull requests (100 pages of 100 PRs). For repositories exceeding this threshold, older PR contributors (reviewers, commenters, issue reporters) may not be captured. Commit authors are not affected by this limit.
