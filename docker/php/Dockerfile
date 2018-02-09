FROM php:7.1-apache

RUN apt-get update && apt-get install -y \
	curl \
	git-core \
	&& apt-get clean

RUN docker-php-ext-install -j$(nproc) pdo_mysql

