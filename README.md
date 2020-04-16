# Development environment setup

### Requirements

* Docker

### Instructions

1. Copy `docker/.env.dist` contents to `docker/.env.dist`
1. Configure environment variables (`SYMFONY_APP_PATH`, `MYSQL_PORT`, `NGINX_PORT` etc) at `docker/.env.dist`
1. Enable MySQL configuration if necessary by uncommenting database section at `docker/docker-compose.yml`
1. `make build`
1. `make start`
1. `make composer-install`
1. Configure database connection at `application/app/config/parameters.yml`. Default dev parameters under docker would look like:
```
    database_host: db
    database_port: 3306
    database_name: captum-dev
    database_user: user
    database_password: password
```
1. Load fixtures (dev data) to database by running:
    - `make fixtures-load-package`
    - `make fixtures-load-example`
    - `make fixtures-load-admin`
    - `make fixtures-load-test`
    - `make fixtures-load-product`
1. That's it. Access it via `http://localhost:{NGINX_PORT}`

# Server requirements

* PHP 7.1+
    * Optional (Composer)[https://getcomposer.org/]
    * Required extensions may be identified by running `composer check-platform-reqs`
* Nginx or Apache with configuration for [Symfony](https://symfony.com/doc/3.4/setup/web_server_configuration.html)
* MySQL 5.7
* Supervisor (config at `docker/php7-fpm/supervisord.conf`)

# Deployment to stage server

Stage server is configured under hostname ec2-3-81-69-200.compute-1.amazonaws.com

In order to be able deploy into the server you must:
* Connected to the VPN
* Have private key located under `.mage/keys/staging.pem`
* Configured development environment via docker

Deployment via docker `mage-deploy-staging`. Configuration is located at `application/.mage.yml`

You can SSH into the server using `ssh -i .mage/keys/staging.pem ubuntu@ec2-3-81-69-200.compute-1.amazonaws.com`
