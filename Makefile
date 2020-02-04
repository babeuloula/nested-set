install:
	cd docker && ./install.sh

shell:
	cd ./docker/ && docker-compose exec php bash
