#!/bin/bash

# This file deletes modifications done by build.sh to avoid svn conflicts

echo "Cleaning Build Files"

cd ../site/app/

rm -f config/revisions.php

rm -f webroot/js/__utm.min.js
rm -f webroot/js/jquery.addons.min.js

rm -f webroot/css/style.min.css

# Clean caches
rm -rf tmp/cache/models/*
rm -rf tmp/cache/persistent/*
rm -rf tmp/cache/views/*

# Remove all .mo files
#   Not doing this probably wouldn't cause a conflict, but better to be safe
find locale/ -name *.mo -exec rm -f {} \;

echo "Done"

