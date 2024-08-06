#!/bin/bash

# enable maintenance mode
php bin/console app:toggle:maintenance

# stop admin-suite services
sudo systemctl stop apache2
sudo systemctl stop admin-suite-monitoring

# clear cache & packages
sudo sh scripts/clear.sh
                   
# pull the latest changes
git pull

# set the environment to production
sed -i 's/^\(APP_ENV=\)dev/\1prod/' .env

# install dependencies
sh scripts/install.sh

# run database migrations
mkdir migrations
php bin/console doctrine:database:create --if-not-exists
php bin/console make:migration --no-interaction
php bin/console doctrine:migrations:migrate --no-interaction
                    
# run app commands
php bin/console app:auth:tokens:regenerate

# set permissions
sudo chmod -R 777 var/
sudo chown -R www-data:www-data var/

# start admin-suite services
sudo systemctl start apache2
sudo systemctl start admin-suite-monitoring

# disable maintenance mode
php bin/console app:toggle:maintenance

# make initial request for reload cache
curl -X GET https://admin-suite.becvar.xyz
