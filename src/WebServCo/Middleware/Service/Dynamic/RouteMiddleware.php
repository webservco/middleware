<?php

declare(strict_types=1);

namespace WebServCo\Middleware\Service\Dynamic;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use WebServCo\Http\Contract\Message\Request\Server\ServerRequestAttributeServiceInterface;
use WebServCo\Middleware\Service\AbstractRouteMiddleware;

use function array_map;
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
    #[Override]
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
         * Dynamic so do not use a limit.
         * Split before decoding, so an encoded slash (`%2F`) can not add a part.
         */
        $parts = array_map($this->decodeRoutePart(...), explode('/', $path));

        if (in_array(null, $parts, true)) {
            /**
             * A part does not decode to a valid route part, nothing to do.
             * Pass to the next handler.
             */
            return $handler->handle($request);
        }

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
                sprintf(ServerRequestAttributeServiceInterface::ROUTE_PART_TEMPLATE, $index + 1),
                $part,
            );
        }

        // Pass to the next handler.
        return $handler->handle($request);
    }
}
