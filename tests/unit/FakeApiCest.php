<?php

use Codeception\Module\ReactHelper;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\Deferred;
use RingCentral\Psr7\ServerRequest;

use function RingCentral\Psr7\str;

class FakeApiCest
{
    public function _before(ServiceGuy $I)
    {
    }
    public function _failed(ServiceGuy $I)
    {
        try {
            $I->stopFakeApi();
        } catch (Exception $e) {
        }
    }
    // helper functions
    public function _validateRequest(ServiceGuy $I, ?ServerRequest $request = null)
    {
        $I->stopFakeApi();
        $grabbedRequest = $I->grabLastRequest();
        $grabbedResponse = $I->grabLastResponse();
        $I->assertNotNull($grabbedRequest);
        $I->assertNotNull($grabbedResponse);
        if ($grabbedResponse instanceof Response) {
            $I->assertEquals(404, $grabbedResponse->getStatusCode());
        }
        if (!$grabbedResponse instanceof Response) {
            $I->assertInstanceOf(\Exception::class, $grabbedResponse);
        }
        if (!is_null($request)) {
            $I->assertEquals($request->getMethod(), $grabbedRequest->getMethod());
            $I->assertEquals($request->getUri()->getHost(), $grabbedRequest->getUri()->getHost());
        }
    }
    public function _validateRequestWithResponse(ServiceGuy $I, ?ServerRequest $request = null, ?Response $response = null)
    {
        try {
            $I->stopFakeApi();
        } catch (Exception $e) {
            $grabbedRequest = $I->grabLastRequest();
            $grabbedResponse = $I->grabLastResponse();
            codecept_debug("Request:\n" . str($grabbedRequest));
            codecept_debug("Response:\n" . str($grabbedResponse));
        }
        $grabbedRequest = $I->grabLastRequest();
        $grabbedResponse = $I->grabLastResponse();
        $I->assertNotNull($grabbedRequest);
        $I->assertNotNull($grabbedResponse);
        if (!is_null($request)) {
            $I->assertEquals($request->getMethod(), $grabbedRequest->getMethod());
            $I->assertEquals($request->getUri()->getHost(), $grabbedRequest->getUri()->getHost());
        }
        if (!is_null($response)) {
            $I->assertEquals($response->getStatusCode(), $grabbedResponse->getStatusCode());
        }
    }
    public function _validateRequestExpectationFailed(ServiceGuy $I, ?ServerRequest $request = null)
    {
        try {
            $I->stopFakeApi();
            $grabbedRequest = $I->grabLastRequest();
            $grabbedResponse = $I->grabLastResponse();
            codecept_debug("Request:\n" . str($grabbedRequest));
            codecept_debug("Response:\n" . str($grabbedResponse));
            $I->fail("Exception should happened");
        } catch (\PHPUnit\Framework\ExpectationFailedException $e) {
            $I->assertStringContainsString('Api endpoint was not called at least', $e->getMessage());
        }
        $grabbedRequest = $I->grabLastRequest();
        $grabbedResponse = $I->grabLastResponse();
        $I->assertNotNull($grabbedRequest);
        $I->assertNotNull($grabbedResponse);
        $I->assertEquals(404, $grabbedResponse->getStatusCode());
        if (!is_null($request)) {
            $I->assertEquals($request->getMethod(), $grabbedRequest->getMethod());
            $I->assertEquals($request->getUri()->getHost(), $grabbedRequest->getUri()->getHost());
        }
    }
    // tests
    public function sendMockedRequestNoFeatureEnabled(ServiceGuy $I)
    {
        $I->wantTo('Send Request when no feature enabled');
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $request = new ServerRequest('POST', 'http://example.com');
        $I->sendMockedRequest($request);
        $this->_validateRequest($I, $request);
        $I->assertEmpty($I->grabProxiedRequests());
        $I->assertEmpty($I->grabProxiedResponses());
    }
    // tests
    public function sendMockedRequestWhenUpstreamEnabledShouldTimeout(ServiceGuy $I)
    {
        $I->wantTo('Send Request when upstream enabled');
        $I->setUpstreamUrl('http://127.0.0.1:33333');
        //$I->setUpstreamUrl('http://example.com');
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $request = new ServerRequest('POST', 'http://example.com/safasasdfas');
        $I->sendMockedRequest($request);
        $I->waitTillNextRequestResolves(20);
        $this->_validateRequest($I, $request);
        $I->assertNotEmpty($I->grabProxiedRequests());
        $I->assertNotEmpty($I->grabProxiedResponses());
    }

    public function expectsUriWithoutResponse(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting a url without defined response');
        $request = new ServerRequest('POST', 'http://example.com/some/url');
        $expectedRequest = $I->expectApiCall(1)->withUrl('/some/url');
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $this->_validateRequest($I, $request);
        $expectedUrl = $I->getUrlString($request);
        $grabbedRequest = $I->grabLastRequest();
        $grabbedUrl = $I->getUrlString($grabbedRequest);
        $I->assertEquals($expectedUrl, $grabbedUrl);
        $I->assertEmpty($I->grabProxiedRequests());
        $I->assertEmpty($I->grabProxiedResponses());
    }

    public function expectsMultipleRequestUriWithoutResponse(ServiceGuy $I)
    {
        $I->wantTo('Send multiple Request when expecting a url without defined response');
        $request = new ServerRequest('POST', 'http://example.com/some/url');
        $expectedRequest = $I->expectApiCall(2)->withUrl('/some/url');
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $I->sendMockedRequest($request);
        $this->_validateRequest($I, $request);
        $expectedUrl = $I->getUrlString($request);
        $grabbedRequest = $I->grabLastRequest();
        $grabbedUrl = $I->getUrlString($grabbedRequest);
        $I->assertEquals($expectedUrl, $grabbedUrl);
        $I->assertEmpty($I->grabProxiedRequests());
        $I->assertEmpty($I->grabProxiedResponses());
    }

    public function expectsMultipleRequestUriWithoutResponseShouldFail(ServiceGuy $I)
    {
        $I->wantTo('Send multiple Request when expecting a url without defined response should fail');
        $request = new ServerRequest('POST', 'http://example.com/some/url');
        $expectedRequest = $I->expectApiCall(2)->withUrl('/some/url');
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $this->_validateRequestExpectationFailed($I, $request);
    }

    public function expectsUriWithResponse(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting a url');
        $request = new ServerRequest('POST', 'http://example.com/some/url');
        $expectedRequest = $I->expectApiCall(1)->withUrl('/some/url');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $this->_validateRequestWithResponse($I, $request, $expectedResponse);
        $expectedUrl = $I->getUrlString($request);
        $grabbedRequest = $I->grabLastRequest();
        $grabbedUrl = $I->getUrlString($grabbedRequest);
        $I->assertEquals($expectedUrl, $grabbedUrl);
    }
    public function expectUriShouldFail(ServiceGuy $I)
    {
        $I->wantTo('Send Request with url expecting test should fail');
        $request = new ServerRequest('POST', 'http://example.com/some/url');
        $I->expectApiCall(1)->withUrl('/some/other/url');
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $this->_validateRequestExpectationFailed($I, $request);
    }

    public function expectHeader(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting a header');
        $request = new ServerRequest('POST', 'http://example.com', [
            'foo' => 'bar'
        ]);
        $expectedRequest = $I->expectApiCall(1)->withHeader('foo', 'bar');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $grabbedRequest = $I->grabLastRequest();
        $this->_validateRequestWithResponse($I, $request, $expectedResponse);
        $I->assertEquals($request->getMethod(), $grabbedRequest->getMethod());
        $I->assertEquals($request->getUri()->getHost(), $grabbedRequest->getUri()->getHost());
    }
    public function expectHeaderShouldFail(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting a header should fail');
        $request = new ServerRequest('POST', 'http://example.com', [
            'foo' => 'bar'
        ]);
        $expectedRequest = $I->expectApiCall(1)->withHeader('baz', 'bar');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $this->_validateRequestExpectationFailed($I, $request);
    }

    public function expectMultipleHeaders(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting multiple headers');
        $request = new ServerRequest('POST', 'http://example.com', [
            'foo' => 'bar',
            'bar' => 'foo'
        ]);
        $expectedRequest = $I->expectApiCall(1)->withHeader('foo', 'bar')->withHeader('bar', 'foo');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $grabbedRequest = $I->grabLastRequest();
        $this->_validateRequestWithResponse($I, $request, $expectedResponse);
        $I->assertEquals($request->getMethod(), $grabbedRequest->getMethod());
        $I->assertEquals($request->getUri()->getHost(), $grabbedRequest->getUri()->getHost());
    }
    public function expectMultipleHeadersShouldFail(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting multiple headers should fail');
        $request = new ServerRequest('POST', 'http://example.com', [
            'foo' => 'bar',
        ]);
        $expectedRequest = $I->expectApiCall(1)->withHeader('foo', 'bar')->withHeader('bar', 'foo');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $this->_validateRequestExpectationFailed($I, $request);
    }
    public function expectHeaders(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting headers');
        $headers = [
            'Host' => 'example.com',
            'foo' => 'bar',
            'bar' => 'foo'
        ];
        $request = new ServerRequest('POST', 'http://example.com', $headers);
        $expectedRequest = $I->expectApiCall(1)->withHeaders($headers);
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $I->stopFakeApi();
        $this->_validateRequestWithResponse($I, $request, $expectedResponse);
    }

    public function expectHeadersShouldFail(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting headers should fail');
        $headers = [
            'Host' => 'example.com',
            'foo' => 'bar',
            'bar' => 'foo'
        ];
        $request = new ServerRequest('POST', 'http://example.com', $headers);
        $headers['baz'] = 'bar';
        $expectedRequest = $I->expectApiCall(1)->withHeaders($headers);
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $this->_validateRequestExpectationFailed($I, $request);
    }

    public function expectQueryParameter(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting a get parameter');
        $request = new ServerRequest('POST', 'http://example.com?foo=bar',);
        $expectedRequest = $I->expectApiCall(1)->withQueryParameter('foo', 'bar');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $this->_validateRequestWithResponse($I, $request, $expectedResponse);
    }
    public function expectMultipleQueryParameter(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting multiple get parameters');
        $request = new ServerRequest('POST', 'http://example.com?foo=bar&bar=foo',);
        $expectedRequest = $I->expectApiCall(1)->withQueryParameter('foo', 'bar')->withQueryParameter('bar', 'foo');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $this->_validateRequestWithResponse($I, $request, $expectedResponse);
    }
    public function expectMultipleQueryParameterShouldFail(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting multiple get parameters');
        $request = new ServerRequest('POST', 'http://example.com?foo=bar&bar=foo',);
        $expectedRequest = $I->expectApiCall(1)->withQueryParameter('foo', 'bar')->withQueryParameter('baz', 'foo');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $this->_validateRequestExpectationFailed($I, $request);
    }
    public function expectQueryParameterShouldFail(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting a get parameter should fail');
        $request = new ServerRequest('POST', 'http://example.com?foo=bar',);
        $expectedRequest = $I->expectApiCall(1)->withQueryParameter('baz', 'bar');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $this->_validateRequestExpectationFailed($I, $request);
    }
    public function expectMethod(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting a given http method');
        $request = new ServerRequest('POST', 'http://example.com',);
        $expectedRequest = $I->expectApiCall(1)->withMethod('POST');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $this->_validateRequestWithResponse($I, $request, $expectedResponse);
    }
    public function expectMethodShouldFail(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting a given http method should fail');
        $request = new ServerRequest('GET', 'http://example.com',);
        $expectedRequest = $I->expectApiCall(1)->withMethod('POST');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $this->_validateRequestExpectationFailed($I, $request);
    }
    public function expectQueryParameters(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting a get parameters');
        $request = new ServerRequest('POST', 'http://example.com?foo=bar&bar=foo',);
        $expectedRequest = $I->expectApiCall(1)->withQueryParameters(['foo' => 'bar', 'bar' => 'foo']);
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $I->sendMockedRequest($request);
        $this->_validateRequestWithResponse($I, $request, $expectedResponse);
    }
    public function expectQueryParametersShouldFail(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting a get parameters should fail');
        $request = new ServerRequest('POST', 'http://example.com?foo=bar&bar=foo',);
        $expectedRequest = $I->expectApiCall(1)->withQueryParameters(['foo' => 'bar', 'bar' => 'foo', 'baz' => 'bar']);
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $I->sendMockedRequest($request);
        $this->_validateRequestExpectationFailed($I, $request);
    }

    public function expectCallback(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting validation with callback');
        $request = new ServerRequest('POST', 'http://example.com?foo=bar&bar=foo',);
        $expectedRequest = $I->expectApiCall(1)->withCallback(function (ServerRequestInterface $request) {
            $parameter = 'foo';
            $value = 'bar';
            $getParams = $request->getQueryParams();
            return isset($getParams[$parameter]) && $getParams[$parameter] === $value;
        });
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $I->sendMockedRequest($request);
        $this->_validateRequestWithResponse($I, $request, $expectedResponse);
    }

    public function expectCallbackShouldFail(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting validation with callback should fail');
        $request = new ServerRequest('POST', 'http://example.com?baz=bar',);
        $expectedRequest = $I->expectApiCall(1)->withCallback(function (ServerRequestInterface $request) {
            $parameter = 'foo';
            $value = 'bar';
            $getParams = $request->getQueryParams();
            return isset($getParams[$parameter]) && $getParams[$parameter] === $value;
        });
        $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $I->sendMockedRequest($request);
        $this->_validateRequestExpectationFailed($I, $request);
    }
    public function expectBodyParameter(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting a body parameter');
        $dataArray = [
            'foo' => 'bar'
        ];
        $bodyString = http_build_query($dataArray);
        $bodyLenght = strlen($bodyString);
        $expectedRequest = $I->expectApiCall(1)->withBodyParameter('foo', 'bar');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $I->sendMockedRequestRaw("POST / HTTP/1.0\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: $bodyLenght\r\n\r\n$bodyString");
        $I->stopFakeApi();
    }
    public function expectBodyParameterShouldFail(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting a body parameter should fail');
        $dataArray = [
            'foo' => 'bar'
        ];
        $bodyString = http_build_query($dataArray);
        $bodyLenght = strlen($bodyString);
        $expectedRequest = $I->expectApiCall(1)->withBodyParameter('baz', 'bar');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $I->sendMockedRequestRaw("POST / HTTP/1.0\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: $bodyLenght\r\n\r\n$bodyString");
        $this->_validateRequestExpectationFailed($I);
    }

    public function expectMultpileBodyParameter(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting multiple body parameters');
        $dataArray = [
            'foo' => 'bar',
            'bar' => 'foo'
        ];
        $bodyString = http_build_query($dataArray);
        $bodyLenght = strlen($bodyString);
        $expectedRequest = $I->expectApiCall(1)->withBodyParameter('foo', 'bar')->withBodyParameter('bar', 'foo');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $I->sendMockedRequestRaw("POST / HTTP/1.0\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: $bodyLenght\r\n\r\n$bodyString");
        $I->stopFakeApi();
    }
    public function expectMultipleBodyParameterShouldFail(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting multiple body parameters should fail');
        $dataArray = [
            'foo' => 'bar',
            'bar' => 'foo'
        ];
        $bodyString = http_build_query($dataArray);
        $bodyLenght = strlen($bodyString);
        $expectedRequest = $I->expectApiCall(1)->withBodyParameter('foo', 'bar')->withBodyParameter('baz', 'bar');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $I->sendMockedRequestRaw("POST / HTTP/1.0\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: $bodyLenght\r\n\r\n$bodyString");
        $this->_validateRequestExpectationFailed($I);
    }
    public function expectBodyParameters(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting a body parameters');
        $dataArray = [
            'foo' => 'bar',
            'bar' => 'foo'
        ];
        $bodyString = http_build_query($dataArray);
        $bodyLenght = strlen($bodyString);
        $expectedRequest = $I->expectApiCall(1)->withBodyParameters($dataArray);
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $I->sendMockedRequestRaw("POST / HTTP/1.0\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: $bodyLenght\r\n\r\n$bodyString");
        $I->stopFakeApi();
    }
    public function expectBodyParametersShouldFail(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting a body parameters should fail');
        $dataArray = [
            'foo' => 'bar',
            'bar' => 'foo'
        ];
        $bodyString = http_build_query($dataArray);
        $bodyLenght = strlen($bodyString);
        $dataArray['baz'] = 'bar';
        $expectedRequest = $I->expectApiCall(1)->withBodyParameters($dataArray);
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $I->sendMockedRequestRaw("POST / HTTP/1.0\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: $bodyLenght\r\n\r\n$bodyString");
        $this->_validateRequestExpectationFailed($I);
    }
    public function expectJsonBodyParameter(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting a json body parameter');
        $dataArray = [
            'foo' => 'bar'
        ];
        $bodyString = json_encode($dataArray);
        $bodyLenght = strlen($bodyString);
        $expectedRequest = $I->expectApiCall(1)->withJsonBodyParameter('foo', 'bar');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $I->sendMockedRequestRaw("POST / HTTP/1.0\r\nContent-Type: application/json\r\nContent-Length: $bodyLenght\r\n\r\n$bodyString");
        //$this->_validateRequest($I);
        $I->stopFakeApi();
    }
    public function expectJsonBodyParameterShouldFail(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting a json body parameter should fail');
        $dataArray = [
            'foo' => 'bar'
        ];
        $bodyString = json_encode($dataArray);
        $bodyLenght = strlen($bodyString);
        $expectedRequest = $I->expectApiCall(1)->withJsonBodyParameter('baz', 'bar');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $I->sendMockedRequestRaw("POST / HTTP/1.0\r\nContent-Type: application/json\r\nContent-Length: $bodyLenght\r\n\r\n$bodyString");
        //$this->_validateRequest($I);
        $this->_validateRequestExpectationFailed($I);
    }
    public function expectMultipleJsonBodyParameter(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting multiple json body parameters');
        $dataArray = [
            'foo' => 'bar',
            'bar' => 'foo'
        ];
        $bodyString = json_encode($dataArray);
        $bodyLenght = strlen($bodyString);
        $expectedRequest = $I->expectApiCall(1)->withJsonBodyParameter('foo', 'bar')->withJsonBodyParameter('bar', 'foo');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $I->sendMockedRequestRaw("POST / HTTP/1.0\r\nContent-Type: application/json\r\nContent-Length: $bodyLenght\r\n\r\n$bodyString");
        //$this->_validateRequest($I);
        $I->stopFakeApi();
    }
    public function expectMultipleJsonBodyParameterShouldFail(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting multiple json body parameter should fail');
        $dataArray = [
            'foo' => 'bar'
        ];
        $bodyString = json_encode($dataArray);
        $bodyLenght = strlen($bodyString);
        $expectedRequest = $I->expectApiCall(1)->withJsonBodyParameter('foo', 'bar')->withJsonBodyParameter('bar', 'foo');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $I->sendMockedRequestRaw("POST / HTTP/1.0\r\nContent-Type: application/json\r\nContent-Length: $bodyLenght\r\n\r\n$bodyString");
        //$this->_validateRequest($I);
        $this->_validateRequestExpectationFailed($I);
    }
    public function expectJsonBodyParameters(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting a json body parameters');
        $dataArray = [
            'foo' => 'bar',
            'bar' => 'foo'
        ];
        $bodyString = json_encode($dataArray);
        $bodyLenght = strlen($bodyString);
        $expectedRequest = $I->expectApiCall(1)->withJsonBodyParameters($dataArray);
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $I->sendMockedRequestRaw("POST / HTTP/1.0\r\nContent-Type: application/json\r\nContent-Length: $bodyLenght\r\n\r\n$bodyString");
        //$this->_validateRequest($I);
        $I->stopFakeApi();
    }
    public function expectJsonBodyParametersShouldFail(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting a json body parameters should fail');
        $dataArray = [
            'foo' => 'bar',
            'bar' => 'foo'
        ];
        $bodyString = json_encode($dataArray);
        $bodyLenght = strlen($bodyString);
        $dataArray['baz'] = 'bar';
        $expectedRequest = $I->expectApiCall(1)->withJsonBodyParameters($dataArray);
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $I->sendMockedRequestRaw("POST / HTTP/1.0\r\nContent-Type: application/json\r\nContent-Length: $bodyLenght\r\n\r\n$bodyString");
        //$this->_validateRequest($I);
        $this->_validateRequestExpectationFailed($I);
    }

    //proxy tests
    public function startEchoService(ServiceGuy $I)
    {
        $I->wantTo('Start Echo service');
        xdebug_break();
        $I->createEchoUpstream(8081);
        $echoUrl = $I->grabEchoServiceUrl();

        $I->setUpstreamUrl($echoUrl);
        $I->initFakeServer();
        $echoHost = preg_replace("#(http|https)://#", '', $echoUrl);
        $fakeApiHost = preg_replace("#(http|https)://#", '', $I->grabFakeApiUrl());
        $I->sendJsonRequest('POST', '/v2/authentication/login/license', [], ['licenseKey' => 'some_license']);
        $I->waitTillNextRequestResolves();
        $response = $I->grabLastResponse();

        $expectedResponse = <<<Response
POST /v2/authentication/login/license HTTP/1.1\r
Host: 127.0.0.1:8081\r
User-Agent: ReactPHP/1\r
Connection: close\r
Content-Type: application/json\r
Content-Length: 29\r
\r
{"licenseKey":"some_license"}
Response;
        $I->stopEchoUpstream();
        $I->stopFakeApi();
        $grabbedRequest = $I->grabLastRequest();
        $I->assertEquals($expectedResponse, (string)$response->getBody(), var_export($response, true));
        $echoResponse = (string)$response->getBody();
        $I->assertStringContainsString("Host: $echoHost\r\n", $echoResponse);
        $I->assertStringNotContainsString("Host: $fakeApiHost\r\n", $echoResponse);
        $I->assertStringContainsString('/v2/authentication/login/license', $echoResponse);
        $expected = preg_replace("#Host: .*\r\n#", '', str($grabbedRequest));
        $actual = preg_replace("#Host: .*\r\n#", '', $echoResponse);
        $I->assertEquals($expected, $actual, str($grabbedRequest));
    }

    // Response altering tests
    public function testAddAlteredResponse(ServiceGuy $I)
    {
        $I->wantTo('Adding altered responses before a request arrive');
        $middleware = $I->expectApiCall(0)->withUrl('/hello');
        // Responses are cloned inside the object. We must prepare the altered response before adding it.
        $response = (new Response(220, []))
            ->withAddedHeader('message', 'hello');
        $middleware->addAlteredResponse($response);
        $response = (new Response(220, []))
            ->withAddedHeader('message', 'world');
        $middleware->addAlteredResponse($response);
        $I->initFakeServer();
        $I->sendRequest();
        $I->waitTillNextRequestResolves();
        $response = $I->grabLastResponse();
        $I->assertNotNull($response);
        $I->assertEquals(404, $response->getStatusCode());
        $I->sendRequest('GET', '/hello');
        $I->waitTillNextRequestResolves();
        $response = $I->grabLastResponse();
        $I->assertEquals(220, $response->getStatusCode());
        $I->assertEquals(['hello'], $response->getHeader('message'), var_export($response->getHeaders(), true));
        $I->sendRequest('GET', '/hello');
        $I->waitTillNextRequestResolves();
        $response = $I->grabLastResponse();
        $I->assertEquals(220, $response->getStatusCode());
        $I->assertEquals(['world'], $response->getHeader('message'));
        $I->sendRequest('GET', '/hello');
        $I->waitTillNextRequestResolves();
        $response = $I->grabLastResponse();
        $I->assertEquals(404, $response->getStatusCode());
        /** @var Response $response */
        $I->assertEquals([], $response->getHeader('message'));
        $I->stopFakeApi();
    }

    public function testWillAlteredResponse(ServiceGuy $I)
    {
        $I->wantTo('Will adding altered responses when a request arrive');
        $middleware = $I->expectApiCall(0)->withUrl('/hello');
        $middleware->willAlterResponse()->then(function ($response) use ($I, $middleware) {
            $alteredResponse = $response->withStatus(220)->withAddedHeader('message', 'hello');
            $middleware->addAlteredResponse($alteredResponse);

            $alteredResponse = $response->withStatus(220)->withAddedHeader('message', 'world');
            $middleware->addAlteredResponse($alteredResponse);
            $I->assertNotNull($response);
            $I->assertEquals(404, $response->getStatusCode());
        });
        // Responses are cloned inside the object. We must prepare the altered response before adding it.
        $I->initFakeServer();
        $I->sendRequest();
        $I->waitTillNextRequestResolves();
        $response = $I->grabLastResponse();
        $I->assertNotNull($response);
        $I->assertEquals(404, $response->getStatusCode());

        $I->sendRequest('GET', '/hello');
        $I->waitTillNextRequestResolves();
        $response = $I->grabLastResponse();
        $I->assertEquals(220, $response->getStatusCode());
        $I->assertEquals(['hello'], $response->getHeader('message'));

        $I->sendRequest('GET', '/hello');
        $I->waitTillNextRequestResolves();
        $response = $I->grabLastResponse();
        $I->assertEquals(220, $response->getStatusCode());
        $I->assertEquals(['world'], $response->getHeader('message'));

        $I->sendRequest('GET', '/hello');
        $I->waitTillNextRequestResolves();
        $response = $I->grabLastResponse();
        $I->assertEquals(404, $response->getStatusCode());
        /** @var Response $response */
        $I->assertEquals([], $response->getHeader('message'));
        $I->stopFakeApi();
    }

    public function testWillAlteredResponseStepByStep(ServiceGuy $I)
    {
        $I->wantTo('Will adding altered responses when a request arrive step by step');
        $middleware = $I->expectApiCall(0)->withUrl('/hello');
        $middleware->willAlterResponse()->then(function ($response) use ($I, $middleware) {
            $alteredResponse = $response->withStatus(220)->withAddedHeader('message', 'hello');
            $middleware->addAlteredResponse($alteredResponse);
            $I->assertNotNull($response);
            $I->assertEquals(404, $response->getStatusCode());
        });
        // Responses are cloned inside the object. We must prepare the altered response before adding it.
        $I->initFakeServer();
        $I->sendRequest();
        $I->waitTillNextRequestResolves();
        $response = $I->grabLastResponse();
        $I->assertNotNull($response);
        $I->assertEquals(404, $response->getStatusCode());

        $I->sendRequest('GET', '/hello');
        $I->waitTillNextRequestResolves();
        $response = $I->grabLastResponse();
        $I->assertEquals(220, $response->getStatusCode());
        $I->assertEquals(['hello'], $response->getHeader('message'));

        $middleware->willAlterResponse()->then(function ($response) use ($I, $middleware) {
            $alteredResponse = $response->withStatus(220)->withAddedHeader('message', 'world');
            $middleware->addAlteredResponse($alteredResponse);
            $I->assertNotNull($response);
            $I->assertEquals(404, $response->getStatusCode());
        });
        $I->sendRequest('GET', '/hello');
        $I->waitTillNextRequestResolves();
        $response = $I->grabLastResponse();
        $I->assertEquals(220, $response->getStatusCode());
        $I->assertEquals(['world'], $response->getHeader('message'));

        $I->sendRequest('GET', '/hello');
        $I->waitTillNextRequestResolves();
        $response = $I->grabLastResponse();
        $I->assertEquals(404, $response->getStatusCode());
        /** @var Response $response */
        $I->assertEquals([], $response->getHeader('message'));
        $I->stopFakeApi();
    }
}
