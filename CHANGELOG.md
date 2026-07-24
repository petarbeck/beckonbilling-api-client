# Changelog

All notable changes to this project are documented here. This project adheres
to [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0] - 2026-07-24

Initial release.

### Added
- `Client` with resources for the six v1 entities: customers, article
  categories, articles, quotes, outbound invoices, recurring invoices, plus
  user-token `auth` (register / login / me).
- CRUD (`list`, `autoPaging`, `get`, `create`, `update`, `delete`) and lifecycle
  actions (quote `issue`/`send`/`convert`/`pdf`; invoice
  `issue`/`send`/`cancel`/`setPaid`/`pdf`).
- Typed exception hierarchy keyed on the API's stable `error.key`
  (`AuthenticationException`, `PermissionException`, `NotFoundException`,
  `ConflictException`, `ValidationException`, `RateLimitException`,
  `ServerException`, `TransportException`).
- `Collection` pagination wrapper and lazy `autoPaging()`.
- PSR-18 / PSR-17 transport with auto-discovery via `php-http/discovery`.
- Canonical `openapi.yaml` contract, `README.md`, and `AGENTS.md` agent guide.

[Unreleased]: https://github.com/petarbeck/beckonbilling-api-client/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/petarbeck/beckonbilling-api-client/releases/tag/v0.1.0
