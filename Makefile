.PHONY := server

server:
	php -S localhost:8080 -t server server/index.php
