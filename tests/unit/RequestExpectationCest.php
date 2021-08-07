<?php

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use RingCentral\Psr7\ServerRequest;


class RequestExpectationCest extends BaseCest
{
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
        $request = new ServerRequest('POST', 'http://example.com?foo=bar');
        $expectedRequest = $I->expectApiCall(1)->withQueryParameter('foo', 'bar');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $this->_validateRequestWithResponse($I, $request, $expectedResponse);
    }
    public function expectMultipleQueryParameter(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting multiple get parameters');
        $request = new ServerRequest('POST', 'http://example.com?foo=bar&bar=foo');
        $expectedRequest = $I->expectApiCall(1)->withQueryParameter('foo', 'bar')->withQueryParameter('bar', 'foo');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $this->_validateRequestWithResponse($I, $request, $expectedResponse);
    }
    public function expectMultipleQueryParameterShouldFail(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting multiple get parameters');
        $request = new ServerRequest('POST', 'http://example.com?foo=bar&bar=foo');
        $expectedRequest = $I->expectApiCall(1)->withQueryParameter('foo', 'bar')->withQueryParameter('baz', 'foo');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $this->_validateRequestExpectationFailed($I, $request);
    }
    public function expectQueryParameterShouldFail(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting a get parameter should fail');
        $request = new ServerRequest('POST', 'http://example.com?foo=bar');
        $expectedRequest = $I->expectApiCall(1)->withQueryParameter('baz', 'bar');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $this->_validateRequestExpectationFailed($I, $request);
    }
    public function expectMethod(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting a given http method');
        $request = new ServerRequest('POST', 'http://example.com');
        $expectedRequest = $I->expectApiCall(1)->withMethod('POST');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $this->_validateRequestWithResponse($I, $request, $expectedResponse);
    }
    public function expectMethodShouldFail(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting a given http method should fail');
        $request = new ServerRequest('GET', 'http://example.com');
        $expectedRequest = $I->expectApiCall(1)->withMethod('POST');
        $expectedResponse = $expectedRequest->willReturn(new Response(222))->getDefinedResponse();
        $I->initFakeServer();
        $I->sendMockedRequest($request);
        $this->_validateRequestExpectationFailed($I, $request);
    }
    public function expectQueryParameters(ServiceGuy $I)
    {
        $I->wantTo('Send Request when expecting a get parameters');
        $request = new ServerRequest('POST', 'http://example.com?foo=bar&bar=foo');
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
        $request = new ServerRequest('POST', 'http://example.com?foo=bar&bar=foo');
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
        $request = new ServerRequest('POST', 'http://example.com?foo=bar&bar=foo');
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
        $request = new ServerRequest('POST', 'http://example.com?baz=bar');
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
}
