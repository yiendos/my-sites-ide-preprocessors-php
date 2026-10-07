# PHP

PHP for [my-sites-ide](https://github.com/yiendos/my-sites-ide): a php-fpm container that serves your
sites through the web server, and a cli container for artisan, queue workers and other background work.

Written for: developers running sites in my-sites-ide, including ones moving over from the fpm, cron
and cli containers that used to ship inside the IDE.

## Contents

- [Installation](#installation)
- [Upgrading from the built-in PHP containers](#upgrading-from-the-built-in-php-containers)
- [Architecture](#architecture)
- [Command reference](#command-reference)
- [Configuration](#configuration)
- [What it uses from the IDE](#what-it-uses-from-the-ide)
- [Troubleshooting](#troubleshooting)
- [Known gaps](#known-gaps)

## Installation

A [my-sites-ide](https://github.com/yiendos/my-sites-ide) plugin. Add it to the `require` section of the IDE's `composer.local.json`
(the IDE's `composer.local-example.json` already lists it):

```json
"yiendos/my-sites-ide-preprocessors-php": "@dev"
```

Then, from the IDE root:

```
composer update
php my-sites-ide ide:build      # builds the ${NAMESPACE}_fpm and ${NAMESPACE}_cli images, then sparks the IDE
```

Composer's `post-autoload-dump` hook registers the `preprocessors:php-*` commands and the `fpm` and
`cli` compose services. Both have `autostart: true`, so `ide:spark` starts them alongside `APP` - you
don't need to list them in `APP`.

## Upgrading from the built-in PHP containers

| Before (in the IDE) | Now (this plugin) |
|---|---|
| three containers: `fpm`, `cron` and `cli` | two: `fpm` and `cli`. `cron` and `cli` were the same image, both idle (`tail -f /dev/null`) - nothing ran a scheduler - so `cli` now does both jobs |
| `cli` built `FROM ${NAMESPACE}_cron`, so `ide:build` had to build cron first | one `Dockerfile` for both images, picked with the `PHP_SAPI` build argument (`fpm` or `cli`), so they always get the same extensions |
| fpm and cron each had their own copy of `custom.ini` and the xdebug ini | one copy of each, shared |
| background jobs held to fpm's `disable_functions` (the same `PHP_DISABLE_FUNCTIONS`) | cli has its own list, `PHP_CLI_DISABLE_FUNCTIONS`, empty by default - queue workers get `pcntl_*`, jobs can shell out |
| the whole root `.env` passed into every container (`env_file`) | only the `PHP_*` and `XDEBUG_CLIENT_HOST` settings `custom.ini` reads - the rest of the root `.env` (API keys, passwords) stays out |
| `cli`'s PHP settings came only from the root `.env` | fpm and cli read the same settings, with this plugin's `.env` as the defaults |

Recreate the containers once from the plugin's compose file:

```
php my-sites-ide ide:build
```

or, if you've already built the images, `php my-sites-ide preprocessors:php-start`, then
`docker rm -f cron` for the old cron container (`ide:spark` removes it too, as an orphan).

Your existing `.env` can keep `fpm` and `cli` in `APP` - they're de-duplicated. `cron` no longer
exists: `ide:spark` skips it with a warning until you remove it from `APP`.

## Architecture

```
host (my-sites-ide CLI)
  |- ide:spark / ide:restart                --> docker compose up / restart (fpm and cli included)
  |- preprocessors:php-start / -stop        --> docker compose up -d / stop fpm cli
  |- preprocessors:php-artisan <site> -- .. --> docker compose exec -w /opt/repos/<site>/<IDE_APP_DIR> cli php artisan ..

browser --> web server (nginx/apache/caddy plugin) --FastCGI fpm:9000--> fpm container
cli container: artisan, queue:work, schedule:run - whatever you exec into it

both containers
  /opt/repos    <-- Repos/      (your sites)
  /opt/Packages <-- Packages/   (for path repositories into Packages/)
  /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini <-- conf/docker-php-ext-xdebug.ini
```

Both images are the official `php:8.4-*-alpine` images with `redis`, `xdebug` and `pdo_mysql`, and
`mariadb-client`. They run as `webuser` (uid 20008, gid 20009).

## Command reference

| Command | What it does |
|---|---|
| `preprocessors:php-start` | `docker compose up -d fpm cli`. Also recreates running containers whose compose config has changed (e.g. new `PHP_*` values) |
| `preprocessors:php-stop` | `docker compose stop` whichever of fpm and cli is running, leaving the rest of the IDE up. The next `ide:spark` starts them again |
| `preprocessors:php-artisan <site> -- <arguments>` | `php artisan` in `Repos/<site>/<IDE_APP_DIR>`, in the cli container, e.g. `preprocessors:php-artisan example -- migrate` or `-- queue:work --once`. Put artisan's arguments after `--`, or the CLI takes their options as its own. `--dir=<folder>` for a site whose Laravel app is somewhere else (e.g. `--dir=Sites`, or `--dir=.` for the repository root) |

For anything else, `docker compose exec cli sh` gets you a shell.

## Configuration

Every setting has a default in this plugin's `.env`. Set any of them in the IDE's root `.env`, which
wins, then run `preprocessors:php-start` to recreate the containers.
`php my-sites-ide ide:plugin-env yiendos/my-sites-ide-preprocessors-php` copies the main ones in, commented out.

| Variable | Default | What it does |
|---|---|---|
| `PHP_DISABLE_FUNCTIONS` | a list of risky functions (`exec`, `shell_exec`, `system`, `phpinfo`, `pcntl_*`...) | `disable_functions` for **fpm**. Set it to nothing (`PHP_DISABLE_FUNCTIONS=`) to lift every restriction - the IDE's `env-example` does, for development |
| `PHP_CLI_DISABLE_FUNCTIONS` | nothing | `disable_functions` for **cli** |
| `XDEBUG_CLIENT_HOST` | `host.docker.internal` | Where Xdebug connects to your editor (port 9003) |
| `PHP_UPLOAD` | `128M` | `upload_max_filesize` and `post_max_size` |
| `PHP_MAX_SIZE` | `20M` | `post_max_size` - read after `PHP_UPLOAD`, so this is the one that applies |
| `PHP_MEMORY_LIMIT` | `512M` | `memory_limit` |
| `PHP_DISPLAY_ERRORS` / `PHP_DISPLAY_STARTUP_ERRORS` / `PHP_HTML_ERRORS` | `On` | Show errors while developing |
| `PHP_ERROR_REPORTING` / `PHP_LOG_ERRORS` / `PHP_ERROR_LOG` | `On` / `On` / `/dev/stderr` | Error logging, to the container log |
| `PHP_DATE_TIMEZONE` | `UTC` | `date.timezone` |
| `PHP_MAX_EXECUTION_TIME` / `PHP_MAX_INPUT_TIME` | `45` / `60` | Request time limits (fpm - the CLI never times out) |
| `PHP_SESSION_*`, `PHP_SHORT_OPEN_TAG`, `PHP_EXPOSE_PHP`, `PHP_ALLOW_URL_OPEN`, `PHP_DEFAULT_CHARSET` | see `.env` | The rest of `conf/custom.ini` |

`conf/custom.ini` falls back to production-safe values (errors hidden, the full `disable_functions`
list) for any variable that isn't set at all - which is what a deployed image, run without this
plugin's environment, gets.

Xdebug's own settings are in `conf/docker-php-ext-xdebug.ini`, mounted rather than built in: edit
it, then `preprocessors:php-start`.

## What it uses from the IDE

| From the IDE | Used for |
|---|---|
| `NAMESPACE` (root `.env`) | the image names, `${NAMESPACE}_fpm` and `${NAMESPACE}_cli` |
| the `my-sites-ide` network | web servers reaching fpm as `fpm:9000`; sites reaching the database, redis, mailhog |
| `IDE_ROOT` (set by the CLI and `_dev/cache/ide.env`) | the `Repos/` and `Packages/` mounts, finding `Repos/<site>` |
| `IDE_APP_DIR` (root `.env`, set by the CLI - `deploy` if it isn't) | which folder in `Repos/<site>/` holds the app - where `preprocessors:php-artisan` runs |

## Troubleshooting

**`Call to undefined function exec()` (or `shell_exec`, `pcntl_signal`...) in a web request.** fpm's
`disable_functions` list. Put `PHP_DISABLE_FUNCTIONS=` in the root `.env`, then `preprocessors:php-start`.
In a queue worker or artisan command, run it in `cli` instead of fpm.

**`ide:spark` warns `Skipping cron - no such service`.** Remove `cron` from `APP` in the root `.env` -
its job is `cli`'s now.

**`502 Bad Gateway` from the web server.** fpm isn't running: `php my-sites-ide preprocessors:php-start`.

**A changed `PHP_*` value has no effect.** The containers read their environment when they're
created: `preprocessors:php-start` recreates them.

## Known gaps

- Nothing runs the Laravel scheduler or queue workers - `cli` waits to be exec'd into, as the old
  cron and cli containers did. Running `schedule:run` for each site is a planned follow-up.
- PHP 8.4 only - the version is fixed in the `Dockerfile`.
- Extensions are fixed at build time (`PHP_PECL_EXTS`, plus `pdo_mysql`) - adding one means editing
  the `Dockerfile` and rebuilding.
