#!/bin/sh

root=$(cd $(dirname $0)/..; pwd)

/etc/init.d/apache2 stop

. /etc/apache2/envvars

if [ $# -gt 0 ]; then
    for i in "$@"; do
        echo $i
        mysql -u remora --password=remora -D remora < $i
    done
else
    mysql -u remora --password=remora -D remora < $root/site/app/config/sql/remora.sql
    mysql -u remora --password=remora -D remora < $root/aslo/sql/sugar-stub-data.sql
fi

rm -rf $root/site/app/tmp
rm -rf $root/downloads
rm -rf $root/files
rm -rf $root/log

mkdir $root/site/app/tmp/
mkdir $root/site/app/tmp/cache
mkdir $root/site/app/tmp/cache/persistent
mkdir $root/site/app/tmp/cache/models
mkdir $root/site/app/tmp/cache/views
chown -R $APACHE_RUN_USER:$APACHE_RUN_GROUP $root/site/app/tmp

mkdir $root/downloads
chown -R $APACHE_RUN_USER:$APACHE_RUN_GROUP $root/downloads

mkdir $root/files
mkdir $root/files/temp
mkdir $root/files/extracted
chown -R $APACHE_RUN_USER:$APACHE_RUN_GROUP $root/files

mkdir $root/log
chown -R $APACHE_RUN_USER:$APACHE_RUN_GROUP $root/log

cp $root/aslo/config-local.php $root/site/app/config/
cp $root/aslo/config.php $root/site/app/config/

/etc/init.d/apache2 start
