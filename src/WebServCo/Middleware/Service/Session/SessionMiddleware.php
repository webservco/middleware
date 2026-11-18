<?php

declare(strict_types=1);

namespace WebServCo\Middleware\Service\Session;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use WebServCo\Session\Contract\SessionStoreInterface;

/**
 * Session: resumed when the request sends the session cookie. Later code starts it when it needs one
 * (`SessionStoreInterface`).
 *
 * A response to a request with a session, started before or during it, depends on that session: it gets
 * `Cache-Control: private, no-cache`, error and 404 pages included. PHP sends no cache headers of its own
 * (`webservco/session`).
 */
final class SessionMiddleware implements MiddlewareInterface
{
    public const string CACHE_CONTROL = 'private, no-cache';

    public function __construct(private SessionStoreInterface $sessionStore)
    {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->sessionStore->resume($request);

        $response = $handler->handle($request);

        if (!$this->sessionStore->isStarted()) {
            return $response;
        }

        return $response->withHeader('Cache-Control', self::CACHE_CONTROL);
    }
}
