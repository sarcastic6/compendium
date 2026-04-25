FROM dunglas/frankenphp

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN install-php-extensions \
    intl \
    zip \
    gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first (for layer caching)
COPY composer.json composer.lock ./

# Install dependencies
ENV APP_ENV=prod
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy the rest of the app
COPY . .

# Run post-install scripts now that full app is copied
RUN composer run-script post-install-cmd --no-dev

RUN php bin/console sass:build
RUN php bin/console asset-map:compile

# Set permissions
RUN chown -R www-data:www-data /app