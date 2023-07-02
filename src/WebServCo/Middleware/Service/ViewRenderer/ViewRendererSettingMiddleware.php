<?php

declare(strict_types=1);

namespace WebServCo\Middleware\Service\ViewRenderer;

use OutOfBoundsException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use UnexpectedValueException;
use WebServCo\Http\Contract\Message\Request\Server\ServerHeadersAcceptProcessorInterface;
use WebServCo\View\Contract\HTMLRendererInterface;
use WebServCo\View\Contract\JSONRendererInterface;
use WebServCo\View\Contract\ViewRendererInterface;

final class ViewRendererSettingMiddleware implements MiddlewareInterface
{
    public function __construct(private ServerHeadersAcceptProcessorInterface $serverHeadersAcceptProcessor)
    {
    }

    /**
     * Set the View renderer interface to be use for the response, based on the request.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $acceptHeaderValue = $this->serverHeadersAcceptProcessor->getAcceptHeaderValue($request);
        } catch (OutOfBoundsException) {
            // "Accept header array is empty." Assume accept anything.
            $acceptHeaderValue = '*/*;q=0.8';
        }
        $acceptList = $this->serverHeadersAcceptProcessor->processAcceptList($acceptHeaderValue);
        $viewRenderer = $this->getViewRenderer($acceptList);

        $request = $request->withAttribute('ViewRendererInterface', $viewRenderer);

        // Pass to the next handler.
        return $handler->handle($request);
    }

    /**
     * @param array<string,string> $acceptList
     */
    private function getViewRenderer(array $acceptList): string
    {
        // Loop over acceptList in order to consider priority while figuring out the View renderer to use.
        foreach ($acceptList as $accept) {
            try {
                // Get View renderer for current accept item
                return $this->getViewRendererItem($accept);
            } catch (UnexpectedValueException) {
                // No View renderer for current accept item. Nothing to do here, will go to next loop and try there.
            }
        }

        throw new UnexpectedValueException('No View renderer available for any of the provided accept mime types.');
    }

    private function getViewRendererItem(string $accept): string
    {
        return match ($accept) {
            // JSON
            JSONRendererInterface::CONTENT_TYPE => JSONRendererInterface::class,
            // HTML
            HTMLRendererInterface::CONTENT_TYPE => HTMLRendererInterface::class,
            // General; 'accept anything', the 'any' flag
            '*/*' => ViewRendererInterface::class,
            // Any other situation
            default => throw new UnexpectedValueException('Unhandled accept mime type.'),
        };
    }
}
