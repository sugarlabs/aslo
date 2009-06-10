BUNDLE="../amo-bundle.js"
COMPRESSED="../amo-bundle.compressed.js"

echo "Creating bundle..."
echo "" > $BUNDLE
#echo "Copying bundle template..."
#cat bundle-template.js > $BUNDLE

DIRECTORIES="ajax timeline timeplot"
for dir in $DIRECTORIES;
do
	echo "Reading $dir inclusions file..."
	for file in `cat $dir.txt`
	do
		echo "    $dir/$file";
		cat "../$dir/$file" >> $BUNDLE
	done
done

echo "Compressing bundle..."
/usr/bin/php -f pack-bundle.php $BUNDLE $COMPRESSED

echo "Done"
