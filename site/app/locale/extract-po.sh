#!/bin/bash
SOURCE_DIRS="config models views controllers webroot"

cd `dirname $0`/../
touch ./locale/keys.pot

echo -n "Extracting..."
for sourcedir in $SOURCE_DIRS; do \
    find ./${sourcedir} -name "*thtml" -or -name "*.php" | xgettext \
        --language=PHP \
        --keyword=___:1 \
        --keyword=___:1,2c \
        --keyword=n___:1,2 \
        --keyword=n___:1,2,4c \
        --force-po \
        --omit-header \
        --join-existing \
        --sort-output \
        --output=./locale/keys.pot \
        --copyright-holder="Mozilla Corporation" \
        --files-from=- # Pull from standard input (our find command) \
done
echo "done."
echo -n "Merging to all locales..."
for i in `find locale -type f -name "messages.po"`; do
    msgmerge -s -U "$i" ./locale/keys.pot
done
rm ./locale/keys.pot
