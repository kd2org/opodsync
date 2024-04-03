.PHONY : server docker-test

server:
	php -S localhost:8080 -t server server/index.php

docker-test:
	docker-compose down --rmi all -v
	docker-compose up
