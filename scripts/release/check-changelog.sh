#!/usr/bin/env bash
#
# Checks that CHANGELOG.md can be released from: an "## [Unreleased]" section first,
# released versions below it in descending semantic-version order with a date, and no
# version or section type repeated. Release notes are read from this file, so a broken
# changelog is a broken release.
#
#   scripts/release/check-changelog.sh [CHANGELOG.md]

set -euo pipefail

changelog="${1:-CHANGELOG.md}"
fail=0
err() { echo "CHANGELOG: $*" >&2; fail=1; }

first="$(grep -m1 -E '^## ' "$changelog" || true)"
[ "$first" = "## [Unreleased]" ] || err "the first section must be \"## [Unreleased]\" (found \"$first\")"

prev=""
while IFS= read -r line; do
    version="$(printf '%s' "$line" | sed -nE 's/^## \[([0-9]+\.[0-9]+\.[0-9]+)\] - [0-9]{4}-[0-9]{2}-[0-9]{2}.*/\1/p')"
    if [ -z "$version" ]; then
        err "a released section needs \"## [X.Y.Z] - YYYY-MM-DD\": $line"
        continue
    fi
    if [ -n "$prev" ] && [ "$(printf '%s\n%s\n' "$version" "$prev" | sort -V | tail -1)" != "$prev" ]; then
        err "versions must descend: $version comes after $prev"
    fi
    [ "$version" != "$prev" ] || err "version $version appears twice"
    prev="$version"
done < <(grep -E '^## \[' "$changelog" | grep -v '^## \[Unreleased\]')

# Within one version, each Keep a Changelog section type appears once.
awk '
    /^## \[/ { version = $0; delete seen; next }
    /^### / {
        if (seen[$0]++) { printf "CHANGELOG: \"%s\" appears twice under \"%s\"\n", $0, version > "/dev/stderr"; bad = 1 }
    }
    END { exit bad }
' "$changelog" || fail=1

[ "$fail" -eq 0 ] && echo "CHANGELOG.md is releasable."
exit "$fail"
