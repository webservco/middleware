<?php

declare(strict_types=1);

namespace WebServCo\Middleware\Service\Dynamic;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use WebServCo\Http\Contract\Message\Request\Server\ServerRequestAttributeServiceInterface;

use function array_key_exists;

/**
 * Resource middleware that goes along with the dynamic parts route middleware.
 *
 * Processes requests that have already been routed by the Route middleware.
 */
final class ResourceMiddleware implements MiddlewareInterface
{
    /**
     * Constructor.
     *
     * @param array<string,\Psr\Http\Server\RequestHandlerInterface> $handlers list of handlers used by this middleware.
     */
    public function __construct(
        private array $handlers,
        private ServerRequestAttributeServiceInterface $serverRequestAttributeService,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $this->serverRequestAttributeService->getRoutePart(1, $request);

        if ($route === null) {
            // No route defined, nothing to do.
            // Pass to the next handler.
            return $handler->handle($request);
        }

        if (!array_key_exists($route, $this->handlers)) {
            // We do not have a handler for this route, nothing to do.
            // Pass to the next handler.
            return $handler->handle($request);
        }

        // All conditions match, go ahead and do our thing.
        // Pass on to dedicated handler.
        return $this->handlers[$route]->handle($request);
    }
}
