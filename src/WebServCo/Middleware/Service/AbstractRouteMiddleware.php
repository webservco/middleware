<?php

declare(strict_types=1);

namespace WebServCo\Middleware\Service;

use function preg_match;
use function rawurldecode;
use function str_contains;
use function strlen;
use function substr;
use function trim;

abstract class AbstractRouteMiddleware
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
    public function __construct(
        protected string $basePath,
        protected ?string $defaultPath,
        protected array $handledPaths,
    ) {
    }

    protected function parsePath(string $path): string
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
     * Percent-decode a single route part (path segment).
     *
     * `rawurldecode()`, not `urldecode()`: in a path "+" is a plus, not a space.
     *
     * Returns null when the decoded value can not be a route part:
     * it contains a slash (`%2F`) or a null byte (`%00`), or it is not valid UTF-8.
     *
     * No HTML escaping: the result is raw user input, escape it where it is output.
     */
    protected function decodeRoutePart(string $input): ?string
    {
        $result = rawurldecode($input);

        if (str_contains($result, '/') || str_contains($result, "\0") || preg_match('//u', $result) !== 1) {
            return null;
        }

        return $result;
    }
}
