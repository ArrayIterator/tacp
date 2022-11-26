#!/usr/bin/env bash
spinner_pid=
PHP_BASE_URL='https://www.php.net/distributions'
PHP_VERSION="8.1.13"
SHA='eed1981ce9999d807cb139a9d463ae54bbeda2a57a9a28ad513badf5b99b0073'
VERSION="php-${PHP_VERSION}"
PWD=$(pwd)
WGET=$(/usr/bin/which wget)
LOG="${PWD}/build.log"
url_php_composer='https://getcomposer.org/download/latest-stable/composer.phar'
PREFIX="${PWD}/php"
PHP_BIN_DIR="$PREFIX/bin"
PHP_BIN="$PHP_BIN_DIR/php"
USER=$(whoami)

# --------------------------------------------
# FUNCTIONS
# --------------------------------------------
function red {
      echo -e "\033[0;31m${1}\033[0m"
}
function red_x {
      echo -e "\033[0;31m${1}\033[0m ${2}"
}
function red_y {
      echo -e "\033[0;31m${1}\033[0m ${2}"
}
function green {
      echo -e "\033[0;32m${1}\033[0m"
}
function green_x {
      echo -e "\033[0;32m${1}\033[0m ${2}"
}
function green_y {
      echo -en "\033[0;32m${1}\033[0m ${2}"
}
function blue {
      echo -e "\033[0;34m${1}\033[0m"
}
function blue_x {
      echo -e "\033[0;34m${1}\033[0m ${2}"
}
function blue_y {
      echo -en "\033[0;34m${1}\033[0m ${2}"
}
function spinner() {
    local pid=$!

    global_error=0

    trap "kill -9 $pid $PID &> /dev/null" SIGINT SIGTERM

    ignore=$2

    blue_y "[i]" "${1}"

    spinner_pid=$pid


    spin='-\|/'
    i=0
    while kill -0 "${pid}" 2>/dev/null
    do
      i=$(( (i+1) %4 ))
      # shellcheck disable=SC2059
      printf "\r[${spin:${i}:1}]"
      sleep .1
    done

    printf "\r"
    wait "${pid}";
    global_error=$?
    if [ $global_error -ne 0 ] && [ "${ignore}" = "" ]; then
        echo -e "\033[0;31m[x]\033[0m"
    else
        echo -e "\033[0;32m[✓]\033[0m"
    fi

    spinner_pid=""
    return $global_error
}
validate_sha256_sum() {
    cur_file=$1
    sha_sum=$2
    if [ "${cur_file}" = '' ] || [ ! -f "${cur_file}" ] || [ "${sha_sum}" = "" ]; then
        echo 1
        return 1
    fi
    validate_sum=$(sha256sum "${cur_file}" | awk '{printf $1}')
    if [ "${sha_sum}" = "${validate_sum}" ]; then
        echo 0
        return 0
    fi
    echo 1
    return 1
}

if [[ ! -f "$VERSION.tar.gz" ]]; then
    $WGET -nv --no-check-certificate "${PHP_BASE_URL}/${VERSION}.tar.gz" -O "${VERSION}.tar.gz" & spinner "DOWNLOADING ${VERSION}" || (echo "FAILED TO DOWNLOAD $VERSION" && exit $?)
fi

if [[ -f "$VERSION.tar.gz" ]]; then
    SUM=$(validate_sha256_sum "$VERSION.tar.gz" "${SHA}")
    if [[ "${SUM}" -eq 1 ]]; then
        echo "INVALID SIGNATURE ON : ${VERSION}.tar.gz!"
        exit
    fi
else
    echo "FILE: [${VERSION}.tar.gz] NOT FOUND!";
    exit
fi

if [ -d "${VERSION}" ]; then
    rm -rf "${VERSION}"
fi

tar -xf "${VERSION}.tar.gz"

if [[ ! -d "${VERSION}" ]]; then
    echo "DIRECTORY ${VERSION} DOES NOT EXISTS!"
    exit 255;
fi

# INCLUDE_DIR=/usr/include/postgresql
# INCLUDE_DIR="/opt/local/include/postgresql14"
BIN_DIR="/usr/bin"

# GOTO
cd "${VERSION}" || (echo "DIRECTORY ${VERSION} DOES NOT EXISTS!" && exit $?)

echo > "${LOG}"

echo "INSTALLATION LOG ON ${LOG}";
echo "INSTALL TARGET ${PREFIX}"

apt install -y build-essential autoconf \
    libtool bison re2c pkg-config libxpm-dev \
    libxml2-dev libpq-dev libicu-dev libpng-dev \
    libjpeg-dev zlib1g-dev make libsqlite3-dev \
    libbz2-dev libcurl4-gnutls-dev libenchant-2-dev \
    libwebp-dev libfreetype-dev libonig-dev libaspell-dev libpspell-dev \
    libedit-dev libsodium-dev libargon2-dev libtidy-dev\
     libexpat-dev libxslt-dev \
     libzip-dev  &>> "${LOG}" & spinner "INSTALLING PREREQUISITES" || (echo "FAILED TO INSTALL PREREQUISITES" && exit $?);

./configure --with-pdo-pgsql=$BIN_DIR \
        --localstatedir="/var" \
        --sysconfdir="/home/$USER/php/etc" \
        --enable-static \
        --enable-fast-install \
        --disable-shared \
        --disable-all \
        --prefix="${PREFIX}" \
        --without-pear \
        --disable-fpm \
        --disable-cgi \
        --enable-bcmath \
        --enable-calendar \
        --disable-cgi \
        --disable-dtrace \
        --enable-dom \
        --enable-exif \
        --enable-fileinfo \
        --enable-filter \
        --disable-fiber-asm\
        --disable-ftp \
        --disable-fpm \
        --enable-gd \
        --enable-intl \
        --enable-mbregex \
        --enable-mbstring \
        --enable-mysqlnd \
        --enable-mysqlnd_compression_support\
        --enable-opcache \
        --enable-opcache-jit\
        --enable-pcntl \
        --enable-posix \
        --enable-session\
        --enable-phpdbg \
        --enable-phpdbg-readline \
        --enable-simplexml \
        --enable-sockets \
        --enable-sysvmsg \
        --enable-sysvsem \
        --enable-sysvshm \
        --enable-shmop \
        --enable-pdo \
        --enable-phar \
        --enable-soap \
        --enable-tokenizer \
        --enable-xml \
        --enable-xmlreader \
        --enable-xmlwriter \
        --with-bz2 \
        --with-curl \
        --enable-gd-jis-conv\
        --with-libedit \
        --with-libxml \
        --with-mhash \
        --with-mysqli=mysqlnd \
        --with-openssl \
        --with-password-argon2 \
        --with-pgsql \
        --with-pdo-mysql=mysqlnd \
        --with-pdo-sqlite \
        --with-pic \
        --with-readline \
        --with-sqlite3 \
        --with-pspell \
        --with-tidy \
        --with-enchant\
        --with-zip \
        --with-xsl \
        --with-webp\
        --with-jpeg\
        --with-xpm\
        --with-sodium\
        --with-freetype\
        --with-expat &>> "${LOG}" & spinner "Configuring PHP ${PHP_VERSION}" || (echo "FAILED TO BUILD PHP ${PHP_VERSION}" && exit $?)

make -j4 install &>> "${LOG}" & spinner "BUILD PHP ${PHP_VERSION}" || (echo "FAILED TO INSTALL PHP ${PHP_VERSION}" && exit $?)
echo "SUCCESS ON: ${PWD}/php"

$WGET -nv --no-check-certificate "${url_php_composer}" -O "${PHP_BIN_DIR}/composer.phar" &>> "${LOG}" & spinner "DOWNLOADING COMPOSER" || (echo "FAILED TO DOWNLOAD $VERSION" && exit $?)
