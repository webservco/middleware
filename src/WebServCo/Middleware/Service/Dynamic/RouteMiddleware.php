<?php

declare(strict_types=1);

namespace WebServCo\Middleware\Service\Dynamic;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use WebServCo\Middleware\Service\AbstractRouteMiddleware;

use function explode;
use function in_array;
use function sprintf;
use function strpos;

/**
 * Route middleware.
 *
 * Dynamic, ie. route can have any number of parts, not just 3 like the ThreePart system.
 * Adds routing information to request.
 */
final class RouteMiddleware extends AbstractRouteMiddleware implements MiddlewareInterface
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
         * Dynamic so do not use a limit
         */
        $parts = explode('/', $path);

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

        foreach ($parts as $index => $part) {
            $request = $request->withAttribute(
                sprintf(self::ROUTE_PART_TEMPLATE, $index + 1),
                $this->sanitizeRoutePart($part),
            );
        }

        // Pass to the next handler.
        return $handler->handle($request);
    }
}
