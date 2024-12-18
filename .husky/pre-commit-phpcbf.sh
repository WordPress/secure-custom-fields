#!/bin/sh

# Disable exit on error since we handle exit codes manually
set +e

# Get list of staged PHP files
STAGED_PHP_FILES=$(git diff --cached --name-only --no-renames --diff-filter=d '*.php' )

# Exit if no PHP files are staged
if [ -z "$STAGED_PHP_FILES" ]; then
    exit 0
fi

echo "Running PHPCBF on staged files..."

# First try to fix what we can with PHPCBF
echo "$STAGED_PHP_FILES" | xargs vendor/bin/phpcbf

# Check the return code
PHPCBF_STATUS=$?

# If files were fixed, add them back to staging
if [ $PHPCBF_STATUS -ne 3 ]; then
    echo "$STAGED_PHP_FILES" | xargs git add

    if [ $PHPCBF_STATUS -eq 1 ] || [ $PHPCBF_STATUS -eq 2 ]; then
        echo ""
        echo "Some errors were fixed by PHPCBF, but not all. Continuing with phpcs-changed for remaining errors on lines changed by this PR."
    fi
fi

# Now run phpcs-changed to check only modified lines
echo "Checking modified lines with phpcs-changed..."
STAGED_FILES_SPACE_SEPARATED=$(echo "$STAGED_PHP_FILES" | tr '\n' ' ')

# Run phpcs-changed
vendor/bin/phpcs-changed\
    --git-staged\
    --phpcs-path=vendor/bin/phpcs\
    --standard=.phpcs.xml.dist\
    $STAGED_FILES_SPACE_SEPARATED

PHPCS_STATUS=$?

if [ $PHPCS_STATUS -ne 0 ]; then
    echo "⛔️ Found coding standards violations in changed lines. Please fix them before committing."
    exit 1
fi

echo "✅ All coding standards checks passed!"
exit 0