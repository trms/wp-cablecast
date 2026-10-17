ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-cli

# Older PHP images (7.4, 8.0) are built on Debian bullseye, which is EOL and
# only available from the archive.
RUN if grep -q bullseye /etc/os-release; then \
        sed -i -e 's|deb.debian.org|archive.debian.org|g' -e '/bullseye-updates/d' /etc/apt/sources.list; \
    fi

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip subversion \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install mysqli

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
