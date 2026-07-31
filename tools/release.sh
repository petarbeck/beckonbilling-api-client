#!/usr/bin/env bash
#
# Tag and publish a release.
#
#   tools/release.sh              patch release (the normal case)
#   tools/release.sh minor        minor release, patch resets to 0
#   tools/release.sh major        major release - a BREAKING contract change
#   tools/release.sh --dry-run    show what would happen
#
# Refuses to release when there is nothing to release, or when the tree is
# dirty: a tag has to point at a commit that exists on the remote, or a consumer
# resolves a version whose code nobody else can fetch.

set -euo pipefail

cd "$( dirname "$0" )/.."

LEVEL="patch"
DRY=0
CONFIRMED=0
for arg in "$@"; do
    case "$arg" in
        patch|minor|major) LEVEL="$arg" ;;
        --i-decided) CONFIRMED=1 ;;
        --dry-run) DRY=1 ;;
        *) echo "usage: $0 [patch|minor|major] [--i-decided] [--dry-run]" >&2; exit 2 ;;
    esac
done

# MINOR and MAJOR are a human's call. Nothing about the shape of a change earns
# one - not "a field was removed so it is breaking", which is semver's rule and
# not this project's. Requiring an explicit flag is what stops that reasoning
# from quietly substituting itself; it has already happened once.
if [ "$LEVEL" != "patch" ] && [ "$CONFIRMED" -eq 0 ]; then
    echo "!! $LEVEL is a human decision - re-run with --i-decided if that is what you want" >&2
    echo "   (patch is the default, and is what a routine release should be)" >&2
    exit 1
fi

CURRENT="$( tools/version.sh )"
NEXT="$( tools/version.sh "$LEVEL" )"

if [ -n "$( git status --porcelain )" ]; then
    echo "!! working tree is dirty - commit first, so the tag points at what you tested" >&2
    exit 1
fi

# "Nothing to release" is a real answer, not an error to work around.
LAST_TAG="$( git tag --list 'v[0-9]*' --sort=-v:refname | head -1 )"
if [ -n "$LAST_TAG" ] && [ "$( git rev-list -n1 "$LAST_TAG" )" = "$( git rev-parse HEAD )" ]; then
    echo ">> HEAD is already released as $LAST_TAG - nothing to do"
    exit 0
fi

echo ">> $CURRENT -> $NEXT ($LEVEL)"

if [ "$DRY" -eq 1 ]; then
    echo ">> (dry run) would run the tests, tag v$NEXT and push"
    exit 0
fi

echo ">> running tests…"
php vendor/bin/phpunit --no-coverage >/dev/null

git push
git tag -a "v$NEXT" -m "v$NEXT"
git push origin "v$NEXT"

echo ">> released v$NEXT"
echo ">> remember: CHANGELOG.md should already name this version"
