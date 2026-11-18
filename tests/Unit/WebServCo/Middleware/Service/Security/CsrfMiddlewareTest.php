<?php

declare(strict_types=1);

namespace Tests\Unit\WebServCo\Middleware\Service\Security;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use WebServCo\Http\Service\Message\Request\Server\ServerRequestAttributeService;
use WebServCo\Middleware\Service\Security\CsrfMiddleware;
use WebServCo\Session\Contract\CsrfTokenServiceInterface;

/**
 * Responses: the next handler's when the request passes, the error request handler's when it is refused.
 *
 * The token service is a stub that knows one token: how it checks a token, and that there is none without a session,
 * is its business (`webservco/session`).
 */
#[CoversClass(CsrfMiddleware::class)]
final class CsrfMiddlewareTest extends TestCase
{
    private const string EXEMPT_MODULE = 'api';

    private const string MODULE = 'example';

    private const string TOKEN = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    /**
     * Tokens the token service was asked to check.
     *
     * @var list<string>
     */
    private array $checkedTokens = [];

    // The `throwable` attribute the error request handler got.
    private ?Throwable $errorPageThrowable = null;

    // Created on first use (`getErrorResponse()`).
    private ?ResponseInterface $errorResponse = null;

    // The project's error of a refused request; created on first use (`getErrorThrowable()`).
    private ?Throwable $errorThrowable = null;

    /**
     * Logger calls, by level: message and context.
     *
     * @phpcs:ignore SlevomatCodingStandard.TypeHints.DisallowMixedTypeHint.DisallowedMixedTypeHint
     * @var array<string,list<array{0:string,1:array<mixed>}>>
     */
    private array $logEntries = [];

    // Created on first use (`getNextResponse()`).
    private ?ResponseInterface $nextResponse = null;

    #[DataProvider('safeMethodProvider')]
    public function testASafeMethodNeedsNoToken(string $method): void
    {
        self::assertSame($this->getNextResponse(), $this->process($method, self::MODULE, '', null));
        self::assertSame([], $this->checkedTokens);
    }

    #[DataProvider('unsafeMethodProvider')]
    public function testAnUnsafeMethodWithoutATokenGetsTheErrorPage(string $method): void
    {
        self::assertSame($this->getErrorResponse(), $this->process($method, self::MODULE, '', null));
        self::assertSame($this->getErrorThrowable(), $this->errorPageThrowable);
        self::assertSame(
            ['warning' => [['CSRF token missing or not valid.', ['method' => $method, 'path' => '/path']]]],
            $this->logEntries,
        );
    }

    public function testTheTokenInTheFormFieldPasses(): void
    {
        self::assertSame(
            $this->getNextResponse(),
            $this->process('POST', self::MODULE, '', [CsrfTokenServiceInterface::FIELD_NAME => self::TOKEN]),
        );
        self::assertSame([self::TOKEN], $this->checkedTokens);
        self::assertSame([], $this->logEntries);
    }

    public function testTheTokenInTheHeaderPasses(): void
    {
        self::assertSame($this->getNextResponse(), $this->process('POST', self::MODULE, self::TOKEN, null));
    }

    public function testAWrongTokenGetsTheErrorPageAndStaysOutOfTheLog(): void
    {
        $wrongToken = self::TOKEN . '0';

        self::assertSame(
            $this->getErrorResponse(),
            $this->process('POST', self::MODULE, '', [CsrfTokenServiceInterface::FIELD_NAME => $wrongToken]),
        );
        self::assertSame($this->getErrorThrowable(), $this->errorPageThrowable);
        self::assertSame(
            ['warning' => [['CSRF token missing or not valid.', ['method' => 'POST', 'path' => '/path']]]],
            $this->logEntries,
        );
    }

    public function testTheHeaderDecidesWhenItIsSent(): void
    {
        self::assertSame(
            $this->getErrorResponse(),
            $this->process('POST', self::MODULE, 'wrong', [CsrfTokenServiceInterface::FIELD_NAME => self::TOKEN]),
        );
        self::assertSame(['wrong'], $this->checkedTokens);
    }

    public function testATokenThatIsNotTextIsRefused(): void
    {
        self::assertSame(
            $this->getErrorResponse(),
            $this->process('POST', self::MODULE, '', [CsrfTokenServiceInterface::FIELD_NAME => ['x']]),
        );
        self::assertSame([], $this->checkedTokens);
    }

    public function testAnExemptModuleIsNotChecked(): void
    {
        self::assertSame($this->getNextResponse(), $this->process('POST', self::EXEMPT_MODULE, '', null));
        self::assertSame([], $this->logEntries);
    }

    public function testARequestOutsideAModuleIsChecked(): void
    {
        self::assertSame($this->getErrorResponse(), $this->process('POST', null, '', null));
    }

    /**
     * @return iterable<string,array{0:string}>
     */
    public static function safeMethodProvider(): iterable
    {
        yield 'GET' => ['GET'];

        yield 'HEAD' => ['HEAD'];

        yield 'OPTIONS' => ['OPTIONS'];
    }

    /**
     * @return iterable<string,array{0:string}>
     */
    public static function unsafeMethodProvider(): iterable
    {
        yield 'POST' => ['POST'];

        yield 'PUT' => ['PUT'];

        yield 'PATCH' => ['PATCH'];

        yield 'DELETE' => ['DELETE'];
    }

    private function createCsrfTokenService(): CsrfTokenServiceInterface
    {
        $csrfTokenService = self::createStub(CsrfTokenServiceInterface::class);
        $csrfTokenService->method('isValid')->willReturnCallback(
            function (string $token): bool {
                $this->checkedTokens[] = $token;

                return $token === self::TOKEN;
            },
        );

        return $csrfTokenService;
    }

    private function createErrorRequestHandler(): RequestHandlerInterface
    {
        $errorRequestHandler = self::createStub(RequestHandlerInterface::class);
        $errorRequestHandler->method('handle')->willReturnCallback(
            function (ServerRequestInterface $request): ResponseInterface {
                $throwable = $request->getAttribute('throwable');
                self::assertInstanceOf(Throwable::class, $throwable);
                $this->errorPageThrowable = $throwable;

                return $this->getErrorResponse();
            },
        );

        return $errorRequestHandler;
    }

    private function createHandler(): RequestHandlerInterface
    {
        $handler = self::createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->getNextResponse());

        return $handler;
    }

    private function createLogger(): LoggerInterface
    {
        $logger = self::createStub(LoggerInterface::class);
        $logger->method('warning')->willReturnCallback(
            /**
             * @phpcs:ignore SlevomatCodingStandard.TypeHints.DisallowMixedTypeHint.DisallowedMixedTypeHint
             * @param array<mixed> $context
             */
            function (string $message, array $context): void {
                $this->logEntries['warning'][] = [$message, $context];
            },
        );

        return $logger;
    }

    /**
     * Stubs of the PSR-7 request and URI: `withAttribute()` gives a request whose `getAttribute()` returns the value.
     *
     * @param ?string $module Route part 1; null: a path no module handles.
     * @param array<string,array<string>|string>|null $parsedBody
     */
    private function createRequest(
        string $method,
        ?string $module,
        string $headerToken,
        ?array $parsedBody,
    ): ServerRequestInterface {
        $uri = self::createStub(UriInterface::class);
        $uri->method('getPath')->willReturn('/path');
        $request = self::createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);
        $request->method('getHeaderLine')->willReturnMap([[CsrfTokenServiceInterface::HEADER_NAME, $headerToken]]);
        $request->method('getParsedBody')->willReturn($parsedBody);
        $request->method('getAttribute')->willReturnMap([['routePart.1', null, $module]]);
        $request->method('withAttribute')->willReturnCallback(
            static function (string $name, mixed $value): ServerRequestInterface {
                $requestWithAttribute = self::createStub(ServerRequestInterface::class);
                $requestWithAttribute->method('getAttribute')->willReturnMap([[$name, null, $value]]);

                return $requestWithAttribute;
            },
        );

        return $request;
    }

    private function getErrorResponse(): ResponseInterface
    {
        return $this->errorResponse ??= self::createStub(ResponseInterface::class);
    }

    private function getErrorThrowable(): Throwable
    {
        return $this->errorThrowable ??= new RuntimeException('This page has expired.', 403);
    }

    private function getNextResponse(): ResponseInterface
    {
        return $this->nextResponse ??= self::createStub(ResponseInterface::class);
    }

    /**
     * @param array<string,array<string>|string>|null $parsedBody
     */
    private function process(
        string $method,
        ?string $module,
        string $headerToken,
        ?array $parsedBody,
    ): ResponseInterface {
        $middleware = new CsrfMiddleware(
            $this->createCsrfTokenService(),
            $this->createErrorRequestHandler(),
            $this->getErrorThrowable(),
            [self::EXEMPT_MODULE],
            $this->createLogger(),
            new ServerRequestAttributeService(),
        );

        return $middleware->process(
            $this->createRequest($method, $module, $headerToken, $parsedBody),
            $this->createHandler(),
        );
    }
}
