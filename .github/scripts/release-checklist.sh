#!/usr/bin/env bash
#
# Fails a release pull request while any checklist box in its description is
# unticked. Other pull requests pass.
#
# A pull request is a release when its branch starts with "release/" or a line
# of its description is the marker from .github/PULL_REQUEST_TEMPLATE/release.md.
#
# Input (environment): PR_BODY, the pull request description, and HEAD_REF,
# the name of its branch. TEMPLATE overrides the path of the release template.

set -euo pipefail

marker='<!-- abonemen:release-checklist -->'
body="${PR_BODY:-}"
head_ref="${HEAD_REF:-}"

has_marker=false
# Only a line that holds just the marker counts, so a description that quotes
# the marker in passing is not mistaken for a release.
if tr -d '\r' <<< "$body" | grep -qxE -- "[[:space:]]*${marker}[[:space:]]*"; then
    has_marker=true
fi

if [[ "$head_ref" != release/* && "$has_marker" == false ]]; then
    echo "Not a release pull request; nothing to check."
    exit 0
fi

if [[ "$has_marker" == false ]]; then
    echo "::error::Branch '$head_ref' is a release branch, but the description does not use the release template."
    echo "Open the pull request with ?template=release.md, or paste .github/PULL_REQUEST_TEMPLATE/release.md into the description."
    exit 1
fi

ticked=$(grep -cE '^[[:space:]]*[-*+][[:space:]]+\[[xX]\]' <<< "$body" || true)
unticked=$(grep -E '^[[:space:]]*[-*+][[:space:]]+\[[[:space:]]\]' <<< "$body" || true)

if [[ "$ticked" -eq 0 && -z "$unticked" ]]; then
    echo "::error::The release checklist is missing from the description."
    exit 1
fi

if [[ -n "$unticked" ]]; then
    echo "::error::The release checklist has unticked boxes. Do each check, then tick its box:"
    echo "$unticked"
    exit 1
fi

# Deleting a box must not be a way to skip a check.
template="${TEMPLATE:-.github/PULL_REQUEST_TEMPLATE/release.md}"
if [[ -f "$template" ]]; then
    required=$(grep -cE '^[[:space:]]*[-*+][[:space:]]+\[[[:space:]]\]' "$template" || true)
    if [[ "$ticked" -lt "$required" ]]; then
        echo "::error::The release template has $required checklist items, but only $ticked are ticked. Restore the missing items."
        exit 1
    fi
fi

echo "All $ticked release checklist items are ticked."
