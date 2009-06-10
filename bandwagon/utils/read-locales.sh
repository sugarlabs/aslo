svn_locales=(ar ca cs da de fa fr he ja pt-BR pt-PT ro ru sq sv-SE uk)
bz_locales=(el-GR es-ES fy-NL id it nl pl sk vi zh-CN zh-TW)

for locale in ${svn_locales[@]}
do
	echo "[svn] $locale:"
	cat "../locale/$locale/bandwagon.properties"
done

for locale in ${bz_locales[@]}
do
	echo "[bz] $locale:"
	cat "../locale/$locale/bandwagon.properties"
done

echo "done"
