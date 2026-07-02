# ─── Build stage ──────────────────────────────────────────────────────────────
FROM php:8.3-cli AS base

LABEL maintainer="CrescentPHP"
LABEL description="CrescentPHP com Swoole — HTTP assíncrono + WebSocket"

# ─── Dependências do sistema ──────────────────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
        libssl-dev        \
        libcurl4-openssl-dev \
        libpcre2-dev      \
        unzip             \
        git               \
    && rm -rf /var/lib/apt/lists/*

# ─── Extensões PHP ────────────────────────────────────────────────────────────

# PDO + MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Swoole (com suporte a SSL, HTTP2, corrotinas)
RUN pecl install swoole \
    && docker-php-ext-enable swoole

# Opcache (melhora performance em modo Swoole)
RUN docker-php-ext-install opcache

# ─── Configuração PHP ─────────────────────────────────────────────────────────
COPY docker/php.ini /usr/local/etc/php/conf.d/crescent.ini

# ─── App ─────────────────────────────────────────────────────────────────────
WORKDIR /var/www

COPY . .

RUN mkdir -p logs \
    && chmod -R 755 logs

# ─── Porta exposta ───────────────────────────────────────────────────────────
# Swoole HTTP + WebSocket na mesma porta
EXPOSE 9501

# ─── Saúde ───────────────────────────────────────────────────────────────────
HEALTHCHECK --interval=15s --timeout=5s --start-period=10s --retries=3 \
    CMD php -r "exit(fsockopen('127.0.0.1', 9501) ? 0 : 1);" || exit 1

# ─── Entrada ─────────────────────────────────────────────────────────────────
CMD ["php", "swoole.php"]
