composer install --no-dev
npm run build
rm -r composer.json
rm -r composer.lock

rm -r README.md

rm -rf .git/
rm -r .editorconfig
rm -r .gitignore
rm -r phpcs.xml
rm -r blueprint.json
rm -r site-content.xml

rm -r .eslintignore
rm -r .eslintrc.js
rm -r .nvmrc
rm -r package.json
rm -r package-lock.json
rm -r webpack.config.js
rm -rf node_modules/
rm -rf src/

echo "Production Ready 📦"
rm -r clean.sh
