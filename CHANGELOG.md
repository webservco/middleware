# Changelog

## Unreleased

### Changed

- **BC break:** `RouteMiddleware` percent-decodes route parts (`rawurldecode()`) and no longer cleans them with `strip_tags()` and `htmlspecialchars()`. Route parts are raw user input: escape them where they are output (`$view->escape()` in templates). Before, `café` arrived as `caf%C3%A9` and `rock&roll` as `rock&amp;roll`, which then showed double-escaped.
- `RouteMiddleware` passes the request on untouched when a route part decodes to a slash (`%2F`), a null byte (`%00`) or invalid UTF-8.
- `AbstractRouteMiddleware::sanitizeRoutePart()` is replaced by `decodeRoutePart()`.
