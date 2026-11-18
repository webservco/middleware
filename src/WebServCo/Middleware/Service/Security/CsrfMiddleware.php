<?php

declare(strict_types=1);

namespace WebServCo\Middleware\Service\Security;

use Fig\Http\Message\RequestMethodInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use WebServCo\Http\Contract\Message\Request\Server\ServerRequestAttributeServiceInterface;
use WebServCo\Session\Contract\CsrfTokenServiceInterface;

use function array_key_exists;
use function in_array;
use function is_array;
use function is_string;

/**
 * CSRF check: a request that can change state (any method but GET, HEAD and OPTIONS) must carry the token of the
 * session, in the header or the form field (`CsrfTokenServiceInterface`).
 *
 * Every module, except the ones the project exempts (eg. an API module that authenticates with a token instead of the
 * session cookie). Without a session there is no token, so such a request always fails.
 *
 * A missing or wrong token gets the error page of the throwable the project gives (eg. 403, "This page has expired").
 * A form left open until its session ended looks the same as a forged one.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const array SAFE_METHODS = [
        RequestMethodInterface::METHOD_GET,
        RequestMethodInterface::METHOD_HEAD,
        RequestMethodInterface::METHOD_OPTIONS,
    ];

    /**
     * @param \Psr\Http\Server\RequestHandlerInterface $errorRequestHandler Renders the error page of the `throwable`
     *     request attribute (the request handler of `ExceptionHandlerMiddleware`).
     * @param \Throwable $errorThrowable The error of a refused request, put into the `throwable` request attribute.
     *     Never thrown: its code and message are what the error page shows.
     * @param array<int,string> $exemptModules Route part 1 values not checked.
     */
    public function __construct(
        private CsrfTokenServiceInterface $csrfTokenService,
        private RequestHandlerInterface $errorRequestHandler,
        private Throwable $errorThrowable,
        private array $exemptModules,
        private LoggerInterface $logger,
        private ServerRequestAttributeServiceInterface $serverRequestAttributeService,
    ) {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->isExempt($request) || $this->hasValidToken($request)) {
            return $handler->handle($request);
        }

        // No token values in the log.
        $this->logger->warning(
            'CSRF token missing or not valid.',
            ['method' => $request->getMethod(), 'path' => $request->getUri()->getPath()],
        );

        return $this->errorRequestHandler->handle($request->withAttribute('throwable', $this->errorThrowable));
    }

    /**
     * The token of the request: the header first, then the form field.
     */
    private function getRequestToken(ServerRequestInterface $request): ?string
    {
        $headerToken = $request->getHeaderLine(CsrfTokenServiceInterface::HEADER_NAME);
        if ($headerToken !== '') {
            return $headerToken;
        }

        $parsedBody = $request->getParsedBody();
        if (
            is_array($parsedBody)
            && array_key_exists(CsrfTokenServiceInterface::FIELD_NAME, $parsedBody)
            && is_string($parsedBody[CsrfTokenServiceInterface::FIELD_NAME])
        ) {
            return $parsedBody[CsrfTokenServiceInterface::FIELD_NAME];
        }

        return null;
    }

    private function hasValidToken(ServerRequestInterface $request): bool
    {
        $token = $this->getRequestToken($request);

        return $token !== null && $this->csrfTokenService->isValid($token);
    }

    private function isExempt(ServerRequestInterface $request): bool
    {
        return in_array($request->getMethod(), self::SAFE_METHODS, true)
            || in_array($this->serverRequestAttributeService->getRoutePart(1, $request), $this->exemptModules, true);
    }
}
