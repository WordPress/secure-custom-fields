#!/bin/bash
#
# Install PCOV extension in wp-env Docker container for faster PHP coverage.
# This script is called by wp-env lifecycleScripts.afterStart.
#
# PCOV is ~5-10x faster than Xdebug for coverage collection.

set -e

# Only install if PHP_COVERAGE_ENABLED is set
if [ -z "$PHP_COVERAGE_ENABLED" ]; then
    echo "PHP_COVERAGE_ENABLED not set, skipping PCOV installation."
    exit 0
fi

echo "Installing PCOV for PHP coverage..."

# Get the wp-env install path
WP_ENV_PATH=$(wp-env install-path)
cd "$WP_ENV_PATH"

# Check if PCOV is already installed
if docker compose exec -T tests-wordpress php -m 2>/dev/null | grep -q "^pcov$"; then
    echo "PCOV already installed."
    exit 0
fi

echo "Installing PCOV extension..."

# Install PCOV using pecl (run as root)
docker compose exec -T -u root tests-wordpress bash -c '
    # Install dependencies for pecl
    apt-get update -qq && apt-get install -qq -y autoconf gcc make > /dev/null 2>&1 || true

    # Install PCOV
    pecl install pcov > /dev/null 2>&1

    # Enable PCOV
    docker-php-ext-enable pcov

    # Configure PCOV
    echo "pcov.enabled=1" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini
    echo "pcov.directory=/var/www/html/wp-content/plugins/secure-custom-fields" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini

    # Disable Xdebug if enabled (they conflict)
    if [ -f /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini ]; then
        mv /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini.disabled || true
    fi
'

# Restart Apache to load the new extension
docker compose exec -T -u root tests-wordpress service apache2 reload > /dev/null 2>&1 || true

echo "PCOV installed successfully."
