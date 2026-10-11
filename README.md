# webservco/middleware

A PHP component/library. Please see [webservco/sandbox](https://webservco.github.io/sandbox/) for an example project using these types of components.

PSR-15 middleware for routing, dispatching to module handlers, exception handling, request logging and choosing the response format.

- Implements: [PSR-15: HTTP Server Request Handlers](https://www.php-fig.org/psr/psr-15/) (middleware)
- Related: `webservco/http-request-handler` (`StackHandler` runs the middleware), `webservco/exception` (exception handler), `webservco/view` (renderers)

---

## Installation

```shell
composer require webservco/middleware
```

---

## Usage

The order matters. This is the order the `webservco/sandbox` project uses in `RequestHandlerFactory`, minus its own authentication middleware.

```php
use WebServCo\Http\Service\Message\Request\RequestHandler\Exception\ExceptionRequestHandler;
use WebServCo\Middleware\Service\Dynamic\ResourceMiddleware;
use WebServCo\Middleware\Service\Dynamic\RouteMiddleware;
use WebServCo\Middleware\Service\Exception\ExceptionHandlerMiddleware;
use WebServCo\Middleware\Service\Log\LoggerMiddleware;
use WebServCo\Middleware\Service\ViewRenderer\ViewRendererSettingMiddleware;
use WebServCo\Route\Service\ControllerView\RouteConfiguration;

// Log the exception, then render ErrorController.
$exceptionHandlerMiddleware = new ExceptionHandlerMiddleware(
    // \WebServCo\Exception\Contract\ExceptionHandlerInterface
    $exceptionHandler,
    new ExceptionRequestHandler(/* ... */ new RouteConfiguration(ErrorController::class)),
);

$stackHandler->addMiddleware($exceptionHandlerMiddleware);
// Picks HTML, JSON or JSON:API from the Accept header.
$stackHandler->addMiddleware(new ViewRendererSettingMiddleware($serverHeadersAcceptService));
// Base path "/", default route "example/test", modules handled.
$stackHandler->addMiddleware(new RouteMiddleware('/', 'example/test', ['api', 'example']));
$stackHandler->addMiddleware(new LoggerMiddleware($logger));
// Second instance, so it also sees everything set up above.
$stackHandler->addMiddleware($exceptionHandlerMiddleware);
// Sends /api/... and /example/... to the module request handlers.
$stackHandler->addMiddleware(new ResourceMiddleware(
    ['api' => $apiRequestHandler, 'example' => $exampleRequestHandler],
    $serverRequestAttributeService,
));
```

---

## Contracts

| Interface | Implementation | Purpose |
| --- | --- | --- |
| `\Psr\Http\Server\MiddlewareInterface` | `Service\Dynamic\RouteMiddleware` | Splits the path into `routePart.1`, `routePart.2`, ... request attributes. |
| | `Service\Dynamic\ResourceMiddleware` | Passes the request to the handler registered for route part 1. |
| | `Service\Exception\ExceptionHandlerMiddleware` | Catches exceptions from the next handlers, logs them and returns an error response. |
| | `Service\ViewRenderer\ViewRendererSettingMiddleware` | Sets the `ViewRendererInterface` request attribute from the `Accept` header. |
| | `Service\Log\LoggerMiddleware` | Logs method, URI and attributes at info level. |

Namespaces are relative to `WebServCo\Middleware\`.

---

## Notes

`RouteMiddleware` and `ResourceMiddleware` pass the request on untouched when the path isn't one of theirs. An unknown first path part travels down the whole stack, reaches the fallback handler and gets a 404. Route parts are cleaned with `strip_tags()` and `htmlspecialchars()`.

The error controller gets the exception in the `throwable` request attribute.

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
# Run unit tests.
ddev c test:dox
```

---

## License

[MIT](LICENSE.md)
