<?php

namespace Codeception\Lib\Middleware;

use Codeception\Module\FakeApi;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;

use function React\Promise\reject;

class ProxyRequest
{
    /**
     * @var string
     */
    protected $upstreamUrl;
    /**
     * @var Response[]
     */
    protected $proxiedResponses = [];
    /**
     * @var ServerRequestInterface[]
     */
    protected $proxiedRequests = [];

    /**
     * @var LoopInterface
     */
    protected $loop;
    /**
     * @var integer
     */
    protected $upstreamTimeout;
    public function __construct(LoopInterface $loop, $upstreamTimeout)
    {
        $this->loop = $loop;
        $this->upstreamTimeout = $upstreamTimeout;
    }
    public function __invoke(ServerRequestInterface $request, $next)
    {
        if (is_null($this->upstreamUrl)) {
            return $next($request);
        }
        $this->_log("Proxy requests to [{$this->upstreamUrl}]");
        return $this->proxyRequest($request);
    }
    /**
     * Helper function to debug log messages
     *
     * @param string $message
     * @return void
     */
    protected function _log($message)
    {
        codecept_debug("[ProxyRequest] $message");
    }

    /**
     * @param ServerRequestInterface $serverRequest
     * @return PromiseInterface
     */
    protected function proxyRequest(ServerRequestInterface $serverRequest)
    {
        $client = new \React\Http\Browser($this->loop);
        $method = $serverRequest->getMethod();
        $headers = $serverRequest->getHeaders();
        $body = $serverRequest->getBody();
        unset($headers['Host']);
        $urlString = FakeApi::getUrlString($serverRequest);
        $client->withTimeout($this->upstreamTimeout);
        $response = $client->request($method, $this->upstreamUrl . $urlString, $headers, (string)$body);
        $handle = function ($response) use ($serverRequest) {
            $this->proxiedRequests[] = [
                'method' => $serverRequest->getMethod(),
                'urlString' => FakeApi::getUrlString($serverRequest),
                'headers' => $serverRequest->getHeaders(),
                'body' => (string)$serverRequest->getBody()
            ];
            $this->proxiedResponses[] = [
                'statusCode' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'content' => (string)$response->getBody()
            ];
            return $response;
        };
        return $response->then($handle)->otherwise(function (\React\Http\Message\ResponseException $e) use ($serverRequest) {
            $this->proxiedRequests[] = [
                'method' => $serverRequest->getMethod(),
                'urlString' => FakeApi::getUrlString($serverRequest),
                'headers' => $serverRequest->getHeaders(),
                'body' => (string)$serverRequest->getBody()
            ];
            $response = $e->getResponse();
            $this->proxiedResponses[] = [
                'statusCode' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'content' => (string)$response->getBody()
            ];
            return $response;
        })->otherwise(function (\Throwable $e) use ($serverRequest) {
            $this->proxiedRequests[] = [
                'method' => $serverRequest->getMethod(),
                'urlString' => FakeApi::getUrlString($serverRequest),
                'headers' => $serverRequest->getHeaders(),
                'body' => (string)$serverRequest->getBody()
            ];
            $this->proxiedResponses[] = [
                'errorCode' => $e->getCode(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
            return reject($e);
        });
    }

    /**
     * @param string $url
     * @return void
     */
    public function setUpstreamUrl($url)
    {
        $this->upstreamUrl = $url;
    }

    /**
     * @return array
     */
    public function getProxiedResponses()
    {
        return $this->proxiedResponses;
    }
    /**
     * @return array
     */
    public function getProxiedRequests()
    {
        return $this->proxiedRequests;
    }
}
