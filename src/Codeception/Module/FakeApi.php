<?php

namespace Codeception\Module;

use Codeception\Lib\Middleware\RequestExpectation;
use Codeception\Test\Cest;
use Codeception\Util\Stub;
use LeeShan87\React\MultiLoop\MultiLoop;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Message\Response;
use React\Http\Server;
use React\Promise\PromiseInterface;
use React\Socket\ServerInterface;
use RingCentral\Psr7\ServerRequest;

use function React\Promise\reject;
use function RingCentral\Psr7\str;

/**
 * This Codeception module helps to create an async FakeApi http server.
 *
 * This module requires react/http:^1.0.0 to work.
 *
 * It provides an async http server, which can
 * - Respond to http request
 * - Proxy request to an external http server
 * - Record proxied requests and store them on the disk
 *
 * It can be used in Codeception tests
 *
 * Example Cept usage:
 *
 * ```php
 *  <?php
 * $I->wantTo('Save some api calls for testing');
 * $I->setUpstreamUrl('https://example.com');
 * $I->initFakeServer();
 * $loop = $I->grabFakeApiLoop();
 * $I->recordRequestsForSeconds(30);
 * $I->waitTillFakeApiRecordingEnds();
 * $I->stopFakeApi();
 * $I->saveRecordedInformation(codecept_output_dir(date('Y_m_d_H_i_s') . ".json"));
 * ```
 *
 * Example usage out side Codeception
 * ```php
 *  <?php
 * $api = new FakeApi(Stub::make(\Codeception\Lib\ModuleContainer::class));
 * $api->setBindPort(8081);
 * $api->initFakeServer();
 * $api->addMessage(200, [], 'hello');
 * $api->addMessage(200, [], 'hello w');
 * $api->addMessage(200, [], 'hello wor');
 * $api->addMessage(200, [], 'hello world');
 * $api->run();
 * ```
 */
class FakeApi extends \Codeception\Module
{
    /**
     * @var LoopInterface
     */
    protected $fakeApiLoop;
    /**
     * @var boolean
     */
    protected $tickDisabled = false;
    /**
     * @var boolean
     */
    protected $doLog = false;
    /**
     * Optional Codeception module configuration
     *
     * @link https://codeception.com/docs/06-ModulesAndHelpers#Configuration
     * @var array
     */
    protected $config = [
        'bind' => 8080,
        'upstreamUrl' => null,
        'upstreamTimeout' => 5,
        'recordInterval' => 30
    ];
    /**
     * @var \React\Http\Server
     */
    protected $server;
    /**
     * @var \React\Socket\Server
     */
    protected $socket;
    /**
     * @var string
     */
    protected $upstreamUrl;
    /**
     * @var array
     */
    protected $messages = [];
    /**
     * @var integer
     */
    protected $recordingStartTime;
    /**
     * @var integer
     */
    protected $recordInterval = 30;
    /**
     * @var Response[]
     */
    protected $recordedResponses = [];
    /**
     * @var ServerRequestInterface[]
     */
    protected $recordedRequests = [];

    /**
     * @var Response[]
     */
    protected $proxiedResponses = [];
    /**
     * @var ServerRequestInterface[]
     */
    protected $proxiedRequests = [];

    protected $expectedRequests = [];
    /**
     * @var Cest
     */
    protected $test;
    protected $currentMiddlewares = [];
    /**
     * @var \React\Socket\Connection
     */
    protected $mockedConnection;
    /**
     * @var ServerRequestInterface
     */
    protected $lastRequest;
    /**
     * @var Response
     */
    protected $lastResponse;
    protected $hasDefinedResponses = false;
    /**
     * @var LoopInterface
     */
    protected $echoUpstreamLoop;
    /**
     * @var Server
     */
    protected $echoUpstreamServer;
    /**
     * @var \React\Socket\Server
     */
    protected $echoUpstreamSocket;
    public function _beforeSuite($settings = [])
    {
        $mockedConnection = Stub::make(\React\Socket\Connection::class, [
            'write' => null,
            'end' => null,
            'close' => null,
            'pause' => null,
            'resume' => null,
            'isReadable' => true,
            'isWritable' => true,
            'getRemoteAddress' => null,
            'getLocalAddress' => null,
            'pipe' => null
        ]);
        $this->mockedConnection = $mockedConnection;
    }
    public function _before($test)
    {
        $this->test = $test;
    }
    public function enableLog()
    {
        $this->doLog = true;
    }
    public function disableLog()
    {
        $this->doLog = false;
    }
    public function reset()
    {
        $this->recordedRequests = [];
        $this->recordedResponses = [];
        $this->lastRequest = null;
        $this->lastResponse = null;
        $this->proxiedRequests = [];
        $this->proxiedResponses = [];
        $this->upstreamUrl = !is_null($this->upstreamUrl) ? $this->upstreamUrl : $this->config['upstreamUrl'];
    }
    /**
     * Initialize FakeApi Server
     *
     *
     * @return void
     */
    public function initFakeServer()
    {
        $this->reset();
        $loop = \React\EventLoop\Factory::create();
        $this->currentMiddlewares = $this->createMiddlewares();
        $server = new \React\Http\Server($loop, ...$this->currentMiddlewares);
        $socket = $this->createFakeApiSocket($this->config['bind'], $loop);
        $server->listen($socket);
        $this->fakeApiLoop = $loop;
        MultiLoop::addLoop($loop, 'FakeApi');
        $this->socket = $socket;
        $this->server = $server;
        $this->_log("FakeApi service created with url [{$this->grabFakeApiUrl()}]");
    }
    /**
     *
     * @param string $bind
     * @param LoopInterface $loop
     * @param integer $retry
     * @return ServerInterface
     */
    protected function createFakeApiSocket($bind, $loop, $retry = 10)
    {
        try {
            $this->_log("Try to bind on [$bind]");
            return new \React\Socket\Server($bind, $loop);
        } catch (\RuntimeException $e) {
            $this->_log($e->getMessage());
            if (is_numeric($bind) && $retry > 0) {
                return $this->createFakeApiSocket(++$bind, $loop, --$retry);
            }
        }
    }

    /*
    Send Json request to Echo Service:
    curl --header "Content-Type: application/json" \
    --request POST \
    --data '{"username":"xyz","password":"xyz"}' \
    http://localhost:8081/api/login

    Send file upload request to echo service:
    curl -F 'upload_readme=@./README.md' http://localhost:8081/upload
    */

    /**
     *
     * @param string $bind
     * @return void
     */
    public function createEchoUpstream($bind)
    {
        $loop = \React\EventLoop\Factory::create();
        MultiLoop::addLoop($loop, 'EchoService');
        $this->echoUpstreamLoop = $loop;
        $server = new \React\Http\Server($loop, function (ServerRequestInterface $request) {
            return new Response(200, ['Service-type' => 'Echo Service'], str($request));
        });
        $this->echoUpstreamServer = $server;
        $socket = $this->createFakeApiSocket($bind, $loop);
        $this->echoUpstreamSocket = $socket;
        $server->listen($socket);
        $this->_log("Echo service created with url [{$this->grabEchoServiceUrl()}]");
    }
    /**
     * @return void
     */
    public function stopEchoUpstream()
    {
        MultiLoop::removeLoop('EchoService');
        $this->echoUpstreamLoop->stop();
        $this->echoUpstreamSocket->close();
        unset($this->echoUpstreamLoop);
        unset($this->echoUpstreamServer);
        unset($this->echoUpstreamSocket);
    }

    /**
     * @return string
     */
    public function grabEchoServiceUrl()
    {
        return str_replace('tcp://', 'http://', $this->echoUpstreamSocket->getAddress());
    }

    /**
     * @return array
     */
    protected function createMiddlewares()
    {
        $middlewares = [
            function (ServerRequestInterface  $request, $next) {
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
                    $urlString = $this->getUrlString($request);
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
                        'urlString' => $this->getUrlString($request),
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
            },
        ];
        $middlewares = array_merge($middlewares, $this->getExpectedRequests());
        $middlewares[] =
            function (ServerRequestInterface  $request, $next) {
                if (is_null($this->upstreamUrl)) {
                    return $next($request);
                }
                $this->_log("Proxy requests to [{$this->upstreamUrl}]");
                return $this->proxyRequest($request);
            };
        $middlewares[] = function (ServerRequestInterface  $request, $next) {
            if (!$this->hasDefinedResponses) {
                return $next($request);
            }
            $response = array_shift($this->messages);
            if (empty($this->messages)) {
                $this->fakeApiLoop->futureTick(function () {
                    $this->fakeApiLoop->stop();
                    $this->socket->close();
                });
            }
            return $response;
        };
        $middlewares[] = function (ServerRequestInterface  $request) {
            return new Response(404);
        };
        return $middlewares;
    }
    public function getExpectedRequests()
    {
        return $this->expectedRequests;
    }

    public function waitForSeconds($secounds)
    {
        MultiLoop::waitForSeconds($secounds);
    }
    public function waitTillFakeApiRecordingEnds()
    {
        while ($this->notSeeRecordingEnded()) {
            MultiLoop::tickAll();
        }
    }

    /**
     *
     * @param integer $seconds
     * @return void
     */
    public function waitTillNextRequestResolves($seconds = 20)
    {
        $startTime = time();
        $maxExecutionTime = $startTime + $seconds;
        $resolvedRequests = count($this->recordedRequests);
        while ($resolvedRequests === count($this->recordedRequests) && time() < $maxExecutionTime) {
            MultiLoop::tickAll();
        }
    }
    /**
     *
     * @param integer $maxDelay
     * @return void
     */
    public function waitTillAllRequestsResolved($maxDelay = 20)
    {
        $waits = 0;
        foreach ($this->currentMiddlewares as $middleware) {
            if ($middleware instanceof RequestExpectation) {
                $waits += $middleware->getExpectedInvocationCount();
            }
        }
        for ($i = 0; $i < $waits; $i++) {
            $this->waitTillNextRequestResolves($maxDelay);
        }
    }
    /**
     * @param ServerRequestInterface $serverRequest
     * @return PromiseInterface
     */
    protected function proxyRequest(ServerRequestInterface $serverRequest)
    {
        $client = new \React\Http\Browser($this->fakeApiLoop);
        $method = $serverRequest->getMethod();
        $headers = $serverRequest->getHeaders();
        $body = $serverRequest->getBody();
        unset($headers['Host']);
        $urlString = $this->getUrlString($serverRequest);
        $client->withTimeout($this->config['upstreamTimeout']);
        $response = $client->request($method, $this->upstreamUrl . $urlString, $headers, (string)$body);
        $handle = function ($response) use ($serverRequest) {
            $this->proxiedRequests[] = [
                'method' => $serverRequest->getMethod(),
                'urlString' => $this->getUrlString($serverRequest),
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
                'urlString' => $this->getUrlString($serverRequest),
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
                'urlString' => $this->getUrlString($serverRequest),
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
    public function getUrlString(ServerRequestInterface $serverRequest)
    {
        $url = $serverRequest->getUri();
        $url->getPath();
        return $url->getPath() . ($url->getQuery() === '' ? '' : '?' . $url->getQuery()) . ($url->getFragment() === '' ? '' : '#' . $url->getFragment());
    }
    /**
     * @param integer $status
     * @param array $headers
     * @param string $content
     * @return void
     */
    public function addMessage($status,  $headers, $content)
    {
        $this->hasDefinedResponses = true;
        $this->messages[] = new Response(
            $status,
            $headers,
            $content
        );
    }
    /**
     * @return void
     */
    public function tickFakeApiLoop()
    {
        if ($this->tickDisabled) {
            return;
        }
        $loop = $this->fakeApiLoop;
        $loop->futureTick(function () use ($loop) {
            $loop->stop();
        });

        $loop->run();
    }
    /**
     * @return void
     */
    public function run()
    {
        while ($this->hasMessage()) {
            $this->tickFakeApiLoop();
            usleep(2000);
        }
        $this->tickFakeApiLoop();
    }
    /**
     * @param integer|null $sec
     * @return void
     */
    public function recordRequestsForSeconds($sec = null)
    {
        $this->recordingStartTime = time();
        $recordInterval = $sec ?: $this->config['recordInterval'];
        $this->recordInterval = $recordInterval;
        $this->_log("Recording for [$recordInterval]");
        if ($this->tickDisabled) {
            return;
        }
        while ($this->notSeeRecordingEnded()) {
            $this->tickFakeApiLoop();
        }
    }
    /**
     * @param string $method
     * @param string $url
     * @param array|null $headers
     * @param string $body
     * @return PromiseInterface
     */
    public function sendRequest($method = 'GET',  $url = '/',  $headers = [],  $body = '')
    {
        $loop = \React\EventLoop\Factory::create();
        MultiLoop::addLoop($loop, 'client');
        $client = new \React\Http\Browser($loop);

        //$client->withBase();
        $client->withTimeout($this->config['upstreamTimeout']);
        $this->_log('Sending request [' . "$method " . $this->grabFakeApiUrl() . $url . ']');
        return $client->request($method, $this->grabFakeApiUrl() . $url, $headers, $body);
    }
    /**
     * @param string $method
     * @param string $url
     * @param array|null $headers
     * @param array $body
     * @return PromiseInterface
     */
    public function sendJsonRequest($method = 'GET',  $url = '/',  $headers = [],  $body = [])
    {
        return $this->sendRequest(
            $method,
            $url,
            array_merge(['Content-Type' => 'application/json'], $headers),
            json_encode($body)
        );
    }
    /**
     * You cannot send POSt request with this method
     *
     * @param ServerRequest $serverRequest
     * @return void
     */
    public function sendMockedRequest(ServerRequest $serverRequest)
    {
        $this->_log("Sending mocked request:\n" . str($serverRequest));
        $this->socket->emit('connection', [$this->mockedConnection]);
        $this->mockedConnection->emit('data', [str($serverRequest)]);
    }
    /**
     *
     * @param string $string
     * @return void
     */
    public function sendMockedRequestRaw($string)
    {
        $this->_log("Sending raw mocked request:\n"  . $string);
        $this->socket->emit('connection', [$this->mockedConnection]);
        $this->mockedConnection->emit('data', [$string]);
    }
    public function grabLastRequest()
    {
        return $this->lastRequest;
    }
    public function grabLastResponse()
    {
        return $this->lastResponse;
    }
    /**
     * @return boolean
     */
    public function notSeeRecordingEnded()
    {
        return time() < ($this->recordingStartTime + $this->recordInterval);
    }
    /**
     * @return boolean
     */
    public function hasMessage()
    {
        return !empty($this->messages);
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
     * @param integer $port
     * @return void
     */
    public function setBindPort($port = 8080)
    {
        $this->bind = $port;
    }
    /**
     * @param integer $sec
     * @return void
     */
    public function setRecordInterval($sec)
    {
        $this->recordInterval = $sec;
    }
    /**
     * @return array
     */
    public function getRecordedResponses()
    {
        return $this->recordedResponses;
    }
    /**
     * @return array
     */
    public function getRecordedRequests()
    {
        return $this->recordedRequests;
    }
    /**
     * @return array
     */
    public function grabRecordedResponses()
    {
        return $this->recordedResponses;
    }
    /**
     * @return array
     */
    public function grabRecordedRequests()
    {
        return $this->recordedRequests;
    }
    /**
     * @return array
     */
    public function grabProxiedResponses()
    {
        return $this->proxiedResponses;
    }
    /**
     * @return array
     */
    public function grabProxiedRequests()
    {
        return $this->proxiedRequests;
    }
    /**
     * @param string $file
     * @return void
     */
    public function saveRecordedInformation($file)
    {
        $data = [];
        foreach ($this->getRecordedRequests() as $key => $request) {
            $data[$key] = [
                'request' => $request,
                'response' => $this->recordedResponses[$key]
            ];
        }
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    }
    /**
     * @param string $file
     * @return void
     */
    public function saveRecordedProxyedInformation($file)
    {
        $data = [];
        foreach ($this->grabProxiedRequests() as $key => $request) {
            $data[$key] = [
                'request' => $request,
                'response' => $this->proxiedResponses[$key]
            ];
        }
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    }
    /**
     * @return string
     */
    public function grabFakeApiUrl()
    {
        return str_replace('tcp://', 'http://', $this->socket->getAddress());
    }
    /**
     * @return void
     */
    public function grabFakeApiLoop()
    {
        return $this->fakeApiLoop;
    }
    /**
     * Disable FakeApi event loop to run.
     *
     * ```php
     * <?php
     * $I->disableFakeApiTick($content);
     * ```
     *
     * @return void
     */
    public function disableFakeApiTick()
    {
        $this->tickDisabled = true;
    }

    /**
     * Enable FakeApi event loop to run.
     *
     * ```php
     * <?php
     * $I->enableFakeApiTick($content);
     * ```
     *
     * @return void
     */
    public function enableFakeApiTick()
    {
        $this->tickDisabled = false;
    }

    /**
     * Stops FakeApi http server
     *
     * ```php
     * <?php
     * $I->stopFakeApi($content);
     * ```
     *
     * @return void
     */
    public function stopFakeApi()
    {
        $this->socket->close();
        MultiLoop::removeLoop('FakeApi');
        $this->_log('Stopping FakeApi');
        $this->hasDefinedResponses = false;
        $this->upstreamUrl = null;
        $this->expectedRequests = [];
        foreach ($this->currentMiddlewares as $middleware) {
            if ($middleware instanceof RequestExpectation) {
                $middleware->verify();
            }
        }
    }
    /**
     * @param int $count
     * @return RequestExpectation
     */
    public function expectApiCall($count)
    {
        $middleware = (new RequestExpectation())->setExpectedInvocationCount($count);
        $this->expectedRequests[] = $middleware;
        return $middleware;
    }
    /**
     * Helper function to debug log messages
     *
     * @param string $message
     * @return void
     */
    protected function _log($message)
    {
        $this->debugSection($this->_getName(), $message);
        if (!$this->doLog) {
            return;
        }
        echo "\n|{$this->_getName()}| $message";
    }
}
/*
*/