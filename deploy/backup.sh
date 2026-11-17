#!/bin/sh
set -eu

PROJECT_DIR=${PROJECT_DIR:-/opt/kidoki}
BACKUP_DIR=${BACKUP_DIR:-/var/backups/kidoki}
COMPOSE_FILE=${COMPOSE_FILE:-compose.production.yml}

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
cd "$PROJECT_DIR"
STAMP=$(date -u +%Y%m%dT%H%M%SZ)

docker compose -p kidoki -f "$COMPOSE_FILE" exec -T db \
    sh -c 'PGPASSWORD="$POSTGRES_PASSWORD" pg_dump --format=custom --no-owner --no-acl --username="$POSTGRES_USER" "$POSTGRES_DB"' \
    > "$BACKUP_DIR/kidoki-$STAMP.dump"

docker run --rm \
    -v kidoki_storage:/source:ro \
    -v "$BACKUP_DIR:/destination" \
    alpine:3.20 \
    tar -czf "/destination/kidoki-storage-$STAMP.tar.gz" -C /source app/public

find "$BACKUP_DIR" -type f -mtime +14 -delete
