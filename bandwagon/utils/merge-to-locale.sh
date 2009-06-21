# This script adds new strings to a file in the specified locale
# usage: ./merge-to-locale.sh {locale} {locale file to merge into} {new strings to add}
# example: ./merge-to-locale.sh zh-CN extensionsOverlay.dtd new.txt

echo "adding strings from $3 to ../locale/$1/$2"
cat $3 >> "../locale/$1/$2"

echo "done"
