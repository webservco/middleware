<?php

declare(strict_types=1);

namespace WebServCo\Middleware\Service\ThreePart;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use UnexpectedValueException;
use WebServCo\Route\Contract\ThreePart\RoutePartsInterface;

use function array_key_exists;
use function is_string;

/**
 * Resource middleware that goes along with the three part route middleware.
 *
 * Processes requests that have already been routed by the Route midleware.
 */
final class ResourceMiddleware implements MiddlewareInterface, RoutePartsInterface
{
    /**
     * Constructor.
     *
     * @param array<string,\Psr\Http\Server\RequestHandlerInterface> $handlers list of hadlers used by this middleware.
     */
    public function __construct(private array $handlers)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $this->getRoutePart1($request);

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

    private function getRoutePart1(ServerRequestInterface $request): ?string
    {
        $result = $request->getAttribute(self::ROUTE_PART_1, null);

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
