<?php

namespace Codeception\Module;

use Codeception\Lib\Middleware\AddRequest;
use Codeception\Lib\Middleware\ProxyRequest;
use Codeception\Lib\Middleware\RequestExpectation;
use Codeception\Lib\Middleware\RequestRecorder;
use Codeception\Util\Stub;
use LeeShan87\React\MultiLoop\MultiLoop;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Http\Message\Response;
use React\Http\Server;
use React\Promise\PromiseInterface;
use React\Socket\ServerInterface;
use RingCentral\Psr7\ServerRequest;
use RuntimeException;
use Throwable;

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
     *
     * @var AddRequest
     */
    protected $addRequestMiddleware;
    /**
     * Bind port of the Fake Api server
     *
     * @var int
     */
    protected $bind;
    /**
     * @var \React\Http\Server
     */
    protected $server;
    /**
     * @var \React\Socket\Server
     */
    protected $socket;
    /**
     * @var integer
     */
    protected $recordingStartTime;
    /**
     * @var integer
     */
    protected $recordInterval = 30;

    /**
     *
     * @var RequestExpectation[]
     */
    protected $expectedRequests = [];
    /**
     *
     * @var callable[]
     */
    protected $currentMiddlewares = [];
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

    /**
     * @var integer
     */
    protected $echoUpstreamStatusCode = 200;
    /**
     *
     * @var RequestRecorder
     */
    protected $requestRecorderMiddleware;
    /**
     *
     * @var ProxyRequest
     */
    protected $proxyMiddleware;
    /**
     * @return \React\Socket\Connection
     */
    protected $upstreamUrl;
    protected function createMockedConnection()
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
        return $mockedConnection;
    }
    /**
     * Resets the Fake Api to an initial state
     *
     * @return void
     */
    public function reset()
    {
        $this->addRequestMiddleware = new AddRequest();
        $this->requestRecorderMiddleware = new RequestRecorder();
        $this->upstreamUrl = !is_null($this->upstreamUrl) ? $this->upstreamUrl : $this->config['upstreamUrl'];
        $this->proxyMiddleware = new ProxyRequest($this->fakeApiLoop, $this->config['upstreamTimeout']);
        $this->bind = !is_null($this->bind) ? $this->bind : $this->config['bind'];
    }
    /**
     * Initialize FakeApi Server
     *
     *
     * @return void
     */
    public function initFakeServer()
    {
        $loop = \React\EventLoop\Factory::create();
        MultiLoop::addLoop($loop, 'FakeApi');
        $this->fakeApiLoop = $loop;
        $this->reset();
        $this->currentMiddlewares = $this->createMiddlewares();
        $server = new \React\Http\Server($loop, ...$this->currentMiddlewares);
        $socket = $this->createFakeApiSocket($this->bind, $loop);
        $server->listen($socket);
        $this->socket = $socket;
        $this->server = $server;
        $this->_log("FakeApi service created with url [{$this->grabFakeApiUrl()}]");
    }
    /**
     *
     * @param string $bind
     * @param LoopInterface $loop
     * @param integer $retry
     * @throws RuntimeException
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
            throw $e;
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
            return new Response($this->echoUpstreamStatusCode, ['Service-type' => 'Echo Service'], str($request));
        });
        $this->echoUpstreamServer = $server;
        $socket = $this->createFakeApiSocket($bind, $loop);
        $this->echoUpstreamSocket = $socket;
        $server->listen($socket);
        $this->_log("Echo service created with url [{$this->grabEchoServiceUrl()}]");
    }

    /**
     * @param integer $code
     * @return self
     */
    public function setEchoServiceStatusCode($code = 200)
    {
        $this->echoUpstreamStatusCode = $code;
        return $this;
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
     * @return void
     */
    public function disableEchoUpstreamLoop()
    {
        MultiLoop::removeLoop('EchoService');
    }

    /**
     * @return string
     */
    public function grabEchoServiceUrl()
    {
        return str_replace('tcp://', 'http://', !is_null($this->echoUpstreamSocket) ? $this->echoUpstreamSocket->getAddress() : '');
    }

    /**
     * @return array
     */
    protected function createMiddlewares()
    {
        $middlewares = [
            $this->requestRecorderMiddleware
        ];
        $middlewares = array_merge($middlewares, $this->getExpectedRequests());
        $this->proxyMiddleware->setUpstreamUrl($this->upstreamUrl);
        $middlewares[] = $this->proxyMiddleware;
        $middlewares[] = $this->addRequestMiddleware;
        $middlewares[] = function (ServerRequestInterface  $request) {
            return new Response(404);
        };
        return $middlewares;
    }
    public function getExpectedRequests()
    {
        return $this->expectedRequests;
    }
    /**
     *
     * @param integer $seconds
     * @return void
     */
    public function waitForSeconds($seconds)
    {
        MultiLoop::waitForSeconds($seconds);
    }
    public function waitTillFakeApiRecordingEnds()
    {
        while ($this->isRecording()) {
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
        $resolvedRequests = count($this->requestRecorderMiddleware->getRecordedRequests());
        while ($resolvedRequests === count($this->requestRecorderMiddleware->getRecordedRequests()) && time() < $maxExecutionTime) {
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
        $unresolved = $this->countUnresolvedRequest();
        if ($unresolved === 0) {
            return;
        }
        $loop = Factory::create();
        $loop->futureTick(function () use ($loop, $unresolved, $maxDelay) {
            $this->blockWaitAll($loop, $unresolved, $maxDelay);
        });
        $loop->run();
    }
    protected function blockWaitAll($loop, $unresolved, $maxDelay)
    {
        $this->_log("Block wait [$unresolved]");
        if ($unresolved-- === 0) {
            return;
        }
        $this->waitTillNextRequestResolves($maxDelay);
        $waitResolve = $this->countUnresolvedRequest();
        $this->_log("Waiting to resolve [$waitResolve]");
        if ($waitResolve === 0) {
            return;
        }
        $loop->futureTick(function () use ($loop, $unresolved, $maxDelay) {
            $this->blockWaitAll($loop, $unresolved, $maxDelay);
        });
    }
    protected function countUnresolvedRequest()
    {
        $count = 0;
        foreach ($this->currentMiddlewares as $middleware) {
            if ($middleware instanceof RequestExpectation) {
                if ($middleware->getInvocationCounter() < $middleware->getExpectedInvocationCount()) {
                    $count++;
                }
            }
        }
        return $count;
    }

    public static function getUrlString(ServerRequestInterface $serverRequest)
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
    public function addMessage($status = 200,  $headers = [], $content = "")
    {
        $this->addRequestMiddleware->addMessage($status, $headers, $content);
    }
    /**
     * @return void
     */
    public function flushAddedMessages()
    {
        $this->addRequestMiddleware->flushMessages();
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
        $requestId = md5(var_export(func_get_args(), true) . uniqid());
        $this->_log("RequestId is [$requestId]");
        MultiLoop::addLoop($loop, $requestId);
        $client = new \React\Http\Browser(new \React\Socket\Connector(
            array(
                'timeout' => $this->config['upstreamTimeout']
            )
        ), $loop);
        $this->_log('Sending request [' . "$method " . $this->grabFakeApiUrl() . $url . ']');
        return $client->request($method, $this->grabFakeApiUrl() . $url, $headers, $body)->then(function ($result) use ($requestId) {
            $this->_log("Removing requestId [$requestId]");
            MultiLoop::removeLoop($requestId);
            return $result;
        })->otherwise(function (\React\Http\Message\ResponseException $e) use ($requestId) {
            $this->_log("Removing requestId [$requestId]");
            MultiLoop::removeLoop($requestId);
            return reject($e);
        })->otherwise(function (Throwable $e) use ($requestId) {
            $this->_log($e->getMessage());
            $this->_log("Removing requestId [$requestId]");
            MultiLoop::removeLoop($requestId);
            return reject($e);
        });
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
        $mockedConnection = $this->createMockedConnection();
        $this->socket->emit('connection', [$mockedConnection]);
        $mockedConnection->emit('data', [str($serverRequest)]);
    }
    /**
     *
     * @param string $string
     * @return void
     */
    public function sendMockedRequestRaw($string)
    {
        $this->_log("Sending raw mocked request:\n"  . $string);
        $mockedConnection = $this->createMockedConnection();
        $this->socket->emit('connection', [$mockedConnection]);
        $mockedConnection->emit('data', [$string]);
    }
    /**
     *
     * @return Request
     */
    public function grabLastRequest()
    {
        return $this->requestRecorderMiddleware->getLastRequest();
    }
    /**
     *
     * @return Response
     */
    public function grabLastResponse()
    {
        return $this->requestRecorderMiddleware->getLastResponse();
    }
    /**
     * @return boolean
     */
    public function dontSeeFakeApiIsRecording()
    {
        Assert::assertFalse($this->isRecording());
    }
    public function seeFakeApiIsRecording()
    {
        Assert::assertTrue($this->isRecording());
    }
    protected function isRecording()
    {
        $time = time();
        $end =  ($this->recordingStartTime + $this->recordInterval);
        $isrecording = time() < ($this->recordingStartTime + $this->recordInterval);
        return time() < ($this->recordingStartTime + $this->recordInterval);
    }
    public function dontSeePredefinedMessages()
    {
        $this->assertFalse($this->hasMessage());
    }

    public function seePredefinedMessages()
    {
        $this->assertTrue($this->hasMessage());
    }

    /**
     * @return boolean
     */
    public function hasMessage()
    {
        return $this->addRequestMiddleware->hasMessage();
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
    public function setFakeApiBindPort($port = 8080)
    {
        $this->bind = $port;
    }
    public function grabFakeApiBindPort()
    {
        return $this->bind;
    }
    /**
     * @return void
     */
    public function flushRecordedRequests()
    {
        $this->requestRecorderMiddleware->flushRecordedRequests();
    }
    /**
     * @return array
     */
    public function grabRecordedResponses()
    {
        return $this->requestRecorderMiddleware->getRecordedResponses();
    }
    /**
     * @return array
     */
    public function grabRecordedRequests()
    {
        return $this->requestRecorderMiddleware->getRecordedRequests();
    }
    /**
     * @return array
     */
    public function grabProxiedResponses()
    {
        return $this->proxyMiddleware->getProxiedResponses();
    }
    /**
     * @return array
     */
    public function grabProxiedRequests()
    {
        return $this->proxyMiddleware->getProxiedRequests();
    }

    /**
     * @return void
     */
    public function flushRecordedProxyRequests()
    {
        $this->proxyMiddleware->flushProxiedRequests();
    }
    /**
     * @return string
     */
    public function grabFakeApiUrl()
    {
        return str_replace('tcp://', 'http://', !is_null($this->socket) ? $this->socket->getAddress() : '');
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
        if (!is_null($this->socket)) {
            $this->socket->close();
        }
        MultiLoop::removeLoop('FakeApi');
        $this->_log('Stopping FakeApi');
        $this->expectedRequests = [];
        $this->setUpstreamUrl(null);
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

    // Debugging helper functions
    public function enableLog()
    {
        $this->doLog = true;
    }
    public function disableLog()
    {
        $this->doLog = false;
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
        // @codeCoverageIgnoreStart

        echo "\n|{$this->_getName()}| $message";
    }
    // @codeCoverageIgnoreEnd

    /**
     * @param integer|null $sec
     * @return void
     */
    public function recordRequestsForSeconds($sec = null)
    {
        $this->recordingStartTime = time();
        $recordInterval = !is_null($sec) ? $sec : $this->config['recordInterval'];
        $this->recordInterval = $recordInterval;
        $this->_log("Recording for [$recordInterval]");
    }

    /**
     * @param string $file
     * @return void
     */
    public function saveRecordedInformation($file)
    {
        $data = [];
        $requests = $this->grabRecordedRequests();
        $responses = $this->grabRecordedResponses();
        foreach ($requests as $key => $request) {
            $data[$key] = [
                'request' => $request,
                'response' => $responses[$key]
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
        $proxyRequests = $this->grabProxiedRequests();
        $proxyResponses = $this->grabProxiedResponses();
        foreach ($proxyRequests as $key => $request) {
            $data[$key] = [
                'request' => $request,
                'response' => $proxyResponses[$key]
            ];
        }
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    }
}
