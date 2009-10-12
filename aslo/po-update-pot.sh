#!/bin/sh

root=$(cd $(dirname $0)/..; pwd)

parse() {
    cat $1 | awk "/^$/{if(out) print msg\"\n\"; msg=\"\"; out=0} /$2/{out=1} {if(\$0) msg=msg\"\n\"\$0}"
}

merge() {
    local in_po=$root/site/app/locale/$1/LC_MESSAGES/messages.po
    local out_po=$root/aslo/po/$1.po

    [ -f $in_po ] || continue
    echo -n "Update $lang "

    local tmp=`mktemp /tmp/po-update.XXXXXX` || exit 1

    parse $in_po 'msgid ""' > $tmp

    for i in `cat $root/aslo/po/msgid`; do
        parse $in_po "\"$i\"" >> $tmp
    done

    touch $out_po
    msgmerge --update --backup=none --no-fuzzy-matching $out_po $tmp
    rm $tmp
}

cd $root/site/app/locale
./extract-po.sh || exit 1
./merge-po.sh ../messages.po en_US/ || exit 1
merge en
cp $root/aslo/po/en.po $root/aslo/po/aslo.pot
