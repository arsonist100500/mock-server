<?php declare(strict_types=1);

namespace lav45\MockServer;

use Amp\Http\HttpStatus;
use Amp\Http\Server\ErrorHandler;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler as RequestHandlerInterface;
use Amp\Http\Server\Response;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use lav45\MockServer\middlewares\RequestParamsMiddleware;
use lav45\MockServer\middlewares\WebhooksMiddleware;
use Monolog\Logger;
use UnexpectedValueException;
use function FastRoute\simpleDispatcher;
use function implode;
use function rawurldecode;

class Reactor implements RequestHandlerInterface
{
    private string $mocksPath;

    public function __construct(
        string                        $mocksPath,
        private readonly ErrorHandler $errorHandler,
        private readonly FakerParser  $faker,
        private readonly Logger       $logger
    )
    {
        $this->mocksPath = rtrim($mocksPath, '/');
    }

    public function handleRequest(Request $request): Response
    {
        $method = $request->getMethod();
        $uri = rawurldecode($request->getUri()->getPath());

        $file = $this->getFile($uri);
        if ($file === null) {
            return $this->makeNotFoundResponse($request);
        }

        $routeDispatcher = simpleDispatcher(function (RouteCollector $rc) use ($file): void {
            $items = $this->getItems($file);
            foreach ($items as $item) {
                $this->addRoute($rc, $item);
            }
        });

        $match = $routeDispatcher->dispatch($method, $uri);

        return $this->dispatch($match, $request);
    }

    private function dispatch(array $match, Request $request): Response
    {
        switch ($match[0]) {
            case Dispatcher::FOUND:
                /**
                 * @var RequestHandlerInterface $requestHandler
                 * @var string[] $routeArgs
                 */
                [, $requestHandler, $routeArgs] = $match;
                $request->setAttribute(self::class, $routeArgs);
                return $requestHandler->handleRequest($request);

            case Dispatcher::NOT_FOUND:
                return $this->makeNotFoundResponse($request);

            case Dispatcher::METHOD_NOT_ALLOWED:
                return $this->makeMethodNotAllowedResponse($match[1], $request);

            default:
                throw new UnexpectedValueException("Encountered unexpected dispatcher code: " . $match[0]);
        }
    }

    private function getFile(string $uri): ?string
    {
        if (str_contains($uri, '?')) {
            $uri = strstr($uri, '?', true);
        }

        if ($uri === '/') {
            $uri = '/index';
        } else {
            $uri = rtrim($uri, '/');
        }

        $file = "{$this->mocksPath}{$uri}.json";
        if (file_exists($file) === false) {
            $uri = dirname($uri);
            if ($uri === '/') {
                return null;
            }
            return $this->getFile($uri);
        }
        return $file;
    }

    private function getItems(string $file): array
    {
        $content = file_get_contents($file);
        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }

    private function addRoute(RouteCollector $rc, array $item): void
    {
        $mock = new Mock($item);
        $request = $mock->getRequest();
        $response = $mock->getResponse();
        $webhooks = $mock->getWebhooks();

        $parser = new EnvParser($this->faker);
        $parser->addData([
            'env' => $parser->replaceFaker($mock->env)
        ]);

        $requestHandler = Middleware\stackMiddleware(
            new RequestHandler($response, $parser),
            new WebhooksMiddleware($webhooks, $parser, $this->logger),
            new RequestParamsMiddleware($parser)
        );

        $rc->addRoute($request->getMethod(), $request->url, $requestHandler);
    }

    /**
     * Create a response if no routes matched and no fallback has been set.
     */
    private function makeNotFoundResponse(Request $request): Response
    {
        return $this->errorHandler->handleError(HttpStatus::NOT_FOUND, null, $request);
    }

    /**
     * Create a response if the requested method is not allowed for the matched path.
     */
    private function makeMethodNotAllowedResponse(array $methods, Request $request): Response
    {
        $response = $this->errorHandler->handleError(HttpStatus::METHOD_NOT_ALLOWED, null, $request);
        $response->setHeader('allow', implode(', ', $methods));
        return $response;
    }
}