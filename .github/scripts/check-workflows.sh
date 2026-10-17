#!/usr/bin/env bash
#
# Checks the supply-chain rules for GitHub Actions in the technical design:
# - every action is pinned to a full commit SHA (local ./ actions and
#   docker:// images with a digest pass);
# - no workflow uses the pull_request_target trigger.

set -euo pipefail

status=0
while IFS= read -r line; do
    file="${line%%:*}"
    rest="${line#*:}"
    lineno="${rest%%:*}"
    ref=$(sed -E 's/^[[:space:]-]*uses:[[:space:]]*//; s/[[:space:]]+#.*$//; s/^["'\'']//; s/["'\'']$//' <<< "${rest#*:}")

    if [[ "$ref" == ./* ]]; then
        continue
    fi
    if [[ "$ref" =~ ^docker://.+@sha256:[0-9a-f]{64}$ ]]; then
        continue
    fi
    if [[ "$ref" =~ ^[^@[:space:]]+@[0-9a-f]{40}$ ]]; then
        continue
    fi

    echo "::error file=$file,line=$lineno::'$ref' is not pinned to a full commit SHA."
    status=1
done < <(grep -nHE '^[[:space:]-]*uses:' .github/workflows/*.yml .github/actions/*/action.yml 2>/dev/null || true)

while IFS= read -r line; do
    echo "::error file=${line%%:*}::pull_request_target is not allowed; use pull_request."
    status=1
done < <(grep -lE '^[[:space:]]*(-[[:space:]]*)?pull_request_target[[:space:]]*(:|$)|^[[:space:]]*on:.*[^a-z_]pull_request_target([^a-z_]|$)' .github/workflows/*.yml 2>/dev/null || true)

if [[ "$status" -eq 0 ]]; then
    echo "Every action is pinned to a commit SHA and no workflow uses pull_request_target."
fi
exit "$status"
