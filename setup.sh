#!/bin/bash

docker compose build

docker compose run dev-php composer install
# The symfony/http-client package is required to make HTTP requests to the external API.
docker compose run --rm dev-php composer require symfony/http-client
# The symfony/rate-limiter package is required to implement rate limiting for the API requests.
docker compose run --rm dev-php composer require symfony/lock
docker compose run --rm dev-php composer require symfony/rate-limiter

docker compose run dev-vue3 npm install

docker compose up -d

echo "Navigate to http://localhost:3000"