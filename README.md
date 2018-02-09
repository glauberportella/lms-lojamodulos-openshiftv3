# Docker

Create an `.env` file before run `docker-compose up -d`, this file will contain the following environment variables:

```
LMSDB_SERVICE_HOST=db
LMSDB_SERVICE_PORT=3306
MYSQL_ROOT_PASSWORD=root mysql password
MYSQL_DATABASE=database name
MYSQL_USER=user name
MYSQL_PASSWORD=user password
```

Init services

`docker-compose up -d`

Install dependencies

`docker-compose run composer install --no-dev`