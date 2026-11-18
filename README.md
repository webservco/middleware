# webservco/middleware

A PHP component/library. Please see [webservco/sandbox](https://webservco.github.io/sandbox/) for an example project using these types of components.

PSR-15 middleware for routing, dispatching to module handlers, exception handling, request logging, choosing the response format, the session and the CSRF check.

- Implements: [PSR-15: HTTP Server Request Handlers](https://www.php-fig.org/psr/psr-15/) (middleware)
- Related: `webservco/http-request-handler` (`StackHandler` runs the middleware), `webservco/exception` (exception handler), `webservco/view` (renderers), `webservco/session` (the session store and the CSRF token)

---

## Installation

```shell
composer require webservco/middleware
```

---

## Usage

The order matters. This is the order of the `webservco/sandbox` project's `RequestHandlerFactory`, without its language middleware (`webservco/i18n`) and its own two authentication middleware (web and API).

```php
use WebServCo\Http\Service\Message\Request\RequestHandler\Exception\ExceptionRequestHandler;
use WebServCo\Middleware\Service\Dynamic\ResourceMiddleware;
use WebServCo\Middleware\Service\Dynamic\RouteMiddleware;
use WebServCo\Middleware\Service\Exception\ExceptionHandlerMiddleware;
use WebServCo\Middleware\Service\Log\LoggerMiddleware;
use WebServCo\Middleware\Service\ViewRenderer\ViewRendererSettingMiddleware;
use WebServCo\Route\Service\ControllerView\RouteConfiguration;

// Renders ErrorController.
$errorRequestHandler = new ExceptionRequestHandler(/* ... */ new RouteConfiguration(ErrorController::class));
// Log the exception, then render the error page.
$exceptionHandlerMiddleware = new ExceptionHandlerMiddleware(
    // \WebServCo\Exception\Contract\ExceptionHandlerInterface
    $exceptionHandler,
    $errorRequestHandler,
);

$stackHandler->addMiddleware($exceptionHandlerMiddleware);
// Picks HTML, JSON or JSON:API from the Accept header.
$stackHandler->addMiddleware(new ViewRendererSettingMiddleware($serverHeadersAcceptService));
// Base path "/", default route "example/test", modules handled.
$stackHandler->addMiddleware(new RouteMiddleware('/', 'example/test', ['api', 'example']));
// Resumes the session; see "Session and CSRF check".
$stackHandler->addMiddleware($sessionMiddleware);
$stackHandler->addMiddleware(new LoggerMiddleware($logger));
// Second instance, so it also sees everything set up above.
$stackHandler->addMiddleware($exceptionHandlerMiddleware);
// Checks the CSRF token; see "Session and CSRF check".
$stackHandler->addMiddleware($csrfMiddleware);
// Sends /api/... and /example/... to the module request handlers.
$stackHandler->addMiddleware(new ResourceMiddleware(
    ['api' => $apiRequestHandler, 'example' => $exampleRequestHandler],
    $serverRequestAttributeService,
));
```

### Session and CSRF check

The session store and the CSRF token are `webservco/session`'s (its README: "Session store", "CSRF token", "Wiring"). A project gives the store its directory of the session files, and the CSRF middleware its error page and the modules it doesn't check.

```php
use WebServCo\Middleware\Service\Security\CsrfMiddleware;
use WebServCo\Middleware\Service\Session\SessionMiddleware;
use WebServCo\Session\Service\CsrfTokenService;

// \WebServCo\Session\Contract\SessionStoreInterface
$sessionMiddleware = new SessionMiddleware($sessionStore);

$csrfMiddleware = new CsrfMiddleware(
    new CsrfTokenService($sessionStore),
    // Renders the error page of the `throwable` request attribute: the one of ExceptionHandlerMiddleware.
    $errorRequestHandler,
    // The error of a refused request. Never thrown: the error page shows its code and message.
    // For example the project's own exception, which its error controller shows as 403, "This page has expired".
    $pageExpiredException,
    // Route part 1 values not checked: a module that doesn't use the session cookie (an API with its own tokens).
    ['api'],
    // \Psr\Log\LoggerInterface
    $logger,
    // \WebServCo\Http\Contract\Message\Request\Server\ServerRequestAttributeServiceInterface
    $serverRequestAttributeService,
);
```

`SessionMiddleware` resumes the session of a request that sends the session cookie (`SessionStoreInterface::resume()`), before the next handlers. A response to a request with a session, started before or during it, gets `Cache-Control: private, no-cache`.

`CsrfMiddleware` checks every request that isn't GET, HEAD or OPTIONS, outside the exempt modules:

- The token comes in the header `X-CSRF-Token` (`CsrfTokenServiceInterface::HEADER_NAME`, for requests sent with JavaScript), or else in the form field `csrf_token` (`FIELD_NAME`). A header, when sent, decides alone. A field that isn't text is refused.
- Without a session there is no token, so such a request always fails.
- A refused request is logged as a warning (`CSRF token missing or not valid.`, with the method and the path, never the token) and gets the error request handler's response for the given throwable. The next handlers don't run.
- A form left open until its session ended looks the same as a forged one: the error page should tell the user the page expired.

Their place in the stack:

- `SessionMiddleware`: after the first exception handler, so that a session that can't start gets the error page; before the second, so that the error pages it renders get the header too.
- `CsrfMiddleware`: after the route middleware (it reads route part 1) and the session middleware (it needs the resumed session); after the second exception handler, right before the resource middleware, so that the request log has the refused requests and an exception in it gets the error page with the session's header.

---

## Contracts

| Interface | Implementation | Purpose |
| --- | --- | --- |
| `\Psr\Http\Server\MiddlewareInterface` | `Service\Dynamic\RouteMiddleware` | Splits the path into percent-decoded `routePart.1`, `routePart.2`, ... request attributes. |
| | `Service\Dynamic\ResourceMiddleware` | Passes the request to the handler registered for route part 1. |
| | `Service\Exception\ExceptionHandlerMiddleware` | Catches exceptions from the next handlers, logs them and returns an error response. |
| | `Service\ViewRenderer\ViewRendererSettingMiddleware` | Sets the `ViewRendererInterface` request attribute from the `Accept` header. |
| | `Service\Log\LoggerMiddleware` | Logs method, URI and attributes at info level. |
| | `Service\Session\SessionMiddleware` | Resumes the session of a request that sends its cookie; `Cache-Control: private, no-cache` on a response that depends on a session. |
| | `Service\Security\CsrfMiddleware` | Checks the CSRF token of every request that isn't GET, HEAD or OPTIONS; a refused one gets the project's error page. |

Namespaces are relative to `WebServCo\Middleware\`.

---

## Notes

`RouteMiddleware` and `ResourceMiddleware` pass the request on untouched when the path isn't one of theirs. An unknown first path part travels down the whole stack, reaches the fallback handler and gets a 404.

Route parts are percent-decoded (`rawurldecode()`) and otherwise left as they are: they are raw user input. Escape them where they are output (`$view->escape()` in templates) and validate them before using them in file paths, queries and so on. A path with a part that decodes to a slash (`%2F`), a null byte (`%00`) or invalid UTF-8 is passed on untouched, like an unknown path.

The error controller gets the exception in the `throwable` request attribute: from `ExceptionHandlerMiddleware`, or the throwable a project gives `CsrfMiddleware`.

A request with no `Accept` header is treated as `*/*`. If the header lists only types that no renderer supports, `ViewRendererSettingMiddleware` throws.

---

## Development

```shell
# Start project.
ddev start
# Install dependencies.
ddev c u
# Run all validations.
ddev c c
# Run unit tests, with coverage (needs Xdebug, which DDEV leaves off).
ddev xdebug on && ddev c test:dox; ddev xdebug off
```

---

## License

[MIT](LICENSE.md)
