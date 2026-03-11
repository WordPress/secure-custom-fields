# Releasing a New Version

Read the following guide to learn how to created and publish a new release of Secure Custom Fields.

## Prerequisites

- Clean git working directory (no uncommitted changes)
- Required tools installed: `npm`, `composer`, `gh` (GitHub CLI)
- Push access to the GitHub repository
- Commit access to the `secure-custom-fields` plugin in the WP.org SVN repository

## SVN Checkout

You will need a local checkout of the `secure-custom-fields` plugin from the WP.org SVN repository, in a directory separate from your clone of the GitHub repo. For example, use the following command to check out the SVN repo into `secure-custom-fields-svn`:

```sh
svn co https://plugins.svn.wordpress.org/secure-custom-fields secure-custom-fields-svn
```

Using SVN for WordPress.org plugin releases is covered in more detail in https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/.

## Changelog

Before running the `prepare-release` script (see below), ensure the changelog entry for the new version exists in `readme.txt`:

```
== Changelog ==

= X.Y.Z =
* Feature: Description of new feature
* Fix: Description of bug fix
```

## Prepare the Release

Create a new branch from `trunk` and run the release preparation script:

```sh
git checkout trunk && git pull
git checkout -b release/x.y.z
composer prepare-release
```

This will run an interactive script that will:

* Build the assets required for the release
* Generate documentation and update translations
* Commit these changes to the current branch
* Prompt the user for the new version number and update it accordingly in `secure-custom-fields.php`
* Offer the user to update the stable tag in `readme.txt`
* Offer the user to create a PR (so the changes can be merged back to `trunk`)

Maintainers will typically opt to update the stable tag manually at a later time.

## Create the Distribution Zip

From the `release/x.y.z` branch, run:

```sh
./bin/create-release-zip.sh
```

This script runs a few checks to verify that all required files and directories are present, and that there are no untracked files. It then installs production dependencies and creates and verifies `release/secure-custom-fields.zip`.

## Deploy to WordPress.org

This step is manual — there is no automated deployment workflow.

### Prepare the SVN commit

Unzip the newly created `release/secure-custom-fields.zip` file, and copy its contents over to the `trunk` folder in your local copy of the SVN repo (e.g. `secure-custom-fields-svn/trunk/` per the "SVN Checkout" section).

Then, `cd` into `secure-custom-fields-svn`, and run `svn status`. This will show the list of files that had changes (prefixed with an `M`) or aren't under version control yet (`?`). You'll want to add the latter by running `svn add <filename>`, upon which the file's status will change to `A` (scheduled for addition).

More [information on the `svn status` command](https://svnbook.red-bean.com/en/1.7/svn.ref.svn.c.status.html) is available from the SVN handbook.

### Create the new tag locally

Once your files in the `trunk` directory are ready (i.e. no more `?` or `!` statuses), you need to locally create the new tag by copying `trunk` to `tags/x.y.z`. (Replace `x.y.z` with the version you're about to release.)

```sh
svn cp trunk tags/x.y.z
```

### Committing

Run a final `svn status` to see a list of all changes scheduled for commit. It should list both files in `trunk`, and in `tags/x.y.z`.

Then, commit the files to the remote SVN repository by running

```sh
svn ci -m “Prepare release x.y.z”
```

### Set rollout strategy

After committing, you will receive an email asking you to select the rollout strategy for the new version of the plugin. Follow the instructions in that email. You can choose between "Immediate (default)" and "Manual updates only (24 hours)". You might want to select the latter option, as it gives you some more time to ask others to manually download and test the plugin from https://wordpress.org/plugins/secure-custom-fields/.

Find more information about phased plugin rollout at https://make.wordpress.org/plugins/2025/08/11/plugin-rollout-phased-releases/.

### Set stable tag

Don't forget to update the stable tag in `readme.txt`. You need to do so both in the git repository, and in SVN.

In SVN, simply edit `readme.txt` in `trunk`, and commit the change.

In git, push the commit to the previosly created `release/x.y.z` branch, and push it to GitHub. You can then use [GitHub's Releases UI](https://github.com/WordPress/secure-custom-fields/releases) to create a new `x.y.z` tag. Select the version bump commit on the `release/x.y.z` branch as the target. For the release notes, simply copy the changelog for the new version.

Finally, merge the PR you created earlier for the `release/x.y.z` branch in order to update `trunk` with those changes.