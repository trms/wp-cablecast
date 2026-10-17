#!/usr/bin/env bash
# Runs the PHPUnit suite inside the `tests` container (`make test`).
# WordPress core + test library are cached per WP version in the test-cache volume.
set -e

WP_VERSION=${WP_VERSION:-latest}
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')

export WP_CORE_DIR=/cache/wp/$WP_VERSION/core
export WP_TESTS_DIR=/cache/wp/$WP_VERSION/tests-lib

echo "PHP $PHP_VERSION / WordPress $WP_VERSION"

# composer.lock isn't committed, so re-resolve when the PHP version changes.
if [ "$(cat vendor/.php-version 2>/dev/null)" != "$PHP_VERSION" ]; then
    composer update --prefer-dist --no-progress --no-interaction
    echo "$PHP_VERSION" > vendor/.php-version
fi

if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
    bash bin/install-wp-tests.sh wordpress_test root root db "$WP_VERSION" true
fi

composer test -- "$@"
