#!/bin/bash

# Script to create a release zip file for Secure Custom Fields
# This script creates a zip file with only the necessary files for distribution

# Set script directory and define paths
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$SCRIPT_DIR"
OUTPUT_DIR="$PROJECT_ROOT/release"
ZIP_NAME="secure-custom-fields.zip"

# Create output directory if it doesn't exist
mkdir -p "$OUTPUT_DIR"

# Remove existing zip file if it exists
if [ -f "$OUTPUT_DIR/$ZIP_NAME" ]; then
    echo "Removing existing zip file..."
    rm "$OUTPUT_DIR/$ZIP_NAME"
fi

echo "Creating release zip file: $ZIP_NAME"
echo "Output directory: $OUTPUT_DIR"

# Create temporary directory for staging files
TEMP_DIR=$(mktemp -d)
PLUGIN_DIR="$TEMP_DIR/secure-custom-fields"
mkdir -p "$PLUGIN_DIR"

echo "Staging files in temporary directory: $TEMP_DIR"

# Copy files to staging directory
# Note: Using the current project files instead of the SVN paths mentioned
# since we're working in the main project directory

# Copy individual files
echo "Copying core files..."
cp "$PROJECT_ROOT/acf.php" "$PLUGIN_DIR/" 2>/dev/null || echo "Warning: acf.php not found"
cp "$PROJECT_ROOT/composer.json" "$PLUGIN_DIR/" 2>/dev/null || echo "Warning: composer.json not found"
cp "$PROJECT_ROOT/index.php" "$PLUGIN_DIR/" 2>/dev/null || echo "Warning: index.php not found"
cp "$PROJECT_ROOT/license.txt" "$PLUGIN_DIR/" 2>/dev/null || echo "Warning: license.txt not found"
cp "$PROJECT_ROOT/readme.txt" "$PLUGIN_DIR/" 2>/dev/null || echo "Warning: readme.txt not found"
cp "$PROJECT_ROOT/secure-custom-fields.php" "$PLUGIN_DIR/" 2>/dev/null || echo "Warning: secure-custom-fields.php not found"
cp "$PROJECT_ROOT/SECURITY.md" "$PLUGIN_DIR/" 2>/dev/null || echo "Warning: SECURITY.md not found"

# Copy directories
echo "Copying directories..."
if [ -d "$PROJECT_ROOT/assets" ]; then
    cp -r "$PROJECT_ROOT/assets" "$PLUGIN_DIR/"
    echo "✓ Copied assets directory"
else
    echo "Warning: assets directory not found"
fi

if [ -d "$PROJECT_ROOT/includes" ]; then
    cp -r "$PROJECT_ROOT/includes" "$PLUGIN_DIR/"
    echo "✓ Copied includes directory"
else
    echo "Warning: includes directory not found"
fi

if [ -d "$PROJECT_ROOT/lang" ]; then
    cp -r "$PROJECT_ROOT/lang" "$PLUGIN_DIR/"
    echo "✓ Copied lang directory"
else
    echo "Warning: lang directory not found"
fi

if [ -d "$PROJECT_ROOT/pro" ]; then
    cp -r "$PROJECT_ROOT/pro" "$PLUGIN_DIR/"
    echo "✓ Copied pro directory"
else
    echo "Warning: pro directory not found"
fi

# Install production dependencies before copying vendor
echo "Installing production dependencies..."
if [ -f "$PROJECT_ROOT/composer.json" ]; then
    cd "$PROJECT_ROOT"
    composer install --no-dev --optimize-autoloader --no-interaction
    if [ $? -eq 0 ]; then
        echo "✓ Composer install completed successfully"
    else
        echo "Warning: Composer install failed"
    fi
else
    echo "Warning: composer.json not found, skipping composer install"
fi

if [ -d "$PROJECT_ROOT/vendor" ]; then
    cp -r "$PROJECT_ROOT/vendor" "$PLUGIN_DIR/"
    echo "✓ Copied vendor directory"
    
    # Remove empty vendor/bin directory if it exists
    if [ -d "$PLUGIN_DIR/vendor/bin" ] && [ -z "$(ls -A "$PLUGIN_DIR/vendor/bin")" ]; then
        rm -rf "$PLUGIN_DIR/vendor/bin"
        echo "✓ Removed empty vendor/bin directory"
    fi
else
    echo "Warning: vendor directory not found"
fi

# Create the zip file
echo "Creating zip file..."
cd "$TEMP_DIR"
zip -r "$OUTPUT_DIR/$ZIP_NAME" secure-custom-fields/ -q

# Clean up temporary directory
echo "Cleaning up temporary files..."
rm -rf "$TEMP_DIR"

# Check if zip was created successfully
if [ -f "$OUTPUT_DIR/$ZIP_NAME" ]; then
    echo "✅ Success! Release zip created at: $OUTPUT_DIR/$ZIP_NAME"
    echo "📦 Zip file size: $(du -h "$OUTPUT_DIR/$ZIP_NAME" | cut -f1)"
    echo ""
    echo "Contents of the zip file:"
    unzip -l "$OUTPUT_DIR/$ZIP_NAME" | head -20
    echo "..."
    echo "Total files: $(unzip -l "$OUTPUT_DIR/$ZIP_NAME" | grep -c "secure-custom-fields/")"
else
    echo "❌ Error: Failed to create zip file"
    exit 1
fi
