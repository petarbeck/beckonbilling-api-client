#!/usr/bin/env bash
#
# Read or compute the API client's version.
#
#   tools/version.sh              print the current version
#   tools/version.sh patch        +1 patch   - one per release
#   tools/version.sh minor        +1 minor, patch -> 0
#   tools/version.sh major        +1 major, minor -> 0, patch -> 0
#
# Who decides what - the same rules as the portal (docs/deployment-sunos.md
# there), so the two move independently but by one set of rules:
#
#   MAJOR  a human, deliberately. Nothing suggests it. For a library it also
#          means a BREAKING contract change, which is the only thing that should
#          force a consumer to act.
#   MINOR  a human, after a substantial round of contract work.
#   PATCH  one per release that actually ships something.
#
# The SOURCE is the git tag, not a field in composer.json. Composer derives a
# library's version from its tags, and a `version` key in composer.json is
# discouraged precisely because there would then be two answers that can
# disagree. So this reads the newest v* tag and prints what the next one should
# be. It writes nothing and tags nothing - `tools/release.sh` does that, once the
# work is committed.

set -euo pipefail

cd "$( dirname "$0" )/.."

VERSION="$( git tag --list 'v[0-9]*' --sort=-v:refname | head -1 | sed 's/^v//' )"
[ -n "$VERSION" ] || VERSION="0.0.0"
IFS='.' read -r MAJOR MINOR PATCH <<< "$VERSION"

case "${1:-show}" in
    show)  echo "$VERSION" ;;
    patch) echo "$MAJOR.$MINOR.$(( PATCH + 1 ))" ;;
    # Resetting is the point: patch counts releases WITHIN a minor.
    minor) echo "$MAJOR.$(( MINOR + 1 )).0" ;;
    major) echo "$(( MAJOR + 1 )).0.0" ;;
    *)     echo "usage: $0 [show|patch|minor|major]" >&2; exit 2 ;;
esac
