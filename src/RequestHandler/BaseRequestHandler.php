<?php

namespace lav45\MockServer\RequestHandler;

use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use lav45\MockServer\Request\WrappedRequest;

abstract class BaseRequestHandler implements RequestHandler, WrappedRequestHandlerInterface
{
    public function handleRequest(Request $request): Response
    {
        $wrappedRequest = WrappedRequest::getInstance($request);
        return $this->handleWrappedRequest($wrappedRequest);
    }
}
