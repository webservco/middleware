<?php

declare(strict_types=1);

namespace WebServCo\Middleware\Service\Log;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * A logging middleware.
 *
 * Log request data.
 */
final class LoggerMiddleware implements MiddlewareInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    /**
     * Process.
     *
     * json_encode: Despite using JSON_THROW_ON_ERROR flag, Phan 5.4.1 throws PhanPossiblyFalseTypeArgument.
     * If adding is_string check, PHPStan and Psalm instead throw error.
     * Test: @see `Tests\Misc\Phan\PhanPossiblyFalseTypeArgumentTest`
     *
     * @suppress PhanPossiblyFalseTypeArgument
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $logData = [
            'attributes' => $request->getAttributes(),
            'method' => $request->getMethod(),
            'uri' => $request->getUri()->__toString(),
        ];

        $this->logger->info(json_encode($logData, JSON_THROW_ON_ERROR));

        // Pass to the next handler.
        return $handler->handle($request);
    }
}
