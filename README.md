SLiMS 9 Bulian
===============
SENAYAN Library Management System (SLiMS) version 9 Codename Bulian

SLiMS is free open source software for library resources management
(such as books, journals, digital document and other library materials)
and administration such as collection circulation, collection management,
membership, stock taking and many other else.

SLiMS is licensed under GNU GPL version 3. Please read "GPL-3.0 License.txt"
to learn more about GPL.

### System Requirements
- PHP version >= 8.1;
- MySQL version 5.7 and or MariaDB version 10.3;
- PHP GD enabled
- PHP gettext enabled
- PHP mbstring enabled

### Running with Docker

Copy `.env.example` to `.env`, set `DB_PASS` and `DB_ROOT_PASS`, then run:

```shell
docker compose up -d --build
docker compose logs slims | grep -A3 "ADMIN CREDENTIALS"
```

The image runs PHP 8.3.36 by default (PHP 8.4.27 is also supported via `PHP_IMAGE`).
See `docs/deployment/docker.md` (Bahasa Indonesia) for configuration, HTTPS and
upgrading from the previous compose file, and `docs/fork/maintenance.md` for local
tests (`tools/test/run-local.sh`) and syncing with upstream.
