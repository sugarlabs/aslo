# This script adds new strings to a file in every locale of a certain type
# usage: ./merge-to-locales.sh {locale file to merge into} {new strings to add}
# example: ./merge-to-locales.sh extensionsOverlay.dtd new.txt

svn_locales=(ar ca cs da de fa fr he ja pt-BR pt-PT ro ru sq sv-SE uk)
bz_locales=(el-GR es-ES fy-NL id it nl pl sk vi zh-CN zh-TW)

for locale in ${svn_locales[@]}
do
	echo "adding strings from $2 to ../locale/$locale/$1"
	cat $2 >> "../locale/$locale/$1"
done

echo "done"
