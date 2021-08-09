<?php

namespace Codeception\Lib\Middleware;

use Codeception\Module\FakeApi;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
use function RingCentral\Psr7\str;
use function React\Promise\reject;

class RequestRecorder
{
    /**
     * @var Response[]
     */
    protected $recordedResponses = [];
    /**
     * @var ServerRequestInterface[]
     */
    protected $recordedRequests = [];

    /**
     * @var ServerRequestInterface
     */
    protected $lastRequest;
    /**
     * @var Response
     */
    protected $lastResponse;
    public function __invoke(ServerRequestInterface $request, $next)
    {
        try {
            $response = $next($request);
        } catch (\React\Http\Message\ResponseException $e) {
            $response = $e->getResponse();
        }
        if (!$response instanceof PromiseInterface) {
            $response = \React\Promise\resolve($response);
        }
        return $response->then(function (ResponseInterface $response) use ($request) {
            $this->_log('Response will be:');
            $this->_log(str($response));
            $this->lastRequest = $request;
            $this->lastResponse = $response;
            $urlString = FakeApi::getUrlString($request);
            $this->recordedRequests[] = [
                'method' => $request->getMethod(),
                'urlString' => $urlString,
                'headers' => $request->getHeaders(),
                'body' => (string)$request->getBody()
            ];
            $this->recordedResponses[] = [
                'statusCode' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'content' => (string)$response->getBody()
            ];
            return $response;
        })->otherwise(function (\Throwable $e) use ($request) {
            $this->lastRequest = $request;
            $this->lastResponse = $e;
            $this->recordedRequests[] = [
                'method' => $request->getMethod(),
                'urlString' => FakeApi::getUrlString($request),
                'headers' => $request->getHeaders(),
                'body' => (string)$request->getBody()
            ];
            $this->recordedResponses[] = [
                'errorCode' => $e->getCode(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
            $this->_log("Unexpected exception caught");
            $this->_log($e->getMessage());
            return reject($e);
        });
    }
    /**
     *
     * @return Request
     */
    public function getLastRequest()
    {
        return $this->lastRequest;
    }
    /**
     *
     * @return ServerRequestInterface
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }
    /**
     *
     * @return ServerRequestInterface[]
     */
    public function getRecordedRequests()
    {
        return $this->recordedRequests;
    }
    /**
     *
     * @return Response[]
     */
    public function getRecordedResponses()
    {
        return $this->recordedResponses;
    }

    /**
     * @return void
     */
    public function flushRecordedRequests()
    {
        $this->recordedRequests = [];
        $this->recordedResponses = [];
        $this->lastRequest = null;
        $this->lastResponse = null;
    }
    /**
     * Helper function to debug log messages
     *
     * @param string $message
     * @return void
     */
    protected function _log($message)
    {
        codecept_debug("[RequestRecorder] $message");
    }
}
