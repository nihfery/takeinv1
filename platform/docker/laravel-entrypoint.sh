#!/bin/sh

# This entrypoint intentionally never runs fresh, migrate:fresh, db:wipe, or
# any command that drops tables. A Dokploy redeploy may update the application
# and run pending migrations, but the existing MySQL named volume is preserved.
set -eu

assert_no_symlink_components() {
    target_path="$1"
    current_path=""
    remaining_path="${target_path#/}"

    while [ -n "$remaining_path" ]; do
        case "$remaining_path" in
            */*)
                path_component="${remaining_path%%/*}"
                remaining_path="${remaining_path#*/}"
                ;;
            *)
                path_component="$remaining_path"
                remaining_path=""
                ;;
        esac

        current_path="${current_path}/${path_component}"
        if [ -L "$current_path" ]; then
            printf 'Refusing writable path with symlink component: %s\n' "$current_path" >&2
            exit 65
        fi
    done
}

normalize_laravel_writable_tree() {
    target_path="$1"
    boundary_path="$2"
    directory_mode="$3"
    file_mode="$4"

    assert_no_symlink_components "$target_path"
    mkdir -p -- "$target_path"

    resolved_boundary="$(readlink -f -- "$boundary_path")"
    resolved_target="$(readlink -f -- "$target_path")"
    case "$resolved_target" in
        "$resolved_boundary"|"$resolved_boundary"/*) ;;
        *)
            printf 'Writable path escaped its boundary: %s\n' "$target_path" >&2
            exit 65
            ;;
    esac

    if find -P "$target_path" -xdev -type l -print -quit | grep -q .; then
        printf 'Refusing writable tree containing a symlink: %s\n' "$target_path" >&2
        exit 65
    fi

    chown -R --no-dereference www-data:www-data "$target_path"
    find -P "$target_path" -xdev -type d -exec chmod "$directory_mode" {} +
    find -P "$target_path" -xdev -type f -exec chmod "$file_mode" {} +
}

if [ "${LARAVEL_PROCESS:-backend}" != "backend" ]; then
    printf 'Refusing to run the migration entrypoint for process %s.\n' \
        "${LARAVEL_PROCESS:-unknown}" >&2
    exit 64
fi

php artisan config:clear

if [ "${RUN_DATABASE_MIGRATIONS:-false}" = "true" ]; then
    # Create a point-in-time SQL backup only when at least one migration is
    # pending. The backup lives on persistent private storage. Any failed
    # backup aborts deployment before schema changes.
    migration_status="$(php artisan migrate:status --pending --no-ansi 2>&1 || true)"
    if [ "${DEPLOY_BACKUP_BEFORE_MIGRATE:-true}" = "true" ] \
        && ! printf '%s' "$migration_status" | grep -q "No pending migrations"; then
        backup_directory="/var/www/html/storage/app/deployment-backups"
        backup_timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
        backup_file="${backup_directory}/pre-migrate-${backup_timestamp}.sql"

        mkdir -p "$backup_directory"
        MYSQL_PWD="${DB_PASSWORD:-}" mysqldump \
            --host="${DB_HOST:-db}" \
            --port="${DB_PORT:-3306}" \
            --user="${DB_USERNAME:-youyaku}" \
            --single-transaction \
            --quick \
            --skip-lock-tables \
            --triggers \
            --no-tablespaces \
            "${DB_DATABASE:-youyaku}" > "$backup_file"

        chmod 600 "$backup_file"
        gzip "$backup_file"
        printf 'Database backup created: %s.gz\n' "$backup_file"
    fi

    php artisan migrate --force --no-interaction
    php artisan app:deployment-check
else
    printf 'Database migrations disabled for this backend process.\n'
fi

# The public storage symlink may already exist on a persistent volume. That is
# harmless; a failed symlink creation must not hide a failed migration above.
php artisan storage:link >/dev/null 2>&1 || true

# Older development-server deployments ran as root and may have left persistent
# upload or framework subdirectories unwritable by FPM's www-data workers.
# Normalize only the explicit Laravel writable trees. Public objects remain readable by the
# read-only Nginx container; private/runtime trees are owner/group-only. Abort
# on any symlink rather than traversing an unexpected target.
normalize_laravel_writable_tree /var/www/html/storage/app/public /var/www/html/storage 0755 0644
normalize_laravel_writable_tree /var/www/html/storage/app/private /var/www/html/storage 0750 0640
normalize_laravel_writable_tree /var/www/html/storage/framework/cache /var/www/html/storage 0750 0640
normalize_laravel_writable_tree /var/www/html/storage/framework/sessions /var/www/html/storage 0750 0640
normalize_laravel_writable_tree /var/www/html/storage/framework/views /var/www/html/storage 0750 0640
normalize_laravel_writable_tree /var/www/html/storage/logs /var/www/html/storage 0750 0640
normalize_laravel_writable_tree /var/www/html/bootstrap/cache /var/www/html/bootstrap 0750 0640

# HTTP is terminated by the dedicated backend-http Nginx service. Keep FPM in
# the foreground so Compose owns the process lifecycle and no request query
# string is written by PHP's development-server access logger.
exec php-fpm -F
