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
echo "Merging & compiling all locales..."
for i in `find locale -type f -name "messages.po"`; do
    dir=`dirname $i`
    stem=`basename $i .po`

    # msgen will copy the msgid -> msgstr for English.  All other locales will get a blank msgstr
    if [[ "$i" =~ "en_US" ]]; then
        msgen ./locale/keys.pot | msgmerge --sort-output --no-fuzzy-matching -U "$i" -
    else
        msgmerge --sort-output --no-fuzzy-matching -U "$i" ./locale/keys.pot
    fi
    msgfmt -o ${dir}/${stem}.mo $i
done
rm ./locale/keys.pot
