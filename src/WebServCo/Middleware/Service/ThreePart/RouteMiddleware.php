<?php

declare(strict_types=1);

namespace WebServCo\Middleware\Service\ThreePart;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use WebServCo\Middleware\Service\AbstractRouteMiddleware;
use WebServCo\Route\Contract\ThreePart\RoutePartsInterface;

use function array_key_exists;
use function explode;
use function in_array;
use function strpos;

/**
 * Route middleware.
 *
 * Initial development middleware to play around with routing.
 * Uses the 3 part route idea from the WSFW.
 * Adds routing information to request.
 */
final class RouteMiddleware extends AbstractRouteMiddleware implements MiddlewareInterface, RoutePartsInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        // Check base path.
        if (strpos($path, $this->basePath) !== 0) {
            /**
             * Request path does not start with basePath, nothing to do.
             * Pass to the next handler.
             */
            return $handler->handle($request);
        }

        $path = $this->parsePath($path);

        /**
         * Get path parts.
         *
         * Limit to 3 per WSFW convention (class, method, arguments).
         */
        $parts = explode('/', $path, 3);

        // Check if path is in allowed list
        if (!in_array($parts[0], $this->handledPaths, true)) {
            /**
             * Path is not in the list handled by this middleware, nothing to do.
             * Pass to the next handler.
             */
            return $handler->handle($request);
        }

        // All conditions match, go ahead and do our thing.
        // Perform the routing of the request.

        /**
         * @todo: decide route structure. WSFW: 'class'.
         */
        $request = $request->withAttribute(self::ROUTE_PART_1, $this->sanitizeRoutePart($parts[0]));
        /**
         * @todo: decide route structure. WSFW: 'method'.
         */
        if (array_key_exists(1, $parts)) {
            $request = $request->withAttribute(self::ROUTE_PART_2, $this->sanitizeRoutePart($parts[1]));
        }
        /**
         * @todo: decide route structure. WSFW: 'arguments'.
         */
        if (array_key_exists(2, $parts)) {
            $request = $request->withAttribute(self::ROUTE_PART_3, $this->sanitizeRoutePart($parts[2]));
        }

        // Pass to the next handler.
        return $handler->handle($request);
    }
}
