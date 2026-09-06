# 0003 - The git tag is the version; MINOR and MAJOR are a human's call

**Status:** Accepted

**Date:** 2026-07-31 (`CHANGELOG.md` `[0.6.1]`; commits `b1232cf`, `eded27b`)

## Context

This package is published on Packagist and consumed through Composer by
callers who are not part of the projects that build the API. They have no
announcement channel other than what is published here
([0004](0004-changelog-is-the-announcement-channel.md)), so the version
number is not decoration: it is the only thing that tells a consumer whether
an upgrade can break them, and a constraint like `^0.15.0` is the only
protection they have.

Two failure modes were closed by hand in July 2026. A release script that
bumps MINOR whenever it sees "enough" changes turns the number into noise -
`eded27b` ("0.6.2, not 0.7.0 - minor is a human decision") is the correction.
And a `version` key in `composer.json` is a second answer to a question the
git tag already answers, free to disagree with it.

## Decision

The library follows Semantic Versioning, pre-1.0, and:

- **The source of the version is the git tag.** `tools/version.sh` derives
  the current version from `git tag --list 'v[0-9]*' --sort=-v:refname`;
  `composer.json` deliberately carries no `version` key, because Composer
  derives a library's version from its tags.
- **PATCH is automatic** - once per release that ships something.
- **MINOR is a human's call**, after a substantial round of contract work,
  and resets PATCH.
- **MAJOR is a human's call**, and only for a breaking contract change.
  Nothing about the shape of a diff earns it (`tools/release.sh`, the
  comment above the bump); `tools/release.sh` requires an explicit flag
  (`b1232cf`).

## Consequences

- **A future version number can never be promised in advance.** A
  deprecation window can be expressed in versions ("removed in the next
  major release") but not in numbers, because the number does not exist
  until someone decides to type it. This is why
  [0008](0008-document-uuids-replace-document-ids.md) names no number at
  either end of its window.
- Consumers pin on a range and learn the reach of a change from the range,
  not from a date. That places the whole burden of correct classification on
  the human doing the tag.
- Because the number is a judgement and not a computation, the changelog
  entry has to carry the reasoning - see 0004.
- A tag can be forgotten, and one was: `v0.14.0` was never created, so the
  tag list skips from `v0.13.0` to `v0.15.0` while `CHANGELOG.md` documents
  `[0.14.0]` in full. Nothing in the tooling detects a missing tag, and a
  consumer pinning that release cannot install it.

## References

`CHANGELOG.md` (`[0.6.1]`, "Changed"); `tools/version.sh`;
`tools/release.sh`; `composer.json`; commits `b1232cf`, `eded27b`;
vorgang `2026-08-14-externe-konsumenten-api-v1`.
