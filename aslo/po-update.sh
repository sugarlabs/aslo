#!/bin/sh

root=$(cd $(dirname $0)/..; pwd)
cd $root/site/app/locale

for i in $(ls); do
    [ -d $i ] || continue

    if [ $i == "en_US" ]; then
        lang=en
    else
        lang=$i
    fi

    echo $lang

    tmp=`mktemp /tmp/po-update.XXXXXX` || exit 1

    cat $i/LC_MESSAGES/messages.po \
    | awk "BEGIN{IGNORECASE=1; first=1}
               /^$/{if(out==2 || first==1) print msg\"\"; first=0; msg=\"\"; out=0}
               /^msgid/{out=1}
               /(add-on|mozilla|firefox)/{if(out==1) out=2}
               {msg=msg\$0\"\n\"}" \
        > $tmp

    out_po=$root/aslo/po/$lang.po
    if [ -e $out_po ]; then
        msgcat --use-first $out_po $tmp > $tmp~
        mv $tmp~ $out_po
        rm $tmp
    else
        mv $tmp $out_po
    fi
done
