install:
	cd docker && ./install.sh

shell:
	cd ./docker/ && docker-compose exec php bash

phpcs:
	./vendor/bin/phpcs

stan:
	./vendor/bin/phpstan analyse src --level max
