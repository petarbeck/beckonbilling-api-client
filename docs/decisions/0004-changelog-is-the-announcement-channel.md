# 0004 - The changelog is the announcement channel, and it is never rewritten

**Status:** Accepted

**Date:** 2026-08-11 (`CHANGELOG.md` `[0.10.0]`)

## Context

`/api/v1` has consumers outside the projects that build it. There is no
version handshake, no announcement list and no deprecation feed: a consumer
learns of a change when it touches a version constraint, or when something
breaks. What exists instead is this repository, published as one unit -
`CHANGELOG.md`, `openapi.yaml`, `AGENTS.md`, `llms.txt` and `README.md` - and
a version number that is a human's judgement
([0003](0003-the-git-tag-is-the-version.md)).

That channel was tested on 2026-08-11. 0.9.0 announced a breaking change:
an unknown `unit` would start answering 422 `unit_unknown`, and callers were
told their writes would begin to fail. The refusal never reached a deployed
server - it was reversed before release, an hour later. The tempting repair
was to edit 0.9.0's entry so the file would read as if the wrong claim had
never been made.

## Decision

The changelog is **append-only**, and it is written as an announcement to a
reader who cannot ask a question.

- A withdrawn claim is retracted by a **new entry that names the entry it
  retracts**, never by editing the old one. `[0.10.0]` is headed "Changed
  (BREAKING, and it retracts a claim 0.9.0 made an hour earlier)" and says
  in as many words: "If you changed anything because of 0.9.0's note, you
  can change it back." `[0.10.0]`'s "Note" section then states which of
  0.9.0's other claims still stand.
- An entry states what a caller must **do**, not what a commit touched:
  which key moved, what the wire does in the meantime, and whether anything
  breaks today.
- `[Unreleased]` accumulates entries between tags and is the section a
  consumer reads to see what the next release will contain.
- Because the version number is a judgement, the entry carries the
  reasoning behind that judgement - including, where the number cannot yet
  be known, the reason it is not stated (0003,
  [0008](0008-document-uuids-replace-document-ids.md)).

## Consequences

- A wrong entry stays in the file forever. That is the cost, and it buys the
  property that makes the channel usable: a consumer diffing two versions
  sees every claim ever made to them, including the withdrawn ones, and
  never finds a claim silently gone.
- Nothing here notifies anybody. A consumer has to look, and the reach of a
  change is communicated only through the version range they pinned.
  Whether that is sufficient is not settled by this ADR - vorgang
  `2026-08-14-externe-konsumenten-api-v1` is open on exactly that question.
- A contract correction is a changelog entry like any other, even when
  nothing on the wire changed - see
  [0006](0006-orders-are-in-the-contract-invoicing-is-not.md) and
  [0007](0007-readonly-only-where-the-server-refuses-the-key.md), both filed
  under "Fixed (documentation only - no server change)".

## References

`CHANGELOG.md` (`[Unreleased]`, `[0.9.0]`, `[0.10.0]` "Changed" and "Note");
`openapi.yaml` (`info.description`, "Deprecations"); vorgang
`2026-08-14-externe-konsumenten-api-v1`.
