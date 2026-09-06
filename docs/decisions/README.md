# Architectural Decision Records

This directory holds ADRs for the Beckon Billing PHP API client: the
context, the decision itself, and the consequences, for anything that
affects more than one file - and, because this repository owns the
canonical `/api/v1` contract, for anything that affects a consumer of that
contract. A one-file fix, a typo, a new `@property-read` hint: those are
plain commits and a `CHANGELOG.md` entry, not an ADR.

ADRs are **append-only**: a decision is never rewritten in place. Changing a
decision means writing a new ADR that supersedes the old one, in the same
commit that flips the old ADR's Status. It is the same rule the changelog
follows, and for the same reason - see
[0004](0004-changelog-is-the-announcement-channel.md).

`CHANGELOG.md` says *what changed and what a caller must do*; an ADR says
*why it is that way and what it rules out*. They are cross-referenced, not
merged.

Some ADRs cite a `vorgang <date>-<slug>`: an internal cross-component
record, named so a maintainer can follow it. It is not published here, and
neither is its content - see
[0009](0009-coordination-notes-stay-out-of-this-repository.md).

## Status values

| Status | Meaning |
| --- | --- |
| `Proposed` | Written, not yet acted on. |
| `Accepted` | The decision stands and the repository matches it. |
| `Implemented` | Accepted and specifically verified in the codebase or against a released version. |
| `Superseded by NNNN` | A later ADR replaced this decision; this file is kept for history and is never edited to match the new state. |
| `Declined` | Considered - often across components - and explicitly not adopted. Kept so the question is not reopened without new information. |

## Index

| #    | Title | Status | Date |
| ---- | ----- | ------ | ---- |
| [0001](0001-openapi-yaml-is-the-canonical-contract.md) | `openapi.yaml` in this repository is the canonical `/api/v1` contract | Accepted | 2026-07-25 |
| [0002](0002-hand-written-thin-client-no-generator.md) | A hand-written thin client, not a generated one | Accepted | 2026-07-24 |
| [0003](0003-the-git-tag-is-the-version.md) | The git tag is the version; MINOR and MAJOR are a human's call | Accepted | 2026-07-31 |
| [0004](0004-changelog-is-the-announcement-channel.md) | The changelog is the announcement channel, and it is never rewritten | Accepted | 2026-08-11 |
| [0005](0005-errors-are-typed-and-branch-on-error-key.md) | Errors are typed classes, and callers branch on `error.key` | Accepted | 2026-07-24 |
| [0006](0006-orders-are-in-the-contract-invoicing-is-not.md) | The order is part of this contract; invoicing one is not | Accepted | 2026-09-01 |
| [0007](0007-readonly-only-where-the-server-refuses-the-key.md) | `readOnly` is claimed only where the server really refuses the key | Accepted | 2026-09-06 |
| [0008](0008-document-uuids-replace-document-ids.md) | `document_uuids` replaces `document_ids`; the window closes at the next major | Accepted | 2026-09-06 |
| [0009](0009-coordination-notes-stay-out-of-this-repository.md) | Coordination notes stay out of this repository | Accepted | 2026-09-06 |

## Adding a new ADR

1. Pick the next sequential number (`NNNN`), padded to four digits. The file
   is `NNNN-<slug>.md`, with no `ADR-` prefix.
2. Keep the shape of an existing ADR: `Status`, `Date`, `Context`,
   `Decision`, `Consequences`, `References`. The `Date` is the date of the
   original decision where that is provable, otherwise the date of the
   evidence.
3. Every claim about code names a file plus a symbol (class, method,
   schema, `operationId`, key). No claim without evidence.
4. Add a row to the index above.
5. If this supersedes an existing ADR, flip the old ADR's Status to
   `Superseded by NNNN-...` and cross-link both ways - in the same commit.
6. Nothing internal: no hostname, no path outside this repository, no
   credential, no consumer named. See 0009.
