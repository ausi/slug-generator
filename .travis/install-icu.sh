#!/bin/bash

set -xe

ICU_VERSION="4-2-1"
PHP_VERSION="7.2.9"

mkdir -p ~/php-fromsource
cd ~/php-fromsource

if [ ! -e ./build/bin/php ]
then

    wget -qO- https://github.com/unicode-org/icu/archive/icu-release-${ICU_VERSION}.tar.gz | tar xz
    cd icu-icu-release-${ICU_VERSION}/source

    ./runConfigureICU Linux --prefix=$(pwd)/../build

    make
    make install

    cd ../..

    wget -qO- https://php.net/get/php-${PHP_VERSION}.tar.gz/from/this/mirror | tar xz
    cd php-${PHP_VERSION}

    ./configure --prefix=$(pwd)/../build \
        --enable-intl \
        --with-icu-dir=$(pwd)/../icu-icu-release-${ICU_VERSION}/build \
        --enable-mbstring

    make
    make install
    cd ..

    rm -rf php-${PHP_VERSION}

fi
