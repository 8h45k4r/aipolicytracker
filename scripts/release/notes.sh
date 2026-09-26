#!/usr/bin/env bash
#
# Prints the CHANGELOG.md section for one version, without its heading, as the body
# of a GitHub release. The changelog is the only place release notes are written;
# this reads it rather than asking anyone to write them twice.
#
#   scripts/release/notes.sh 1.0.0
#
# Exits non-zero when the version has no section, or an empty one, so a tag pushed
# before the changelog was cut fails loudly instead of publishing a blank release.

set -euo pipefail

version="${1:?usage: notes.sh <version, e.g. 1.0.0>}"
changelog="${2:-CHANGELOG.md}"

[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "not a semantic version: $version" >&2; exit 2; }

notes="$(awk -v v="$version" '
    index($0, "## [" v "]") == 1 { found = 1; next }
    found && /^## \[/ { exit }
    found && /^\[[^]]+\]: / { exit }
    found { print }
' "$changelog")"

# Drop leading and trailing blank lines.
notes="$(printf '%s\n' "$notes" | sed -e '/./,$!d' | sed -e ':a' -e '/^\n*$/{$d;N;ba' -e '}')"

[ -n "$notes" ] || { echo "CHANGELOG.md has no notes for $version" >&2; exit 1; }

printf '%s\n\n**Full changelog:** [CHANGELOG.md](https://github.com/8h45k4r/aipolicytracker/blob/v%s/CHANGELOG.md)\n' "$notes" "$version"
