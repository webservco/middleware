<?php

declare(strict_types=1);

namespace WebServCo\Middleware\Service;

use function htmlspecialchars;
use function strip_tags;
use function strlen;
use function substr;
use function trim;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

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
     * Sanitize.
     *
     * @todo improve sanitize route part.
     */
    protected function sanitizeRoutePart(string $input): string
    {
        $result = strip_tags($input);

        return htmlspecialchars($result, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
