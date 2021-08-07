<?php

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
class FakeApiRequestMiddleware
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
     * @return int
     */
    public function getExpectedInvocationCount()
    {
        return $this->expectedInvocationCount;
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
                "Endpoint requirements [" . var_export($this->validationRules, true) . "]"
        );
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
                $doneDeferred = new Deferred();
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
    public function setNext(callable $next)
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
                throw new RuntimeException("Validator method [$validator] does not exists");
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
    protected function with(string $rule, $expected)
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
    public function willReturnResponse(int $status, array $headers, string $body)
    {
        return $this->willReturn(new Response($status, $headers, $body));
    }
    /**
     * @param integer $status
     * @param array $headers
     * @param array $body
     * @return self
     */
    public function willReturnJsonResponse(int $status, array $headers, array $body)
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
     * @return PromiseInterface
     */
    public function willGrabResponse()
    {
        $responseDeferred = new Deferred();
        $this->responseDeferred = $responseDeferred;
        $promise = $responseDeferred->promise();
        $this->responsePromise = $promise;
        return $promise;
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
    public function withUrl(string $string)
    {
        return $this->with('url', $string);
    }
    /**
     * @param ServerRequestInterface $requests
     * @param string $expected
     * @return boolean
     */
    protected function validateUrl(ServerRequestInterface $requests, string $expected)
    {
        $url = $requests->getUri()->getPath();
        return $expected === $url;
    }
    /**
     * @param string $parameter
     * @param string $value
     * @return self
     */
    public function withQueryParameter(string $parameter, string $value)
    {
        return $this->with('queryParameter', [$parameter, $value]);
    }
    /**
     * @param ServerRequestInterface $requests
     * @param array $expected
     * @return void
     */
    protected function validateQueryParameter(ServerRequestInterface $requests, array $expected)
    {
        $getParams = $requests->getQueryParams();
        list($parameter, $value) = $expected;
        return isset($getParams[$parameter]) && $getParams[$parameter] === $value;
    }
    /**
     * @param array $queryParameters
     * @return self
     */
    public function withQueryParameters(array $queryParameters)
    {
        return $this->with('queryParameters', $queryParameters);
    }
    /**
     * @param ServerRequestInterface $requests
     * @param array $expected
     * @return boolean
     */
    protected function validateQueryParameters(ServerRequestInterface $requests, array $expected)
    {
        $getParams = $requests->getQueryParams();
        return $expected === $getParams;
    }
    /**
     * @param string $header
     * @param string $value
     * @return self
     */
    public function withHeader(string $header, string $value)
    {
        return $this->with('header', [$header, $value]);
    }
    /**
     * @param ServerRequestInterface $requests
     * @param array $expected
     * @return boolean
     */
    protected function validateHeader(ServerRequestInterface $requests, array $expected)
    {
        list($headerName, $value) = $expected;
        $header = $requests->getHeader($headerName);
        return in_array($value, $header);
    }
    /**
     * @param array $headers
     * @return self
     */
    public function withHeaders(array $headers)
    {
        return $this->with('headers', (new ServerRequest('GET', '', $headers))->getHeaders());
    }
    /**
     * @param ServerRequestInterface $requests
     * @param array $expected
     * @return boolean
     */
    protected function validateHeaders(ServerRequestInterface $requests, array $expected)
    {
        $headers = $requests->getHeaders();
        return $expected === $headers;
    }
    /**
     * @param string $parameter
     * @param string $value
     * @return self
     */
    public function withBodyParameter(string $parameter, string $value)
    {
        return $this->with('bodyParameter', [$parameter, $value]);
    }
    /**
     * @param ServerRequestInterface $requests
     * @param array $expected
     * @return boolean
     */
    protected function validateBodyParameter(ServerRequestInterface $requests, array $expected)
    {
        list($parameter, $value) = $expected;
        $body = $requests->getParsedBody();
        return isset($body[$parameter]) && $body[$parameter] === $value;
    }
    /**
     * @param array $body
     * @return self
     */
    public function withBodyParameters(array $body)
    {
        return $this->with('bodyParameters', $body);
    }
    protected function validateBodyParameters(ServerRequestInterface $requests, array $expected)
    {
        return $expected === $requests->getParsedBody();
    }
    /**
     * @param string $parameter
     * @param string $value
     * @return self
     */
    public function withJsonBodyParameter(string $parameter, string $value)
    {
        return $this->with('jsonBodyParameter', [$parameter, $value]);
    }
    /**
     * @param ServerRequestInterface $requests
     * @param array $expected
     * @return boolean
     */
    protected function validateJsonBodyParameter(ServerRequestInterface $requests, array $expected)
    {
        list($parameter, $value) = $expected;
        $body = json_decode((string)$requests->getBody(), true);
        return isset($body[$parameter]) && $body[$parameter] === $value;
    }
    /**
     * @param array $body
     * @return self
     */
    public function withJsonBodyParameters(array $body)
    {
        return $this->with('jsonBodyParameters', $body);
    }
    /**
     * @param ServerRequestInterface $requests
     * @param array $expected
     * @return boolean
     */
    protected function validateJsonBodyParameters(ServerRequestInterface $requests, array $expected)
    {
        $body = json_decode((string)$requests->getBody(), true);
        return $expected === $body;
    }
    /**
     * @param string $method
     * @return self
     */
    public function withMethod(string $method)
    {
        return $this->with('method', $method);
    }
    /**
     * @param ServerRequestInterface $requests
     * @param string $expected
     * @return boolean
     */
    protected function validateMethod(ServerRequestInterface $requests, string $expected)
    {
        return strtolower($requests->getMethod()) === strtolower($expected);
    }

    /**
     * @param callable $callback
     * @return self
     */
    public function withCallback(callable $callback)
    {
        return $this->with('callback', $callback);
    }
    /**
     * @param ServerRequestInterface $requests
     * @param callable $expected
     * @return boolean
     */
    public function validateCallback(ServerRequestInterface $requests, callable $expected)
    {
        return $expected($requests);
    }
}
