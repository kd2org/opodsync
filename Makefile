up:
	sudo docker run --rm -p 8080:8080 -v $(shell pwd)/server:/var/www/html:rw php-nginx
