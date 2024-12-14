FROM php:7.2-apache
RUN apt-get update && apt-get install -y \
        nano \
        cron \
        libfreetype6-dev \
        libzip-dev \
        libc-client-dev \
        libkrb5-dev \
    && rm -r /var/lib/apt/lists/* \
    && docker-php-ext-configure zip --with-libzip \
    && docker-php-ext-configure pdo_mysql \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install -j$(nproc) zip pdo_mysql imap

RUN crontab -l > mwcron || true \
    && echo "* * * * * /usr/local/bin/php -q /var/www/html/apps/console/console.php send-campaigns >/dev/null 2>&1" >> mwcron \
    && echo "*/2 * * * * /usr/local/bin/php -q /var/www/html/apps/console/console.php send-transactional-emails >/dev/null 2>&1" >> mwcron \
    && echo "*/10 * * * * /usr/local/bin/php -q /var/www/html/apps/console/console.php bounce-handler >/dev/null 2>&1" >> mwcron \
    && echo "*/20 * * * * /usr/local/bin/php -q /var/www/html/apps/console/console.php feedback-loop-handler >/dev/null 2>&1" >> mwcron \
    && echo "*/3 * * * * /usr/local/bin/php -q /var/www/html/apps/console/console.php process-delivery-and-bounce-log >/dev/null 2>&1" >> mwcron \
    && echo "0 * * * * /usr/local/bin/php -q /var/www/html/apps/console/console.php hourly >/dev/null 2>&1" >> mwcron \
    && echo "0 0 * * * /usr/local/bin/php -q /var/www/html/apps/console/console.php daily >/dev/null 2>&1" >> mwcron \
    && crontab mwcron \
    && rm mwcron