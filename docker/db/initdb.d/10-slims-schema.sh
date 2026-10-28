#!/bin/bash
# Run by the MariaDB entrypoint on first initialization only (empty data volume).
# Loads the SLiMS schema, and the sample collection when LOAD_SAMPLE_DATA=true.
# It may be executed (bind mounts from Windows are +x) instead of sourced,
# so it uses the mariadb client rather than the entrypoint's docker_process_sql.

set -e

echo "SLiMS: loading schema from /slims-sql/senayan.sql"
mariadb --protocol=socket -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE" < /slims-sql/senayan.sql

case "${LOAD_SAMPLE_DATA:-false}" in
    true|1|yes)
        echo "SLiMS: loading sample data from /slims-sql/sampledata.sql"
        mariadb --protocol=socket -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE" < /slims-sql/sampledata.sql
        ;;
    *)
        echo "SLiMS: sample data skipped (LOAD_SAMPLE_DATA=${LOAD_SAMPLE_DATA:-false})"
        ;;
esac
