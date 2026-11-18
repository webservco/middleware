# Changelog

## v2.5.0

### Added

- `Service\Session\SessionMiddleware`: resumes the session of a request that sends its cookie (`webservco/session`), and sends `Cache-Control: private, no-cache` with a response that depends on a session.
- `Service\Security\CsrfMiddleware`: checks the CSRF token (`webservco/session`) of every request that isn't GET, HEAD or OPTIONS, except in the modules a project exempts; a refused request gets the error page of the throwable the project gives.
- Requires `webservco/session` `^2` and `fig/http-message-util` `^1`.

## v2.4.0

### Changed

- **BC break:** `RouteMiddleware` percent-decodes route parts (`rawurldecode()`) and no longer cleans them with `strip_tags()` and `htmlspecialchars()`. Route parts are raw user input: escape them where they are output (`$view->escape()` in templates). Before, `café` arrived as `caf%C3%A9` and `rock&roll` as `rock&amp;roll`, which then showed double-escaped.
- `RouteMiddleware` passes the request on untouched when a route part decodes to a slash (`%2F`), a null byte (`%00`) or invalid UTF-8.
- `AbstractRouteMiddleware::sanitizeRoutePart()` is replaced by `decodeRoutePart()`.
