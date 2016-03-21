#!/usr/bin/env bash
cd /var/www/html/bindeo/front
composer install
rm -rf var/cache/*
for file in ./web/js/*.js
do
    uglifyjs $file -c -m -o $file
done