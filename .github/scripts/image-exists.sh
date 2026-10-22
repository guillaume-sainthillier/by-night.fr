#!/usr/bin/env bash
# Usage: image-exists.sh <image:tag>
# Writes exists=true|false to $GITHUB_OUTPUT. Only the registry's "not found" means the tag
# is missing: any other error (429 rate limit, 5xx) is retried with a growing, jittered delay,
# then fails the job, instead of starting a rebuild that would hit the same limit.
set -euo pipefail

image=$1
for attempt in 1 2 3 4; do
    if out=$(docker buildx imagetools inspect "$image" 2>&1); then
        echo "exists=true" >> "$GITHUB_OUTPUT"
        echo "::notice::$image is already published — skipping (tags are immutable, bump docker/base/VERSION to publish changes)"
        exit 0
    fi
    if grep -q ': not found$' <<< "$out"; then
        echo "exists=false" >> "$GITHUB_OUTPUT"
        exit 0
    fi
    [ "$attempt" -eq 4 ] && break
    delay=$((30 * 2 ** (attempt - 1) + RANDOM % 30))
    echo "::warning::Could not check $image (attempt $attempt of 4), retrying in ${delay}s: $out"
    sleep "$delay"
done
echo "::error::Could not check whether $image exists: $out"
exit 1
