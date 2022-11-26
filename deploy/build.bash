#!/bin/bash

PWD=$(echo $(cd $(dirname BAS_SOURCE[0])) & pwd)
PHP_BIN=$(which php)
COMPOSER_PHAR="${PWD}/composer.phar"
if [[ ! -d "${PWD}" ]]; then
    echo -e "\033[0;31mDirectory does not exists\033[0m"
    exit 255
fi

if [[ "${PHP_BIN}" = "" ]] || [[ ! -x "${PHP_BIN}" ]]; then
    echo -e "\033[0;31mPHP binary does not exists\033[0m"
    exit 255
fi

echo -e "\033[0;34mChecking PHP version that meet the version requirement\033[0m"
REQUIRE=$("${PHP_BIN}" -r "echo version_compare(PHP_VERSION, '8.1.0', '>=');")

if [[ "${REQUIRE}" = "" ]]; then
    echo -e "\033[0;31mPHP version is not meet the version requirement\033[0m"
    exit 255
else
    echo -e "\033[0;32mPHP version has meet minimum requirement\033[0m";
fi

if [[ ! -f "${PWD}/composer.json" ]]; then
    echo -e "\033[0;31mcomposer.json does not exists\033[0m"
    exit 255
fi

if [[ ! -f "${PWD}/box.json" ]]; then
    echo -e "\033[0;31mbox.json does not exists\033[0m"
    exit 255
fi
FILE=$("${PHP_BIN}" -r "\$r = json_decode(file_get_contents('${PWD}/box.json'), true)['output']??'';echo \$r ? realpath(dirname(\$r)).'/'.basename(\$r) : '';")
if [[ "${FILE}" = '' ]];then
    echo -e "\033[0;31mCan not detect build file\033[0m"
    exit 255
fi
URL_PHP_COMPOSER='https://getcomposer.org/download/latest-stable/composer.phar'
URL_COMPOSER_SHA256='https://getcomposer.org/download/latest-stable/composer.phar.sha256'

echo -e "\033[0;34mGetting latest sha 256 sum\033[0m"

COMPOSER_SHA256SUM=$(wget -nv --no-check-certificate -q -O - "${URL_COMPOSER_SHA256}")
if [[ "${COMPOSER_SHA256SUM}" = "" ]]; then
    echo -e "\033[0;31mCan not download sha 256 sum\033[0m"
    exit
fi
DO_DOWNLOAD=1
if [[ -f "${PWD}/composer.phar" ]]; then
    SHASUM=$(shasum -a 256 "${COMPOSER_PHAR}" | awk '{printf $1}')
    if [[ "${SHASUM}" = "${COMPOSER_SHA256SUM}" ]]; then
        echo -e "\033[0;32mSha 256 valid:\033[0m ${SHASUM}"
        DO_DOWNLOAD=0
    else
        echo -e "\033[0;31mSha 256 invalid:\033[0m ${SHASUM}"
        echo -e "\033[0;34mDownloading...\033[0m"
    fi
fi
if [[ DO_DOWNLOAD -eq 1 ]]; then
    wget -nv --no-check-certificate "${URL_PHP_COMPOSER}" -O "${COMPOSER_PHAR}" || ( echo "Can not download composer.phar" & exit $?)
fi

if [[ ! -f "${COMPOSER_PHAR}" ]]; then
    echo -e "\033[0;31mcomposer.phar does not exists!\033[0m"
    exit 255
fi

SHASUM=$(shasum -a 256 "${COMPOSER_PHAR}" | awk '{printf $1}')
if [[ "${SHASUM}" != "${COMPOSER_SHA256SUM}" ]]; then
    echo -e "\033[0;31mcomposer.phar invalid sha 256\033[0m"
    exit
fi

cd "${PWD}"

echo -e "\033[0;34mInstalling dependencies with composer\033[0m"
"${PHP_BIN}" "${COMPOSER_PHAR}" install &> /dev/null || exit $?
if [[ ! -f "${PWD}/vendor/bin/box" ]]; then
    echo -e "\033[0;31mBox binary does not exists\033[0m"
    exit
fi

"${PHP_BIN}" "${PWD}/vendor/bin/box" compile &> /dev/null & echo -e "\033[0;34mBuilding Archive\033[0m" || (echo -e "\033[0;31mFAiled to build binary\033[0m" & exit $?)
if [[ ! -f "${FILE}" ]]; then
    echo -e "\033[0;31mI don't know build files saved!\033[0m"
    exit
fi
echo -e "\033[0;34mBuild file on:\033[0m"
echo -e "  \033[0;32m${FILE}\033[0m"

