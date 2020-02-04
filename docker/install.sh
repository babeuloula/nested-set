#!/usr/bin/env bash

set -e

echo "COMPOSE_PROJECT_NAME=nested_set_model_test" > .env
echo "DOCKER_UID=${UID}" >> .env

docker-compose stop
docker-compose build --parallel
docker-compose up -d --remove-orphans
