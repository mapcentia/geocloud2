FROM php:8.3-zts

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
	redis \
	zip \
	pgsql \
	pdo_pgsql \
    pq \
    uv \
	opcache \
	zmq \
    parallel \
    pcntl \
    xdebug

CMD ["/usr/local/bin/php", "-f", "/var/www/geocloud2/app/event/main.php"]
