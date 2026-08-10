#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 2 ]]; then
    echo "Usage: $0 <owner/repository> <full-commit-sha>" >&2
    exit 2
fi

repository="$1"
commit_sha="$2"

[[ "${repository}" =~ ^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$ ]] || { echo "Invalid repository name." >&2; exit 2; }
[[ "${commit_sha}" =~ ^[a-f0-9]{40}$ ]] || { echo "A full lowercase commit SHA is required." >&2; exit 2; }
command -v gh >/dev/null 2>&1 || { echo "GitHub CLI is required." >&2; exit 2; }

workflows=(
    backend-quality.yml
    backend-tests.yml
    backend-security.yml
    customer-web.yml
    provider-landing.yml
    contract-tests.yml
    concurrency-tests.yml
    dependency-scan.yml
    secret-scan.yml
    container-scan.yml
    build-images.yml
)

for workflow in "${workflows[@]}"; do
    conclusion="$(
        gh api \
            --method GET \
            -H 'Accept: application/vnd.github+json' \
            "repos/${repository}/actions/workflows/${workflow}/runs" \
            -f "head_sha=${commit_sha}" \
            -f status=completed \
            -f per_page=20 \
            --jq '.workflow_runs | sort_by(.run_attempt, .run_number) | reverse | .[0].conclusion // "missing"'
    )"

    if [[ "${conclusion}" != "success" ]]; then
        echo "Required workflow ${workflow} is not successful for ${commit_sha} (result: ${conclusion})." >&2
        exit 1
    fi

    echo "${workflow}: success"
done
