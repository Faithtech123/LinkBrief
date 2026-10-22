#!/bin/sh
set -eu

port="${PORT:-10000}"
case "$port" in
    ''|*[!0-9]*)
        echo "PORT must be a number" >&2
        exit 1
        ;;
esac

sed -i "s/Listen 10000/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/*:10000/*:${port}/" /etc/apache2/sites-available/000-default.conf

exec "$@"
