composer install --no-dev
rm -r composer.json
rm -r composer.lock

rm -r README.md

rm -rf .git/
rm -r .editorconfig
rm -r .gitignore
rm -r phpcs.xml

echo "Production Ready 📦"
rm -r clean.sh
