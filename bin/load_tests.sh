ab -t 30 -n 999999 -c 25 -v 1 "http://preview.addons.mozilla.org/" > home.out
sleep 20
ab -t 30 -n 999999 -c 25 -v 1 "http://preview.addons.mozilla.org/en-US/search/?q=web+developer" > search.out
sleep 20
ab -t 30 -n 999999 -c 25 -v 1 "http://preview.addons.mozilla.org/en-US/addons/browse/type:1/" > browse1.out
sleep 20
ab -t 30 -n 999999 -c 25 -v 1 "http://preview.addons.mozilla.org/en-US/addons/browse/type:4/" > browse4.out
sleep 20
ab -t 30 -n 999999 -c 25 -v 1 "http://preview.addons.mozilla.org/en-US/addons/browse/type:1/cat:all/" > browseall.out
sleep 20
ab -t 30 -n 999999 -c 25 -v 1 "http://preview.addons.mozilla.org/en-US/addons/display/60/" > display.out
sleep 20
ab -t 30 -n 999999 -c 25 -v 1 "http://preview.addons.mozilla.org/en-US/reviews/display/60/" > reviews.out
sleep 20
ab -t 30 -n 999999 -c 25 -v 1 "http://preview.addons.mozilla.org/en-US/addons/rss/newest/" > rss.out
sleep 20
ab -t 30 -n 999999 -c 25 -v 1 "http://preview.addons.mozilla.org/en-US/discussions/comments.php?DiscussionID=12&page=1#Item_0" > discuss1.out
sleep 20
ab -t 30 -n 999999 -c 25 -v 1 "http://preview.addons.mozilla.org/en-US/discussions/?AddOnID=7" > discuss2.out
sleep 20
ab -t 30 -n 999999 -c 25 -v 1 "http://preview-services.addons.mozilla.org/update.php?reqVersion=1&id={c45c406e-ab73-11d8-be73-000a95be3b12}&version=1.0.2&maxAppVersion=2.0.0.*&status=userEnabled&appID={ec8030f7-c20a-464f-9b0e-13a3a9e97384}&appVersion=2.0.0.2pre&appOS=Darwin&appABI=x86-gcc3" > update.out
sleep 20
ab -t 30 -n 999999 -c 25 -v 1 "http://preview-services.addons.mozilla.org/pfs.php?mimetype=application/x-shockwave-flash&appID={ec8030f7-c20a-464f-9b0e-13a3a9e97384}&appVersion=2007020307&clientOS=Windows%20XP&chromeLocale=en-US" > pfs.out
sleep 20
