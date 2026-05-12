# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0](https://github.com/jorisnoo/craft-locale-redirect/releases/tag/v1.0.0) (2026-05-12)

### ⚠ BREAKING CHANGES

- drop Craft CMS 4 support and add BrowserLocaleMatcher tests ([b95106d](https://github.com/jorisnoo/craft-locale-redirect/commit/b95106d7130b3fe08205b92bb41bf02b661c1650))

### Features

- preserve query parameters and prevent redirect loops ([b964029](https://github.com/jorisnoo/craft-locale-redirect/commit/b9640292aba914be86e8425b9d759377feb9aab2))
- set module alias and add dependabot configuration ([38be599](https://github.com/jorisnoo/craft-locale-redirect/commit/38be599c2e7e764b62437900133b1e4773e6b25c))
- initialize craft-locale-redirect plugin ([001d558](https://github.com/jorisnoo/craft-locale-redirect/commit/001d558b48bd18e165b95e3748e058a4819c17ad))

### Bug Fixes

- add cache control and vary headers to locale redirect response ([d01c289](https://github.com/jorisnoo/craft-locale-redirect/commit/d01c2898477a3773317f0f86636f5e0af298ddc3))

### Code Refactoring

- extract locale filtering logic to LocaleFilter class ([2582899](https://github.com/jorisnoo/craft-locale-redirect/commit/25828995687925c5bd21fd1983d35f91a78036c5))
- remove bot detection from locale redirect module ([422c678](https://github.com/jorisnoo/craft-locale-redirect/commit/422c678c20231d56fa95abade6a9a6f583ce6dcf))

### Documentation

- add README ([dccc457](https://github.com/jorisnoo/craft-locale-redirect/commit/dccc457cc64bf4b485823bdbb24686571f0984ec))

### Continuous Integration

- upgrade actions/checkout to v6 and add phpunit.xml.dist ([c2a44f7](https://github.com/jorisnoo/craft-locale-redirect/commit/c2a44f7d49dada381fda7229be1e24574ec2b150))
- replace ci workflow with dependabot auto-merge ([c16f0e9](https://github.com/jorisnoo/craft-locale-redirect/commit/c16f0e91a73d9b4601ed3127b4f61aa0611bb86f))
- add GitHub Actions workflow ([fc1f259](https://github.com/jorisnoo/craft-locale-redirect/commit/fc1f259303ac22173d4285d30a35755ff0325c67))

### Chores

- **deps:** bump actions/checkout from 4 to 6 ([2de946e](https://github.com/jorisnoo/craft-locale-redirect/commit/2de946ea7e0b40732a62cb478e5a173240fd13f8))
- drop Craft CMS 4 support and add BrowserLocaleMatcher tests ([b95106d](https://github.com/jorisnoo/craft-locale-redirect/commit/b95106d7130b3fe08205b92bb41bf02b661c1650))
