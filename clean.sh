composer install --no-dev
rm -r composer.json
rm -r composer.lock

rm -r README.md

rm -rf .git/
rm -r .editorconfig
rm -r .gitignore

rm -rf bin/
rm -rf tests/
rm -r .phpcs.xml
rm -r .travis.yml

echo "Production Ready 📦"
rm -r clean.sh
