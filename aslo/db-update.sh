#!/bin/sh

root=$(cd $(dirname $0)/..; pwd)
ts=`date --date='yesterday' +%Y-%m-%d`

gzip -c /var/log/httpd/access_log > $root/log/access_$ts.gz \
    || exit 1

mysql -u remora --password=remora -D remora <<EOF
    DELETE FROM logs_parsed;
    DELETE FROM download_counts;
EOF

pushd $root/bin/parse_logs
    php -f parse_logs.php v=v logs=$root/log temp=/tmp type=downloads date=$ts geo=CN \
        || exit 1
popd

pushd $root/bin
    php -f $root/bin/update-search-views.php
popd

for i in weekly total reviews; do
    php -f $root/bin/maintenance.php $i
done
