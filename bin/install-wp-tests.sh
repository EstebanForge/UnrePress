#!/usr/bin/env bash

if [ $# -lt 3 ]; then
    echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]"
    exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo $TMPDIR | sed -e "s/\//$TMPDIR\\\\//g")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

set -ex

download() {
    if [ `which curl` ]; then
        curl -sL "$1" > "$2";
    elif [ `which wget` ]; then
        wget -nv -O "$2" "$1";
    else
        echo "Neither curl nor wget found. Exiting."
        exit 1
    fi
}

mkdir -p $WP_CORE_DIR

if [ "$WP_VERSION" == "latest" ]; then
    DOWNLOAD_URL="https://wordpress.org/latest.tar.gz"
else
    DOWNLOAD_URL="https://wordpress.org/wordpress-$WP_VERSION.tar.gz"
fi

if [ -d $WP_CORE_DIR ]; then
    echo "Removing existing WordPress core directory..."
    rm -rf $WP_CORE_DIR
fi

echo "Downloading WordPress $WP_VERSION..."
download "$DOWNLOAD_URL" /tmp/wordpress.tar.gz
tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C $WP_CORE_DIR
rm /tmp/wordpress.tar.gz

echo "Creating wp-config.php..."
cp $WP_CORE_DIR/wp-config-sample.php $WP_CORE_DIR/wp-config.php
sed -i "s/database_name_here/$DB_NAME/" $WP_CORE_DIR/wp-config.php
sed -i "s/username_here/$DB_USER/" $WP_CORE_DIR/wp-config.php
sed -i "s/password_here/$DB_PASS/" $WP_CORE_DIR/wp-config.php
sed -i "s/localhost/$DB_HOST/" $WP_CORE_DIR/wp-config.php

if [ "$SKIP_DB_CREATE" == "false" ]; then
    echo "Creating database..."
    mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS" --host="$DB_HOST"
fi

if [ ! -d $WP_TESTS_DIR ]; then
    echo "Installing WordPress test suite..."
    svn co --quiet https://develop.svn.wordpress.org/trunk/tests/phpunit/includes/ $WP_TESTS_DIR/includes
    svn co --quiet https://develop.svn.wordpress.org/trunk/tests/phpunit/data/ $WP_TESTS_DIR/data
fi

if [ ! -f wp-tests-config.php ]; then
    download https://develop.svn.wordpress.org/trunk/wp-tests-config-sample.php $WP_TESTS_DIR/wp-tests-config.php
    sed -i "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR':" $WP_TESTS_DIR/wp-tests-config.php
    sed -i "s/youremptytestdbnamehere/$DB_NAME/" $WP_TESTS_DIR/wp-tests-config.php
    sed -i "s/yourusernamehere/$DB_USER/" $WP_TESTS_DIR/wp-tests-config.php
    sed -i "s/yourpasswordhere/$DB_PASS/" $WP_TESTS_DIR/wp-tests-config.php
    sed -i "s|localhost|${DB_HOST}|" $WP_TESTS_DIR/wp-tests-config.php
fi

echo "WordPress test environment setup complete!"
echo "WP Core: $WP_CORE_DIR"
echo "WP Tests: $WP_TESTS_DIR"