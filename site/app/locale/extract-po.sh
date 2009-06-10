#!/bin/bash
SOURCE_DIRS="config models views controllers webroot"

cd `dirname $0`/../
touch messages.po

for sourcedir in $SOURCE_DIRS; do \
    find ./${sourcedir} -name "*thtml" -or -name "*.php" | xgettext \
        --language=PHP \
        --keyword=___ \
        --keyword=n___:1,2 \
        --force-po \
        --omit-header \
        --join-existing \
        --sort-output \
        --copyright-holder="Mozilla Corporation" \
        --files-from=- # Pull from standard input (our find command) \
done
