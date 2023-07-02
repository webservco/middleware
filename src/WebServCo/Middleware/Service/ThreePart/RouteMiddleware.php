<?php

declare(strict_types=1);

namespace WebServCo\Middleware\Service\ThreePart;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use WebServCo\Route\Contract\ThreePart\RoutePartsInterface;

use function array_key_exists;
use function explode;
use function htmlspecialchars;
use function in_array;
use function strip_tags;
use function strlen;
use function strpos;
use function substr;
use function trim;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Route middleware.
 *
 * Initial development middleware to play around with routing.
 * Uses the 3 part route idea from the WSFW.
 * Adds routing information to request.
 */
final class RouteMiddleware implements MiddlewareInterface, RoutePartsInterface
{
    /**
     * Constructor.
     *
     * $basePath is the root of the application.
     * Examples:
     * - '/' if app is not in subfolder, and we should handle all routing.
     * - '/api' if we should handle only requests that start with '/api'
     *
     * @param array<int,string> $handledPaths a list of request paths handled by this middleware
     */
    public function __construct(private string $basePath, private ?string $defaultPath, private array $handledPaths)
    {
    }

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

        $path = $this->processPath($path);

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

    private function processPath(string $path): string
    {
        // Remove basePath
        $path = substr($path, strlen($this->basePath));
        $path = trim($path, '/');

        // Handle "home page" requests.
        if ($path === '' && $this->defaultPath !== null) {
            return $this->defaultPath;
        }

        return $path;
    }

    /**
     * Sanitize.
     *
     * @todo improve sanitize route part.
     */
    private function sanitizeRoutePart(string $input): string
    {
        $result = strip_tags($input);

        return htmlspecialchars($result, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
