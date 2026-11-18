<?php

declare(strict_types=1);

namespace Tests\Unit\WebServCo\Middleware\Service\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use WebServCo\Middleware\Service\Session\SessionMiddleware;
use WebServCo\Session\Contract\SessionStoreInterface;

/**
 * The session store is a stub: whether a request's cookie resumes a session, and which write starts one, is the
 * store's business (`webservco/session`). Here the store says whether it resumed a session, and the handler starts one
 * or not.
 */
#[CoversClass(SessionMiddleware::class)]
final class SessionMiddlewareTest extends TestCase
{
    /**
     * Headers set on the handler's response.
     *
     * @var array<string,string>
     */
    private array $headers = [];

    private bool $isStarted = false;

    // Whether the session was started when the handler got the request.
    private ?bool $isStartedInHandler = null;

    // The handler's response with the headers; created on first use (`getPrivateResponse()`).
    private ?ResponseInterface $privateResponse = null;

    // The handler's response; created on first use (`getResponse()`).
    private ?ResponseInterface $response = null;

    public function testARequestWithoutASessionGetsNone(): void
    {
        $response = $this->process(false, false);

        self::assertFalse($this->isStartedInHandler);
        self::assertFalse($this->isStarted);
        self::assertSame($this->getResponse(), $response);
        self::assertSame([], $this->headers);
    }

    public function testTheSessionOfTheCookieIsResumedBeforeTheHandler(): void
    {
        $response = $this->process(true, false);

        self::assertTrue($this->isStartedInHandler);
        self::assertSame($this->getPrivateResponse(), $response);
        self::assertSame(['Cache-Control' => 'private, no-cache'], $this->headers);
    }

    public function testASessionStartedByTheHandlerMakesTheResponsePrivate(): void
    {
        $response = $this->process(false, true);

        self::assertFalse($this->isStartedInHandler);
        self::assertSame($this->getPrivateResponse(), $response);
        self::assertSame(['Cache-Control' => 'private, no-cache'], $this->headers);
    }

    /**
     * @param bool $isStartingTheSession Whether the handler stores a value (eg. a form's token).
     */
    private function createHandler(bool $isStartingTheSession): RequestHandlerInterface
    {
        $handler = self::createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(
            function () use ($isStartingTheSession): ResponseInterface {
                $this->isStartedInHandler = $this->isStarted;
                if ($isStartingTheSession) {
                    $this->isStarted = true;
                }

                return $this->getResponse();
            },
        );

        return $handler;
    }

    /**
     * @param bool $isResuming Whether the request sends the cookie of a session.
     */
    private function createSessionStore(ServerRequestInterface $request, bool $isResuming): SessionStoreInterface
    {
        $sessionStore = self::createStub(SessionStoreInterface::class);
        $sessionStore->method('resume')->willReturnCallback(
            function (ServerRequestInterface $resumedRequest) use ($isResuming, $request): bool {
                self::assertSame($request, $resumedRequest);
                $this->isStarted = $isResuming;

                return $isResuming;
            },
        );
        $sessionStore->method('isStarted')->willReturnCallback(
            function (): bool {
                return $this->isStarted;
            },
        );

        return $sessionStore;
    }

    private function getPrivateResponse(): ResponseInterface
    {
        return $this->privateResponse ??= self::createStub(ResponseInterface::class);
    }

    private function getResponse(): ResponseInterface
    {
        if ($this->response === null) {
            $response = self::createStub(ResponseInterface::class);
            $response->method('withHeader')->willReturnCallback(
                function (string $name, mixed $value): ResponseInterface {
                    self::assertIsString($value);
                    $this->headers[$name] = $value;

                    return $this->getPrivateResponse();
                },
            );
            $this->response = $response;
        }

        return $this->response;
    }

    private function process(bool $isResuming, bool $isStartingTheSession): ResponseInterface
    {
        $request = self::createStub(ServerRequestInterface::class);
        $middleware = new SessionMiddleware($this->createSessionStore($request, $isResuming));

        return $middleware->process($request, $this->createHandler($isStartingTheSession));
    }
}
