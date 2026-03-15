#!/bin/bash

docker compose build

docker compose run dev-php composer install
docker compose run --rm dev-php composer require symfony/http-client

docker compose run dev-vue3 npm install

docker compose up -d

echo "Navigate to http://localhost:3000"