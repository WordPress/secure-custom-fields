#!/bin/bash

# Script to create a release zip file for Secure Custom Fields
# This script creates a zip file with only the necessary files for distribution

# Exit on error, undefined variables, and pipe failures
set -euo pipefail

# Show commands as they execute (comment out if too verbose)
# set -x

# Set script directory and define paths
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
OUTPUT_DIR="$PROJECT_ROOT/release"
ZIP_NAME="secure-custom-fields.zip"

cd "$PROJECT_ROOT"

# Check if git working directory is clean
if ! git diff-index --quiet HEAD --; then
    echo "Error: Git working directory is not clean. Commit or stash changes first."
    exit 1
fi

# Assert required files exist
echo "Validating required files..."
REQUIRED_FILES=(
    "secure-custom-fields.php"
    "acf.php"
    "index.php"
    "license.txt"
    "readme.txt"
    "composer.json"
    "SECURITY.md"
)

for file in "${REQUIRED_FILES[@]}"; do
    if [[ ! -f "$file" ]]; then
        echo "Error: Required file missing: $file"
        exit 1
    fi
done

# Assert required directories exist
REQUIRED_DIRS=(
    "includes"
    "assets"
    "lang"
    "pro"
)

for dir in "${REQUIRED_DIRS[@]}"; do
    if [[ ! -d "$dir" ]]; then
        echo "Error: Required directory missing: $dir"
        exit 1
    fi
done

# Check for unexpected development files in root
echo "Checking for development files..."
DISALLOWED_ITEMS=(
    "node_modules"
    ".github"
    "tests"
    "bin"
    ".git"
)

for item in "${DISALLOWED_ITEMS[@]}"; do
    if [[ -e "$item" && "$item" != ".git" ]]; then
        echo "Note: Development item present (will be excluded): $item"
    fi
done

# Verify no untracked files exist in source directories
if [[ -n $(git ls-files --others --exclude-standard includes/ pro/ | head -1) ]]; then
    echo "Error: Untracked files found in includes/ or pro/ directories"
    echo "Run 'git status' to see untracked files"
    exit 1
fi

# Create output directory
mkdir -p "$OUTPUT_DIR"

# Remove existing zip file if it exists
rm -f "$OUTPUT_DIR/$ZIP_NAME"

echo "Creating release zip: $ZIP_NAME"

# Create temporary directory for staging files
TEMP_DIR=$(mktemp -d)
trap 'rm -rf "$TEMP_DIR"' EXIT  # Clean up on exit
PLUGIN_DIR="$TEMP_DIR/secure-custom-fields"
mkdir -p "$PLUGIN_DIR"

# Copy core files
for file in "${REQUIRED_FILES[@]}"; do
    cp "$file" "$PLUGIN_DIR/"
done

# Copy directories
for dir in "${REQUIRED_DIRS[@]}"; do
    cp -r "$dir" "$PLUGIN_DIR/"
done

# Install production dependencies
echo "Installing production dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Copy vendor directory
cp -r vendor "$PLUGIN_DIR/"

# Remove empty vendor/bin directory if it exists
if [[ -d "$PLUGIN_DIR/vendor/bin" && -z "$(ls -A "$PLUGIN_DIR/vendor/bin")" ]]; then
    rm -rf "$PLUGIN_DIR/vendor/bin"
fi

# Create the zip file
cd "$TEMP_DIR"
zip -r "$OUTPUT_DIR/$ZIP_NAME" secure-custom-fields/ -q

# Verify zip integrity
echo "Verifying zip integrity..."
zip -T "$OUTPUT_DIR/$ZIP_NAME"

# Success summary
echo "✅ Release zip created: $OUTPUT_DIR/$ZIP_NAME"
echo "📦 Size: $(du -h "$OUTPUT_DIR/$ZIP_NAME" | cut -f1)"
echo "📁 Files: $(unzip -l "$OUTPUT_DIR/$ZIP_NAME" | tail -1 | awk '{print $2}')"
