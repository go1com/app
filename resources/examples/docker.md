Dockerize the application
===

## Dockerfile

```
FROM busybox
COPY . /app
RUN rm -rf /app/.git/
WORKDIR /app
VOLUME ["/app"]
```

## docker-compose.yml

```
version: "2"
services:
  data:
    image: path.to/me/app:latest
    cpu_shares: 8
    mem_limit: 8000000
    command: ["ping", "127.0.0.1", "-q"]
  web:
    image: go1com/php:7-nginx
    cpu_shares: 32
    mem_limit: 67108864
    ports: ["80"]
    links: ["handler:handler"]
    volumes_from: ["data"]
```

## Build script

```bash
composer install --no-dev
docker build -t me_app .
docker tag me_app path.to/me/app:latest
docker push path.to/me/app:latest
```
