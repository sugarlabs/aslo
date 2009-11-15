#!/bin/sh

root=$(cd $(dirname $0)/..; pwd)
logfile=$root/data/parse

cd $root/bin

date > $logfile

for type in downloads updatepings; do
    php -f parse_logs/parse_logs.php \
        logs=$root/data/ \
        temp=/tmp \
        type=$type \
        date=$(date --date=yesterday +%Y%m%d) \
        geo=US v=v >> $logfile
done

for time in now yesterday; do
    date=$(date +%F -d $time)
    php -f maintenance.php weekly $date >> $logfile
    php -f maintenance.php global_stats $date >> $logfile
    php -f maintenance.php collection_stats $date >> $logfile
    php -f maintenance.php collection_subscribers $date >> $logfile
done

php -f maintenance.php total >> $logfile
php -f maintenance.php ADU >> $logfile
php -f maintenance.php gc >> $logfile
#php -f maintenance.php publish_files >> $logfile
php -f maintenance.php reviews >> $logfile
php -f maintenance.php user_ratings >> $logfile
php -f maintenance.php ratings >> $logfile
php -f maintenance.php unconfirmed >> $logfile
php -f maintenance.php expired_resetcode >> $logfile
php -f maintenance.php addons_collections_total >> $logfile
#php -f maintenance.php collections_total >> $logfile
php -f maintenance.php collections_ratings >> $logfile
php -f maintenance.php share_count_totals >> $logfile
php -f maintenance.php category_totals >> $logfile
