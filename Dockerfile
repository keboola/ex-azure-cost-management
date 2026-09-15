FROM php:7.4-cli

ARG COMPOSER_FLAGS="--prefer-dist --no-interaction"
ARG DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_PROCESS_TIMEOUT 3600

WORKDIR /code/

COPY docker/php-prod.ini /usr/local/etc/php/php.ini
COPY docker/composer-install.sh /tmp/composer-install.sh

# php:7.4-cli is EOL and its pinned Debian bullseye security packages (git, locales and their
# dependencies) now 404 on the mirror. composer --prefer-dist needs only unzip, and the component
# uses no locale, so git and locales are dropped. The list cache is cleared first, so apt reads a
# fresh index instead of the stale one baked into the base image.
RUN rm -rf /var/lib/apt/lists/* \
	&& apt-get update \
	&& apt-get install -y --no-install-recommends unzip \
	&& rm -rf /var/lib/apt/lists/* \
	&& chmod +x /tmp/composer-install.sh \
	&& /tmp/composer-install.sh

## Composer - deps always cached unless changed
# First copy only composer files
COPY composer.* /code/

# Download dependencies, but don't run scripts or init autoloaders as the app is missing
RUN composer install $COMPOSER_FLAGS --no-scripts --no-autoloader

# Copy rest of the app
COPY . /code/

# Run normal composer - all deps are cached already
RUN composer install $COMPOSER_FLAGS

CMD ["php", "/code/src/run.php"]
