# <h1 align="center">Admin suite</h1>
### <p align="center">Solution for administration and monitoring linux private servers</p>
<p align="center">
    <a href="https://github.com/lordbecvold/admin-suite/blob/main/LICENSE" target="_blank">License</a> |
    <a href="https://becvar.xyz" target="_blank">Author</a>
</p>

## Dashboard preview
![Dashboard](.github/assets/preview.png)

## ROADMAP
- [ ] Component disable/enable config
    - [ ] Default enable all components
    - [ ] Disable specific component (by controller or route name)
- [ ] Docker support (service type docker)
- [ ] System resources usage history graph
- [ ] Simple chat component
- [ ] Terminal component

## Code base
This project is based on the Symfony framework. The entire project is designed to require as few dependencies and additional software as possible for easy installation and maintenance.

## Primary use
I created this project to manage my personal servers. I wanted a simple and easy-to-use solution that would allow me to monitor and manage my servers without relying on complex software or complicated setups. This project aims to manage single server instances, not clusters.

## Monitoring
Monitoring is ensured by a console command (infinite loop) ``console app:process:monitoring``. This command needs to be run as a service, and the monitored services should be defined in the services file in the root of the project, which is considered the primary source, or directly in config/suite/services.json. it is necessary to set the monitoring interval and the SMTP server in the environment variables for sending alerts. The status and monitoring data can then be viewed on the web dashboard. 

## Authentication
Authentication uses a completely custom user system with only a username and password, and all users are managed by the application administrator. Only the first user can use the registration to create the first user in the database. The login using a standard PHP session and a cookie for the 'remember me'.

## Installation
Installation is very simple like any other PHP project.
* Clone repo ``git clone git@github.com:lordbecvold/admin-suite.git``
* Create apache virtual host or nginx configuration like ([apache-config](https://github.com/lordbecvold/admin-suite/blob/main/_docker/configs/apache-site.conf))
* Create .env file with your configuration (.env.prod)
* Create admin suite database
* Run database migrations (script/migrate.sh)
* Create user with console command or web interface (webinterface allows to create user if users table is empty)

## Configuration
* You can edit the config files directly in config/suite but it is better to create your own in the project root because these files will be loaded primarily and ignored by git when using the pull for update with scripts/upgrade.sh
* Configure your package-requitements.json & services.json in root file (for example use config/suite files)
* Create systemd or screen service for monitoring process (console command infinite loop) ([systemd-config](https://github.com/lordbecvold/admin-suite/blob/main/_docker/configs/systemd.service))

## Contributing
Contributions are welcome! Please fork this repository and submit pull requests for any features, bug fixes, or enhancements. If you have any questions or need help, feel free to open an issue or contact me directly.

## Requirements
* OS Linux
* PHP 8.3
* MySQL 8
* Mysql - PDO driver
* PHP-OpenSSL extension
* PHP-Intl extension
* PHP-Curl extension
* Composer
* NPM package manager and assets build tool for encore build

## Dependencies
This project uses the following technologies
* PHP
    * [Website](https://php.net)
* Symfony
    * [Website](https://symfony.com)
* Tailwind
    * [Website](https://tailwindcss.com)
* NPM / Webpack encore
    * [NPM](https://docs.npmjs.com)
    * [Encore](https://symfony.com/doc/current/frontend/encore/index.html)

## License
The framework is open-sourced software licensed under the [MIT license](https://github.com/lordbecvold/admin-suite/blob/main/LICENSE).
