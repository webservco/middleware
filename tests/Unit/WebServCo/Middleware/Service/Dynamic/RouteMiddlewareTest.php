<?php

declare(strict_types=1);

namespace Tests\Unit\WebServCo\Middleware\Service\Dynamic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use WebServCo\Middleware\Service\AbstractRouteMiddleware;
use WebServCo\Middleware\Service\Dynamic\RouteMiddleware;

#[CoversClass(AbstractRouteMiddleware::class)]
#[CoversClass(RouteMiddleware::class)]
final class RouteMiddlewareTest extends TestCase
{
    /**
     * Request attributes set by the middleware.
     *
     * @var array<string,string>
     */
    private array $attributes = [];

    /**
     * @param array<string,string> $expected
     */
    #[DataProvider('pathProvider')]
    public function testRouteParts(string $path, array $expected): void
    {
        $uri = self::createStub(UriInterface::class);
        $uri->method('getPath')->willReturn($path);

        $request = self::createStub(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('withAttribute')->willReturnCallback(
            function (string $name, mixed $value) use ($request): ServerRequestInterface {
                self::assertIsString($value);
                $this->attributes[$name] = $value;

                return $request;
            },
        );

        $response = self::createStub(ResponseInterface::class);
        $handler = self::createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $routeMiddleware = new RouteMiddleware('/', 'example/test', ['example']);

        self::assertSame($response, $routeMiddleware->process($request, $handler));
        self::assertSame($expected, $this->attributes);
    }

    /**
     * @return iterable<string,array{0:string,1:array<string,string>}> Request path, expected request attributes.
     */
    public static function pathProvider(): iterable
    {
        yield 'plain' => ['/example/test', ['routePart.1' => 'example', 'routePart.2' => 'test']];

        yield 'default path' => ['/', ['routePart.1' => 'example', 'routePart.2' => 'test']];

        yield 'not handled' => ['/other/test', []];

        yield 'handled check uses the decoded part' => ['/%65xample', ['routePart.1' => 'example']];

        yield 'non-ASCII' => ['/example/caf%C3%A9', ['routePart.1' => 'example', 'routePart.2' => 'café']];

        yield 'space' => ['/example/hello%20world', ['routePart.1' => 'example', 'routePart.2' => 'hello world']];

        yield 'plus is not a space' => ['/example/a+b', ['routePart.1' => 'example', 'routePart.2' => 'a+b']];

        yield 'ampersand, not HTML-escaped' => [
            '/example/rock&roll',
            ['routePart.1' => 'example', 'routePart.2' => 'rock&roll'],
        ];

        yield 'apostrophe, not HTML-escaped' => [
            "/example/O'Brien",
            ['routePart.1' => 'example', 'routePart.2' => "O'Brien"],
        ];

        yield 'tags, not stripped' => ['/example/%3Cb%3E', ['routePart.1' => 'example', 'routePart.2' => '<b>']];

        yield 'decoded once' => ['/example/100%2525', ['routePart.1' => 'example', 'routePart.2' => '100%25']];

        yield 'encoded slash' => ['/example/a%2Fb', []];

        yield 'null byte' => ['/example/a%00b', []];

        yield 'invalid UTF-8' => ['/example/%FF', []];
    }
}
