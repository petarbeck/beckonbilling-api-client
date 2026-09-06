# 0009 — Coordination notes stay out of this repository

**Status:** Accepted

**Date:** 2026-09-06

## Context

This repository is public. It is what an external consumer reads to decide
whether to depend on the package, and what an AI agent reads to write code
against the API. Everything in it — `README.md`, `AGENTS.md`, `llms.txt`,
`CHANGELOG.md`, `openapi.yaml` — is addressed to a reader who has no other
source, and none of it is addressed to the people who build the API.

The projects that build it do keep a shared record of what is open between
components, and the non-public repositories around this one each carry a
section pointing at it. This repository deliberately does not, and the
question came up explicitly when the rest of them gained theirs. Internal
hostnames, internal file paths, the layout of sibling repositories, the
names of individual consumers and the state of unreleased work are not
things a published package should carry, and a consumer gains nothing from
them.

## Decision

Every published file in this repository describes the API and this package,
and nothing else.

- No file names a sibling repository, an internal path, an internal
  hostname, or an individual consumer of the API. An announcement is
  version-shaped and addressed to everyone
  ([0004](0004-changelog-is-the-announcement-channel.md)).
- `AGENTS.md` has no "neighbours" section, by decision rather than by
  oversight — this ADR is where that decision is recorded, so it is not
  reopened as a gap.
- Where the reason for a contract statement lies in server code, the text
  names the **symbol** and never a path — as `CHANGELOG.md` already does with
  `RecurringInvoiceEndpoint::apply()`. Evidence quoted in these ADRs comes
  from files in this repository.
- ADRs here may cite a cross-component record by its bare id in the form
  `vorgang <date>-<slug>`, so that a maintainer can follow it. They never
  give a path to it and never reproduce its content.
- No credential, token, key or account detail appears anywhere, including in
  an example. Sample tokens are placeholders (`bbp_...`).

## Consequences

- A reader of this repository sometimes cannot see **why** a decision was
  taken, only what it is and what evidence in this repository supports it.
  That is accepted: an ADR states the behaviour and its public evidence
  rather than the internal chain that produced it.
- The `vorgang <id>` references are opaque to an outside reader. They are
  kept anyway, because without them a maintainer has no way back from a
  decision to the discussion that settled it, and the alternative — a path
  or a quotation — would publish the thing this ADR excludes.
- The cost falls on the people coordinating: they cannot use this repository
  as a message channel, and anything they need to say to each other has to
  live where the other repositories can see it.
- Anything genuinely useful to a consumer is not "internal" merely because
  it came up internally. A known gap, a deprecation window, a silent-discard
  behaviour: all of those belong here, in the contract, stated plainly
  (0004, [0008](0008-document-uuids-replace-document-ids.md)).

## References

`AGENTS.md` (intro, "What this package is"); `README.md`; `CHANGELOG.md`
(`[Unreleased]`); `openapi.yaml` (`info.description`); commit `0148b9c`
(server evidence cited by symbol, without a path).
