# Development environment setup

### Requirements

* Docker

### Instructions

1. Copy `docker/.env.dist` contents to `docker/.env.dist`
1. Configure environment variables (`SYMFONY_APP_PATH`, `PORT_MYSQL`, `PORT_NGINX` etc) at `docker/.env.dist`
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
1. That's it. Access it via `http://localhost:{PORT_NGINX}`
