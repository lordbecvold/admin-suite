#!/bin/bash

# clean app cache
php bin/console cache:clear

# delete composer files
rm -rf vendor/
rm -rf composer.lock

# delete npm packages
rm -rf node_modules/
rm -rf package-lock.json

# delete builded assets
rm -rf public/build/
rm -rf public/bundles/

# delete symfony cache folder
sudo rm -rf var/

# delete docker services data
sudo rm -rf _docker/services/
