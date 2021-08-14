<?php

namespace Codeception\Lib\Middleware;

use PHPUnit\Framework\Assert;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use RingCentral\Psr7\ServerRequest;

/**
 * This middleware can be used to make actual http calls testable with Codeception.
 *
 * PHPUnit expects feature is made for function invocation. With this class we can manually count the http endpoint invocation.
 * And if the counter is not at least equals the expected invocation counter, than the test should fail.
 */
class RequestExpectation
{
    /**
     * @var Response
     */
    protected $response;
    /**
     * @var array
     */
    protected $validationRules = [];
    /**
     * @var ServerRequestInterface
     */
    protected $request;
    /**
     * @var callable
     */
    protected $next;
    /**
     * @var Deferred
     */
    protected $responseDeferred;
    /**
     * @var boolean
     */
    protected $responseDeferredResolved = true;
    /**
     * @var PromiseInterface
     */
    protected $responsePromise;
    /**
     * @var integer
     */
    protected $invocationCounter = 0;
    /**
     * @var integer
     */
    protected $expectedInvocationCount = 0;
    protected $alteredResponses = [];
    public function __invoke(ServerRequestInterface  $request, $next)
    {
        $this->setRequest($request);
        $this->setNext($next);
        return $this->invoke($request, $next);
    }

    public function __toString()
    {
        return var_export($this->validationRules, true);
    }
    /**
     * @param int $count
     * @return self
     */
    public function setExpectedInvocationCount($count)
    {
        $this->expectedInvocationCount = $count;
        return $this;
    }
    /**
     * Verify if the middleware has invoked at least expected count.
     *
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @return void
     */
    public function verify()
    {
        Assert::assertGreaterThanOrEqual(
            $this->expectedInvocationCount,
            $this->invocationCounter,
            "Api endpoint was not called at least [{$this->expectedInvocationCount}] actual [{$this->invocationCounter}]\n" .
                "Endpoint requirements [{$this}]"
        );
    }
    /**
     *
     * @param integer $count
     * @return self
     */
    public function exactly($count)
    {
        $this->expectedInvocationCount = $count;
        $this->willAlterResponse()->then(function ($response) use (&$count) {
            if ($this->invocationCounter > $this->expectedInvocationCount) {
                Assert::fail(
                    "Api endpoint was called more than {$this->expectedInvocationCount}\n" .
                        "Endpoint requirements [{$this}]"
                );
            }
        });
        return $this;
    }
    /**
     * @return self
     */
    public function never()
    {
        return $this->exactly(0);
    }
    /**
     *
     * @return self
     */
    public function once()
    {
        return $this->exactly(1);
    }
    /**
     *
     * @return self
     */
    public function any()
    {
        $this->expectedInvocationCount = 0;
        return $this;
    }
    /**
     * @param int $count
     * @return self
     */
    public function atLeast($count)
    {
        $this->expectedInvocationCount = $count;
        return $this;
    }
    /**
     * @return self
     */
    public function atLeastOnce()
    {
        return $this->atLeast(1);
    }
    /**
     * @param ServerRequestInterface $request
     * @param callable $next
     * @return PromiseInterface|Response
     */
    public function invoke(ServerRequestInterface  $request, $next)
    {
        if ($this->validateRequest($request)) {
            $response = $this->getDefinedResponse();
            if (!empty($this->alteredResponses)) {
                $response = array_shift($this->alteredResponses);
            }
            $response = !is_null($response) ? $response : $next($request);
            if (!$this->responseDeferredResolved) {
                $this->responseDeferred->resolve($response);
                $this->responseDeferredResolved = true;
                return new Promise(function ($resolve, $reject) use ($request, $next) {
                    $resolve($this->invoke($request, $next));
                });
            }
            $this->invocationCounter++;
            return $response;
        }
        return $next($request);
    }
    /**
     * @param Response $response
     * @return self
     */
    public function addAlteredResponse(Response $response)
    {
        $this->alteredResponses[] = $response;
        $this->expectedInvocationCount++;
        return $this;
    }
    /**
     * The resolved promise will be an array[0=>ServerRequest,1=>Response,2=>PromiseInterface]
     *
     * You must resolve the last done promise to continue.
     *
     * @return PromiseInterface
     */
    public function willAlterResponse()
    {
        $this->responseDeferredResolved = false;
        $deferred = new Deferred();
        $this->responseDeferred = $deferred;
        return $deferred->promise();
    }

    /**
     * @param ServerRequestInterface $request
     * @return self
     */
    public function setRequest(ServerRequestInterface  $request)
    {
        $this->request = $request;
        return $this;
    }
    /**
     * @param callable $next
     * @return self
     */
    public function setNext($next)
    {
        $this->next = $next;
        return $this;
    }
    /**
     * Validates that the middleware is looking for the given request.
     *
     * This method will run through the all given validation rules and than it should decide if this request should be handled.
     *
     * @param ServerRequestInterface $request
     * @return boolean
     */
    protected function validateRequest(ServerRequestInterface $request)
    {
        if (empty($this->validationRules)) {
            return false;
        }
        foreach ($this->validationRules as $method => $values) {
            $validator = 'validate' . ucfirst($method);
            if (!method_exists($this, $validator)) {
                // @codeCoverageIgnoreStart
                throw new RuntimeException("Validator method [$validator] does not exists");
                // @codeCoverageIgnoreEnd
            }
            foreach ($values as $value) {
                if (!$this->$validator($request, $value)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @param string $rule
     * @param mixed $expected
     * @return self
     */
    protected function with($rule, $expected)
    {
        if (!isset($this->validationRules[$rule])) {
            $this->validationRules[$rule] = [];
        }
        $this->_log("With [$rule] rule with value [" . var_export($expected, true) . "]");
        $this->validationRules[$rule][] = $expected;
        return $this;
    }

    /**
     * @param Response $response
     * @return self
     */
    public function willReturn(Response $response)
    {
        $this->response = $response;
        return $this;
    }
    /**
     * @param integer $status
     * @param array $headers
     * @param string $body
     * @return self
     */
    public function willReturnResponse($status = 200,  $headers = [],  $body = '')
    {
        return $this->willReturn(new Response($status, $headers, $body));
    }
    /**
     * @param integer $status
     * @param array $headers
     * @param array $body
     * @return self
     */
    public function willReturnJsonResponse($status = 200,  $headers = [],  $body = [])
    {
        $jsonBody = json_encode($body);

        $respones = new Response(
            $status,
            array_merge(
                [
                    'content-type' => 'application/json; charset=utf-8',
                    'content-length' => strlen($jsonBody)
                ],
                $headers
            ),
            $jsonBody
        );
        return $this->willReturn($respones);
    }
    /**
     * @return Response
     */
    public function getDefinedResponse()
    {
        return $this->response;
    }

    /**
     * Helper function to debug log messages
     *
     * @param string $message
     * @return void
     */
    protected function _log($message)
    {
        if (function_exists('codecept_debug')) {
            codecept_debug('[FakeApiMiddleware] ' . $message);
        }
    }
    /**
     * @param string $string
     * @return self
     */
    public function withUrl($string)
    {
        return $this->with('url', $string);
    }
    /**
     * @param ServerRequestInterface $requests
     * @param string $expected
     * @return boolean
     */
    protected function validateUrl(ServerRequestInterface $requests,  $expected)
    {
        $url = $requests->getUri()->getPath();
        return $expected === $url;
    }
    /**
     * @param string $parameter
     * @param string $value
     * @return self
     */
    public function withQueryParameter($parameter,  $value)
    {
        return $this->with('queryParameter', [$parameter, $value]);
    }
    /**
     * @param ServerRequestInterface $requests
     * @param array $expected
     * @return void
     */
    protected function validateQueryParameter(ServerRequestInterface $requests,  $expected)
    {
        $getParams = $requests->getQueryParams();
        list($parameter, $value) = $expected;
        return isset($getParams[$parameter]) && $getParams[$parameter] === $value;
    }
    /**
     * @param array $queryParameters
     * @return self
     */
    public function withQueryParameters($queryParameters)
    {
        return $this->with('queryParameters', $queryParameters);
    }
    /**
     * @param ServerRequestInterface $requests
     * @param array $expected
     * @return boolean
     */
    protected function validateQueryParameters(ServerRequestInterface $requests,  $expected)
    {
        $getParams = $requests->getQueryParams();
        return $expected === $getParams;
    }
    /**
     * @param string $header
     * @param string $value
     * @return self
     */
    public function withHeader($header,  $value)
    {
        return $this->with('header', [$header, $value]);
    }
    /**
     * @param ServerRequestInterface $requests
     * @param array $expected
     * @return boolean
     */
    protected function validateHeader(ServerRequestInterface $requests,  $expected)
    {
        list($headerName, $value) = $expected;
        $header = $requests->getHeader($headerName);
        return in_array($value, $header);
    }
    /**
     * @param array $headers
     * @return self
     */
    public function withHeaders($headers)
    {
        return $this->with('headers', (new ServerRequest('GET', '', $headers))->getHeaders());
    }
    /**
     * @param ServerRequestInterface $requests
     * @param array $expected
     * @return boolean
     */
    protected function validateHeaders(ServerRequestInterface $requests,  $expected)
    {
        $headers = $requests->getHeaders();
        return $expected === $headers;
    }
    /**
     * @param string $parameter
     * @param string $value
     * @return self
     */
    public function withCookie($parameter, $value)
    {
        return $this->with('cookie', [$parameter, $value]);
    }
    /**
     * @param ServerRequestInterface $requests
     * @param array $expected
     * @return boolean
     */
    protected function validateCookie(ServerRequestInterface $requests,  $expected)
    {
        list($name, $value) = $expected;
        $cookies = $requests->getCookieParams();
        return isset($cookies[$name]) && $cookies[$name] === $value;
    }
    /**
     * @param array $cookies
     * @return self
     */
    public function withCookies($cookies)
    {
        return $this->with('cookies', $cookies);
    }
    /**
     * @param ServerRequestInterface $requests
     * @param array $expected
     * @return boolean
     */
    protected function validateCookies(ServerRequestInterface $requests,  $expected)
    {
        $cookies = $requests->getCookieParams();
        return  $cookies === $expected;
    }

    /**
     * @param string $parameter
     * @param string $value
     * @return self
     */
    public function withBodyParameter($parameter,  $value)
    {
        return $this->with('bodyParameter', [$parameter, $value]);
    }
    /**
     * @param ServerRequestInterface $requests
     * @param array $expected
     * @return boolean
     */
    protected function validateBodyParameter(ServerRequestInterface $requests,  $expected)
    {
        list($parameter, $value) = $expected;
        $body = $requests->getParsedBody();
        return isset($body[$parameter]) && $body[$parameter] === $value;
    }
    /**
     * @param array $body
     * @return self
     */
    public function withBodyParameters($body)
    {
        return $this->with('bodyParameters', $body);
    }
    protected function validateBodyParameters(ServerRequestInterface $requests,  $expected)
    {
        return $expected === $requests->getParsedBody();
    }
    /**
     * @param string $parameter
     * @param string $value
     * @return self
     */
    public function withJsonBodyParameter($parameter,  $value)
    {
        return $this->with('jsonBodyParameter', [$parameter, $value]);
    }
    /**
     * @param ServerRequestInterface $requests
     * @param array $expected
     * @return boolean
     */
    protected function validateJsonBodyParameter(ServerRequestInterface $requests,  $expected)
    {
        list($parameter, $value) = $expected;
        $body = json_decode((string)$requests->getBody(), true);
        return isset($body[$parameter]) && $body[$parameter] === $value;
    }
    /**
     * @param array $body
     * @return self
     */
    public function withJsonBodyParameters($body)
    {
        return $this->with('jsonBodyParameters', $body);
    }
    /**
     * @param ServerRequestInterface $requests
     * @param array $expected
     * @return boolean
     */
    protected function validateJsonBodyParameters(ServerRequestInterface $requests,  $expected)
    {
        $body = json_decode((string)$requests->getBody(), true);
        return $expected === $body;
    }
    /**
     * @param string $method
     * @return self
     */
    public function withMethod($method)
    {
        return $this->with('method', $method);
    }
    /**
     * @param ServerRequestInterface $requests
     * @param string $expected
     * @return boolean
     */
    protected function validateMethod(ServerRequestInterface $requests,  $expected)
    {
        return strtolower($requests->getMethod()) === strtolower($expected);
    }

    /**
     * @param callable $callback
     * @return self
     */
    public function withCallback($callback)
    {
        return $this->with('callback', $callback);
    }
    /**
     * @param ServerRequestInterface $requests
     * @param callable $expected
     * @return boolean
     */
    public function validateCallback(ServerRequestInterface $requests,  $expected)
    {
        return $expected($requests);
    }

    /**
     * @return int
     */
    public function getExpectedInvocationCount()
    {
        return $this->expectedInvocationCount;
    }
    public function getInvocationCounter()
    {
        return $this->invocationCounter;
    }
    // @codeCoverageIgnoreStart
    /**
     * @return ServerRequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }
    /**
     * @return Deferred
     */
    public function getResponseDeferred()
    {
        return $this->responseDeferred;
    }
    /**
     * @return PromiseInterface
     */
    public function getResponsePromise()
    {
        return $this->responsePromise;
    }
    // @codeCoverageIgnoreEnd
}
