# Changelog

All notable changes to Outstand Term Selector will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2026-10-09

### Fixed

- Hide the parent dropdown in the add-new term form for opted-in taxonomies,
  since flat taxonomies cannot store a parent and the REST API rejected new
  terms that had one.

## [1.0.0] - 2026-08-02

### Added

- Replace the free-text tag input with the core hierarchical (checkbox tree)
  term selector for opted-in non-hierarchical taxonomies.
- Opt in at registration with the `use_hierarchical_selector` argument to
  `register_taxonomy()`.
- Opt in or out with the `outstand_term_selector_taxonomies` filter, for
  taxonomies registered by third-party code.

[Unreleased]: https://github.com/pixelalbatross/outstand-term-selector/compare/1.0.1...HEAD
[1.0.1]: https://github.com/pixelalbatross/outstand-term-selector/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/pixelalbatross/outstand-term-selector/releases/tag/1.0.0
