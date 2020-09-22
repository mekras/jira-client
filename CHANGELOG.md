# Changelog

## Unreleased


## 1.4.0 - 22.09.2020

### Changed

- Add some logging to `ClientRaw` to debug cache usage.


## 1.3.1 - 20.09.2020

### Fixed

- Fixed "Cache key must be string, "null" given." error in `ClientRaw`.


## 1.3.0 - 18.09.2020

**Renamed to `mekras/jira-client`.**

### Fixed

- Jira client not passed to called methods in:
  - `Issue::getParentIssue()`; 
  - `Issue::getSubIssues()`. 

### Added

- Added support for setting [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client in
  `ClientRaw`.
- Added support for [PSR-16](https://www.php-fig.org/psr/psr-16/) simple cache in `ClientRaw`.
- Added method `User::getAvatarUrl()`.


## 1.2.0 - 27.03.2020

Unreleased changes from original project.


## [1.1.0](https://github.com/badoo/jira-client/compare/v1.0.0...v1.1.0) (2020-02-04)

### Dependencies

* allow symfony/yaml v5.0 as a dependency ([#11](https://github.com/badoo/jira-client/issues/11))

### Features

* add custom label field ([#4](https://github.com/badoo/jira-client/issues/4)) ([beaa668](https://github.com/badoo/jira-client/commit/beaa6687aabe2e3b14c836d63d3bc4119af44cbe))
* add getAll method for project section ([#8](https://github.com/badoo/jira-client/issues/8)) ([d689325](https://github.com/badoo/jira-client/commit/d68932571e133b6115fd2c99e7a6f8ade525a885))
* add getLatestVersion() for project section ([9dde112](https://github.com/badoo/jira-client/commit/9dde112fb3d038b5ef8eb78eb0649d2ab684dc36))
* add project statuses list ([#6](https://github.com/badoo/jira-client/issues/6)) ([ddfd595](https://github.com/badoo/jira-client/commit/ddfd5952fb14bd1b7aaac4590f7395449956055f))


### Bug Fixes

* Client::instance() will return it`s successor ([#5](https://github.com/badoo/jira-client/issues/5)) ([09079da](https://github.com/badoo/jira-client/commit/09079dafc70d115d1bf4607c5646bef52847788f))
* issue field meta contains flag 'custom' ([#7](https://github.com/badoo/jira-client/issues/7)) ([8edbff7](https://github.com/badoo/jira-client/commit/8edbff7e6ebe9c4dbe51b9593ec76f0e537358bf))

## 1.0.0 (2019-11-01)
