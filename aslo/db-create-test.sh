#!/bin/sh

root=$(cd $(dirname $0)/..; pwd)

service httpd stop

mysql -u remora --password=remora -D remora < $root/site/app/config/sql/remora.sql
mysql -u remora --password=remora -D remora < $root/site/app/tests/data/remora-test-data.sql
mysql -u remora --password=remora -D remora < $root/site/app/tests/data/sugar-test-data.sql
mysql -u remora --password=remora -D remora-test < $root/site/app/config/sql/remora.sql
mysql -u remora --password=remora -D remora-test < $root/site/app/tests/data/remora-test-data.sql

rm -rf $root/site/app/tmp
rm -rf $root/downloads
rm -rf $root/files

mkdir $root/site/app/tmp/
mkdir $root/site/app/tmp/cache
mkdir $root/site/app/tmp/cache/persistent
mkdir $root/site/app/tmp/cache/models
mkdir $root/site/app/tmp/cache/views
chown -R apache:apache $root/site/app/tmp

mkdir $root/downloads
chown -R apache:apache $root/downloads

mkdir $root/files
mkdir $root/files/temp
mkdir $root/files/extracted
chown -R apache:apache $root/files

(cd $root/site/app/locale; sudo -u apache ./compile-mo.sh en_US)

service httpd start
