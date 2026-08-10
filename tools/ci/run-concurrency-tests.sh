#!/usr/bin/env bash
set -euo pipefail

repository_root="$(git rev-parse --show-toplevel)"
backend_root="${repository_root}/backend/laravel-core"
test_file="${backend_root}/tests/Concurrency/BookingConcurrencyTest.php"

if [[ "${DB_CONNECTION:-}" != "mysql" ]]; then
    echo "Concurrency tests require DB_CONNECTION=mysql; SQLite is not an accepted substitute." >&2
    exit 2
fi

if [[ ! -f "${test_file}" ]]; then
    echo "Required real-process concurrency test is missing: ${test_file}" >&2
    exit 2
fi

cd "${backend_root}"
php artisan test --testsuite=Concurrency --colors=never
