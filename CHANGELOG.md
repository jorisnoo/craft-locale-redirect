# Changelog

All notable changes to this project will be documented in this file.

## [1.1.0](https://github.com/jorisnoo/craft-locale-redirect/releases/tag/v1.1.0) (2026-07-16)

### Features

- add crossHostRedirects option for host-aware locale redirects ([b5127ca](https://github.com/jorisnoo/craft-locale-redirect/commit/b5127ca12eb0549d78c35596d49b9a534b55771c))

### Bug Fixes

- preserve request host when redirecting to locale paths ([d828309](https://github.com/jorisnoo/craft-locale-redirect/commit/d828309f20744c177a1f5e53b6d22ba8d81786f0))
## [1.0.4](https://github.com/jorisnoo/craft-locale-redirect/releases/tag/v1.0.4) (2026-07-08)

### Bug Fixes

- decode percent-encoded URLs in redirect loop check ([937a8f6](https://github.com/jorisnoo/craft-locale-redirect/commit/937a8f6c2a1afc435f24b4bb15cb8bce7307d7fd))
## [1.0.3](https://github.com/jorisnoo/craft-locale-redirect/releases/tag/v1.0.3) (2026-07-08)

### Bug Fixes

- strip query string when checking for redirect loops ([0d7aece](https://github.com/jorisnoo/craft-locale-redirect/commit/0d7aece16b710b3ed2aa822cf312cc6e7b79cb88))

### Chores

- add justfile ([5df0204](https://github.com/jorisnoo/craft-locale-redirect/commit/5df0204507b10f6bcab83ebea514a67c7997bd56))
- **deps:** bump actions/checkout from 6 to 7 ([5b07905](https://github.com/jorisnoo/craft-locale-redirect/commit/5b07905886252f743e3328808fb7ed114e823248))
## [1.0.2](https://github.com/jorisnoo/craft-locale-redirect/releases/tag/v1.0.2) (2026-05-13)

### Bug Fixes

- normalize locale paths to lowercase ([c0d8fbc](https://github.com/jorisnoo/craft-locale-redirect/commit/c0d8fbc055e8598c9270e3fd754da271d670d135))
- use raw path info for locale matching ([3aa75fc](https://github.com/jorisnoo/craft-locale-redirect/commit/3aa75fcd7ff26c3654423c0dae344c041ef00734))

### Code Refactoring

- extract redirect resolution logic into testable RedirectResolver class ([99775c8](https://github.com/jorisnoo/craft-locale-redirect/commit/99775c8268c3480eebf9100a9b7272dda21d5de7))
## [1.0.1](https://github.com/jorisnoo/craft-locale-redirect/releases/tag/v1.0.1) (2026-05-13)

### Code Refactoring

- improve locale redirect with path-aware handling ([de455b3](https://github.com/jorisnoo/craft-locale-redirect/commit/de455b3c5e613bf1586e2db620b29d946953ddcf))
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
