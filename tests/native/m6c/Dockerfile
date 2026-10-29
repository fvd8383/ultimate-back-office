# Preparation only. Never built/pulled by RunM6CNative.sh.
# Required: PHP_BASE=php@sha256:<operator-verified digest>, SOURCE_SHA=<exact commit>.
ARG PHP_BASE
FROM ${PHP_BASE}
ARG PHP_BASE
ARG SOURCE_SHA
RUN printf '%s\n' "$PHP_BASE" | grep -Eq '^php@sha256:[a-f0-9]{64}$' \
    && printf '%s\n' "$SOURCE_SHA" | grep -Eq '^[a-f0-9]{40}$' \
    && apt-get update \
    && apt-get install -y --no-install-recommends git util-linux libxml2-dev libonig-dev \
    && for extension in pdo_mysql dom mbstring pcntl; do \
         if ! php -r 'exit(extension_loaded($argv[1]) ? 0 : 1);' "$extension"; then docker-php-ext-install -j1 "$extension"; fi; \
       done \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /opt/ubo /var/lib/ubo-m6c /run/m6c \
    && chmod 0755 /opt /opt/ubo /var/lib/ubo-m6c /run/m6c \
    && php -r 'foreach(["pdo_mysql","dom","mbstring","pcntl","posix","filter","fileinfo"] as $e) if(!extension_loaded($e)) exit(2); foreach(["fsync","getimagesizefromstring","proc_open"] as $f) if(!function_exists($f)) exit(2);'
COPY tests/native/m6c/journal/ /tmp/ubo-m6c-journal-source/
RUN sh /tmp/ubo-m6c-journal-source/build.sh "$SOURCE_SHA"
LABEL org.ubo.m6c.contract="m6c-native-v1" \
      org.ubo.m6c.source="${SOURCE_SHA}" \
      org.ubo.m6c.php-base="${PHP_BASE}"
USER 0:0
WORKDIR /opt/ubo
ENTRYPOINT ["/bin/sleep"]
CMD ["1200"]
