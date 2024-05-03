<?php

declare(strict_types=1);

namespace WebServCo\Middleware\Service\Dynamic;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use UnexpectedValueException;
use WebServCo\Route\Contract\Dynamic\RoutePartsInterface;

use function array_key_exists;
use function is_string;
use function sprintf;

/**
 * Resource middleware that goes along with the dynamic parts route middleware.
 *
 * Processes requests that have already been routed by the Route middleware.
 */
final class ResourceMiddleware implements MiddlewareInterface, RoutePartsInterface
{
    /**
     * Constructor.
     *
     * @param array<string,\Psr\Http\Server\RequestHandlerInterface> $handlers list of handlers used by this middleware.
     */
    public function __construct(private array $handlers)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $this->getRoutePart(1, $request);

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

    private function getRoutePart(int $index, ServerRequestInterface $request): ?string
    {
        $result = $request->getAttribute(sprintf(self::ROUTE_PART_TEMPLATE, $index), null);

        if ($result === null) {
            return null;
        }

        if (!is_string($result)) {
            // Sanity check, should never happen.
            throw new UnexpectedValueException('Route is not a string.');
        }

        return $result;
    }
}
