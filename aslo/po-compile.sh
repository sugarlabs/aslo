#!/bin/sh

root=$(cd $(dirname $0)/..; pwd)

backmerge() {
    echo "Back merge $1"

    local in_po=$root/aslo/po/$1.po
    local out_po=$root/site/app/locale/$1/LC_MESSAGES/messages.po

    local tmp=`mktemp /tmp/po-update.XXXXXX` || exit 1

    msgcat --use-first $in_po $out_po > $tmp
    mv $tmp $out_po
    chmod a+r $out_po

    output=$(dirname $out_po)/$(basename $out_po .po).mo
    msgfmt --use-fuzzy $out_po -o $output
}

for i in `ls $root/aslo/po/*.po`; do
    backmerge $(basename $i .po)
done
