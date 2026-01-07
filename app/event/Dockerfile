FROM php:8.4-zts

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
    pcntl

# Install watchexec for hot-reload (fallback to inotify-tools if needed)
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl gnupg \
    && curl -Ls https://github.com/watchexec/watchexec/releases/download/v2.3.2/watchexec-2.3.2-x86_64-unknown-linux-gnu.tar.xz -o /tmp/watchexec.tar.xz \
    && mkdir -p /tmp/watchexec \
    && tar -xf /tmp/watchexec.tar.xz -C /tmp/watchexec \
    && mv /tmp/watchexec/watchexec-*/watchexec /usr/local/bin/watchexec \
    && rm -rf /var/lib/apt/lists/* /tmp/watchexec /tmp/watchexec.tar.xz

RUN apt-get update -y && apt-get -y install git

# Clone GC2 from GitHub
RUN mkdir -p /var/www \
    && cd /var/www \
    && git clone https://github.com/mapcentia/geocloud2.git --branch connection

WORKDIR /var/www/geocloud2

RUN cd app \
    && php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php \
    && php composer.phar install --ignore-platform-req=ext-gd --ignore-platform-req=ext-dba \
    && php -r "unlink('composer-setup.php');"

COPY dev-entrypoint.sh /usr/local/bin/dev-entrypoint.sh
RUN chmod +x /usr/local/bin/dev-entrypoint.sh

EXPOSE 80

CMD ["/usr/local/bin/dev-entrypoint.sh"]
