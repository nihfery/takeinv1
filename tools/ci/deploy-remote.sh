#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 6 ]]; then
    echo "Usage: deploy-remote.sh <sha> <repository-path> <env-file> <compose-project> <allowed-branch> <image-prefix>" >&2
    exit 2
fi

deploy_sha="$1"
repository_path="$2"
environment_file="$3"
compose_project="$4"
allowed_branch="$5"
image_prefix="$6"

[[ "${deploy_sha}" =~ ^[a-f0-9]{40}$ ]] || { echo "Invalid deployment SHA." >&2; exit 2; }
[[ "${repository_path}" =~ ^/[A-Za-z0-9._/-]+$ ]] || { echo "Invalid repository path." >&2; exit 2; }
[[ "${environment_file}" =~ ^/[A-Za-z0-9._/-]+$ ]] || { echo "Invalid environment file path." >&2; exit 2; }
[[ "${compose_project}" =~ ^[a-z0-9][a-z0-9_-]{1,62}$ ]] || { echo "Invalid Compose project." >&2; exit 2; }
[[ "${allowed_branch}" =~ ^[A-Za-z0-9._/-]+$ ]] || { echo "Invalid deployment branch." >&2; exit 2; }
[[ "${image_prefix}" =~ ^ghcr\.io/[a-z0-9._/-]+$ ]] || { echo "Invalid image prefix." >&2; exit 2; }

cd "${repository_path}"
repository_root="$(git rev-parse --show-toplevel)"
repository_root="$(realpath -e "${repository_root}")"
environment_file="$(realpath -e "${environment_file}")"

if [[ "${repository_root}" != "$(realpath -e "${repository_path}")" ]]; then
    echo "DEPLOY_PATH must be the root of the remote Git worktree." >&2
    exit 2
fi

if [[ "${environment_file}" == "${repository_root}" || "${environment_file}" == "${repository_root}"/* ]]; then
    echo "The deployment environment file must remain outside the Git worktree." >&2
    exit 2
fi

if [[ -L "${environment_file}" || ! -f "${environment_file}" ]]; then
    echo "The deployment environment file must be a regular, non-symlink file." >&2
    exit 2
fi

if [[ -n "$(git status --porcelain)" ]]; then
    echo "Remote deployment worktree is dirty; refusing to overwrite operator changes." >&2
    exit 1
fi

git fetch --prune --no-tags origin "${allowed_branch}"
git cat-file -e "${deploy_sha}^{commit}"

if ! git merge-base --is-ancestor "${deploy_sha}" FETCH_HEAD; then
    echo "Requested commit is not reachable from origin/${allowed_branch}." >&2
    exit 1
fi

git checkout --detach "${deploy_sha}"

export LARAVEL_IMAGE="${image_prefix}/laravel:sha-${deploy_sha}"
export BACKEND_HTTP_IMAGE="${image_prefix}/backend-http:sha-${deploy_sha}"

compose=(docker compose --project-name "${compose_project}" --env-file "${environment_file}")
"${compose[@]}" config --quiet
"${compose[@]}" pull backend backend-http
"${compose[@]}" build customer provider
"${compose[@]}" up -d --no-build --pull missing --remove-orphans --wait --wait-timeout 240
"${compose[@]}" exec -T --user www-data backend php artisan app:deployment-check
"${compose[@]}" exec -T --user www-data backend php artisan migrate:status --pending
"${compose[@]}" exec -T backend-http wget -q -O /dev/null http://127.0.0.1:8080/api/readiness
"${compose[@]}" ps

echo "Remote deployment completed for ${deploy_sha}."
