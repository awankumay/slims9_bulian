# Sourced by the MariaDB entrypoint on first initialization only (empty data volume).
# Loads the SLiMS schema, and the sample collection when LOAD_SAMPLE_DATA=true.

echo "SLiMS: loading schema from /slims-sql/senayan.sql"
docker_process_sql --database="$MARIADB_DATABASE" < /slims-sql/senayan.sql

case "${LOAD_SAMPLE_DATA:-false}" in
    true|1|yes)
        echo "SLiMS: loading sample data from /slims-sql/sampledata.sql"
        docker_process_sql --database="$MARIADB_DATABASE" < /slims-sql/sampledata.sql
        ;;
    *)
        echo "SLiMS: sample data skipped (LOAD_SAMPLE_DATA=${LOAD_SAMPLE_DATA:-false})"
        ;;
esac
