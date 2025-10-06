# PROD
FROM hyperf/hyperf:8.3-alpine-v3.19-swoole AS prod

ARG timezone
ENV TIMEZONE=${timezone:-"Asia/Shanghai"} \
    APP_ENV=prod \
    SCAN_CACHEABLE=true

RUN set -ex \
 && php -v \
 && php -m \
 && php --ri swoole \
 && cd /etc/php* \
 && { \
      echo "upload_max_filesize=128M"; \
      echo "post_max_size=128M"; \
      echo "memory_limit=1G"; \
      echo "date.timezone=${TIMEZONE}"; \
    } | tee conf.d/99_overrides.ini \
 && ln -sf /usr/share/zoneinfo/${TIMEZONE} /etc/localtime \
 && echo "${TIMEZONE}" > /etc/timezone \
 && rm -rf /var/cache/apk/* /tmp/* /usr/share/man

WORKDIR /opt/www
COPY . /opt/www
RUN composer install --no-dev -o

EXPOSE 9501
ENTRYPOINT ["php", "/opt/www/bin/hyperf.php", "start"]

# TEST
FROM hyperf/hyperf:8.3-alpine-v3.19-swoole AS test

RUN set -ex \
  && apk add --no-cache php83-pecl-pcov \
  && PCONFDIR="$(ls -d /etc/php*/conf.d | head -n1)" \
  && { echo "extension=pcov.so"; echo "pcov.enabled=1"; echo "pcov.directory=/opt/www"; echo "pcov.exclude='~vendor~|~test~|~runtime~'"; } \
     | tee "${PCONFDIR}/zz-pcov.ini" >/dev/null


WORKDIR /opt/www
COPY . /opt/www
RUN composer install -o

CMD ["vendor/bin/co-phpunit","--coverage-html","build/coverage","--coverage-clover","build/logs/clover.xml"]
