<?php

declare(strict_types=1);

namespace WebServCo\Middleware\Service\Exception;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use WebServCo\Exception\Contract\ExceptionHandlerInterface;

final class ExceptionHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(
        // Exception handler to use to handle the exception.
        private ExceptionHandlerInterface $exceptionHandler,
        // Request handler to use to return a response to the client
        private RequestHandlerInterface $requestHandler,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            /**
             * Pass to the next handler.
             * If all is OK, nothing else to do.
             */
            return $handler->handle($request);
        } catch (Throwable $throwable) {
            /**
             * An exception happened inside one of the next handlers.
             */

            // Handle error (log, report, etc)
            $this->exceptionHandler->handle($throwable);

            /**
             * Return a response via the request handler.
             * Any exceptions that happen here will bubble up and be handled by the uncaught exception handler (if set).
             */
            return $this->requestHandler->handle($request->withAttribute('throwable', $throwable));
        }
    }
}
