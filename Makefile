build:
	cd docker && docker-compose build
	@$(MAKE) --no-print-directory supervisor-start
	@$(MAKE) --no-print-directory supervisor-status
start:
	cd docker && docker-compose up -d
	@$(MAKE) --no-print-directory supervisor-start
	@$(MAKE) --no-print-directory supervisor-status
stop:
	cd docker && docker-compose down
restart:
	cd docker && docker-compose down
	cd docker && docker-compose up -d
	@$(MAKE) --no-print-directory supervisor-start
	@$(MAKE) --no-print-directory supervisor-status
status:
	cd docker && docker-compose ps
sh:
	cd docker && docker-compose exec -T php bash
permissions:
	cd docker && docker-compose exec -T php bash -c "chown www-data:www-data -R /var/www/symfony"
db-force:
	cd docker && docker-compose exec -T php bash -c "cd /var/www/symfony/ && php bin/console doctrine:schema:update --force"
copy-supervisor-conf:
	cd docker && docker cp php7-fpm/supervisord.conf php:/etc/supervisor/conf.d/
supervisor-start:
	cd docker && docker-compose exec -T php bash -c "service supervisor start && supervisorctl start queue schedule"
supervisor-restart:
	cd docker && docker-compose exec -T php bash -c "supervisorctl restart queue schedule"
supervisor-status:
	cd docker && docker-compose exec -T php bash -c "supervisorctl status"
cache-clear:
	cd docker && docker-compose exec -T php bash -c "cd /var/www/symfony/ && php bin/console cache:clear --no-warmup --env=dev"
	cd docker && docker-compose exec -T php bash -c "cd /var/www/symfony/ && php bin/console cache:clear --no-warmup --env=prod"
	@$(MAKE) --no-print-directory permissions
fixtures-load-package:
	cd docker && docker-compose exec -T php bash -c "cd /var/www/symfony/ && php bin/console doctrine:fixtures:load --group=packages --append"
fixtures-load-example:
	cd docker && docker-compose exec -T php bash -c "cd /var/www/symfony/ && php bin/console doctrine:fixtures:load --group=examples --append"
fixtures-load-admin:
	cd docker && docker-compose exec -T php bash -c "cd /var/www/symfony/ && php bin/console doctrine:fixtures:load --group=admins --append"
fixtures-load-test:
	cd docker && docker-compose exec -T php bash -c "cd /var/www/symfony/ && php bin/console doctrine:fixtures:load --group=test_experiences --append"
fixtures-load-product:
	cd docker && docker-compose exec -T php bash -c "cd /var/www/symfony/ && php bin/console doctrine:fixtures:load --group=products --append"
migrations-execute:
	cd docker && docker-compose exec -T php bash -c "cd /var/www/symfony/ && php bin/console doctrine:migrations:migrate"
migrations-diff:
	cd docker && docker-compose exec -T php bash -c "cd /var/www/symfony/ && php bin/console doctrine:migrations:diff"
composer-install:
	cd docker && docker-compose exec -T php bash -c "composer install"
