# Kidoki production deployment

This stack is isolated under the Compose project `kidoki`. It does not bind ports 80 or 443, does not expose PostgreSQL or Redis, and does not share volumes or networks with NEXO or PAD.

Production uses `compose.production.yml` without `compose.build.yml`; the VPS loads immutable, externally validated images from the offline CI artifact. `compose.build.yml` is for local/CI builds only. The release workflow does not publish to GHCR and does not require Docker Hub access on the VPS.

## Local preparation

1. Copy `.env.production.example` to `.env.production` outside version control.
2. Generate a unique `APP_KEY` and database password. Never put real secrets in the image or Git.
3. Set `APP_URL=https://kidoki.ir` and the final loopback `KIDOKI_HTTP_PORT` selected after read-only VPS inspection.
4. Keep `PAYMENT_MOCK_ENABLED=false` until a real, separately verified payment provider is configured.

## Build and start

Run from the release directory:

```sh
docker compose -p kidoki -f compose.production.yml -f compose.production.small.yml --env-file .env.production config
docker compose -p kidoki -f compose.production.yml -f compose.production.small.yml --env-file .env.production config --quiet
docker compose -p kidoki -f compose.production.yml -f compose.production.small.yml --env-file .env.production up -d db redis
docker compose -p kidoki -f compose.production.yml -f compose.production.small.yml --env-file .env.production run --rm app php artisan migrate --force
docker compose -p kidoki -f compose.production.yml -f compose.production.small.yml --env-file .env.production run --rm app php artisan optimize
docker compose -p kidoki -f compose.production.yml -f compose.production.small.yml --env-file .env.production up -d app web worker scheduler
docker compose -p kidoki -f compose.production.yml -f compose.production.small.yml ps

For a local build only, add `-f compose.build.yml` and use local image names in the environment file. Do not add that overlay to a production start.
```

## Offline image delivery

The `Kidoki release validation` workflow produces one artifact named
`kidoki-offline-images-<commit>`. It contains four `linux/amd64` Docker archives:

- `kidoki-app-<commit>.tar.gz`
- `kidoki-web-<commit>.tar.gz`
- `postgres-16-alpine-<commit>.tar.gz`
- `redis-7-alpine-<commit>.tar.gz`

Download the artifact outside the server and verify `SHA256SUMS` before
transferring it. Transfer only the four archives, `SHA256SUMS`,
`IMAGE-MANIFEST`, and `SOURCE_COMMIT` to a new directory such as
`/opt/kidoki/releases/<release-id>`. Never transfer `.env.production`, local
databases, uploads, or logs.

From that release directory on the server:

```sh
sha256sum -c SHA256SUMS
docker load < kidoki-app-<commit>.tar.gz
docker load < kidoki-web-<commit>.tar.gz
docker load < postgres-16-alpine-<commit>.tar.gz
docker load < redis-7-alpine-<commit>.tar.gz
docker image inspect kidoki/app:<commit> kidoki/web:<commit> postgres:16-alpine redis:7-alpine \
  --format '{{.Os}}/{{.Architecture}} {{join .RepoTags ","}}'
```

Set `KIDOKI_APP_IMAGE=kidoki/app:<commit>` and
`KIDOKI_WEB_IMAGE=kidoki/web:<commit>` in the protected production environment.
Keep `KIDOKI_POSTGRES_IMAGE=postgres:16-alpine` and
`KIDOKI_REDIS_IMAGE=redis:7-alpine`. Validate the production Compose files
without pulling:

```sh
docker compose -p kidoki -f compose.production.yml -f compose.production.small.yml \
  --env-file .env.production config --quiet
```

Before a future runtime start, verify all four image names with
`docker image inspect`. Because the production Compose files contain no build
blocks, `up -d` will not build an image; use `--pull never` where supported by
the installed Compose version and stop if any image is missing. Do not run
`docker pull`, change the Docker daemon, or affect the NEXO/PAD projects.

Migration is intentionally explicit. Never run `migrate:fresh`, reset, seed, or a broad Docker cleanup in production.

## Reverse proxy and HTTPS

After verifying the host's existing ingress and selecting an unused loopback port, add only a dedicated `kidoki.ir` virtual host forwarding to `127.0.0.1:$KIDOKI_HTTP_PORT`. Validate the proxy configuration before a graceful reload. Do not bind a second service to 80/443 and do not change NEXO/PAD server blocks.

Issue the certificate using the existing certificate manager and a webroot or DNS method. Do not use standalone validation if it would take over port 80.

## Health and smoke checks

```sh
docker compose -p kidoki -f compose.production.yml ps
curl --fail http://127.0.0.1:${KIDOKI_HTTP_PORT}/up
docker compose -p kidoki -f compose.production.yml logs --tail=100 app web worker scheduler
```

Then verify `https://kidoki.ir`, HTTP-to-HTTPS redirect, assets, storage URLs, admin login, search, cart, and queue/scheduler logs. Compare NEXO/PAD health and listening ports with the read-only baseline.

## Backups

Use a Kidoki-only backup directory with restricted permissions:

```sh
PROJECT_DIR=/opt/kidoki BACKUP_DIR=/var/backups/kidoki sh ./deploy/backup.sh
```

The script creates a PostgreSQL custom dump and a storage archive. Copy backups to an independent protected location according to the host backup policy. Test restoration on an isolated database before relying on it.

## Rollback

1. Keep the previous immutable application image tag and release metadata.
2. Restore a Kidoki database dump only into an isolated Kidoki database after verifying compatibility.
3. Restore the Kidoki storage archive into `kidoki_storage` only; never touch NEXO/PAD volumes.
4. Run `sh ./deploy/rollback.sh PREVIOUS_IMAGE_TAG`.
5. Validate `docker compose ... ps`, `/up`, HTTPS, and application smoke tests.
6. Revert only the Kidoki proxy snippet if routing is the cause, validate syntax, then gracefully reload the existing proxy.

Rollback must not restart global Docker, PostgreSQL, Redis, Nginx, NEXO, or PAD services.
