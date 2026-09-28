FROM richarvey/nginx-php-fpm:latest

COPY . /var/www/html
WORKDIR /var/www/html

ENV SKIP_COMPOSER 1
ENV WEBROOT /var/www/html/public
ENV PHP_ERRORS_STDERR 1
ENV RUN_SCRIPTS 1
ENV REAL_IP_HEADER 1

ENV APP_ENV production
ENV APP_DEBUG false
ENV LOG_CHANNEL stderr

ENV COMPOSER_ALLOW_SUPERUSER 1

# ✅ Overwrite nginx's default config BEFORE starting
RUN echo '#!/bin/bash\n\
cp /var/www/html/conf/nginx/site.conf /etc/nginx/sites-available/default.conf\n\
ln -sf /etc/nginx/sites-available/default.conf /etc/nginx/sites-enabled/default.conf\n\
/start.sh' > /custom-start.sh && chmod +x /custom-start.sh

CMD ["/custom-start.sh"]