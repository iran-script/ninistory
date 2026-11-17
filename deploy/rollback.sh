#!/bin/sh
set -eu

PROJECT_DIR=${PROJECT_DIR:-/opt/kidoki}
COMPOSE_FILE=${COMPOSE_FILE:-compose.production.yml}
ROLLBACK_TAG=${1:?Usage: rollback.sh IMAGE_TAG}

cd "$PROJECT_DIR"
export KIDOKI_IMAGE_TAG="$ROLLBACK_TAG"
docker compose -p kidoki -f "$COMPOSE_FILE" --env-file .env.production up -d --no-build app web worker scheduler
docker compose -p kidoki -f "$COMPOSE_FILE" ps
