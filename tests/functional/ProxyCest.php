<?php

use LeeShan87\React\MultiLoop\MultiLoop;
use React\Http\Message\Response;
use RingCentral\Psr7\ServerRequest;
use function RingCentral\Psr7\str;

class ProxyCest extends BaseCest
{

    //proxy tests
    public function startEchoService(ServiceGuy $I)
    {
        $I->wantTo('Start Echo service');
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

    public function sendMockedRequestWhenUpstreamEnabled(ServiceGuy $I)
    {
        $I->wantTo('Send Request when upstream enabled');
        $I->createEchoUpstream(33333);
        $I->setUpstreamUrl($I->grabEchoServiceUrl());
        $I->initFakeServer();
        $I->assertEmpty($I->grabProxiedRequests());
        $I->assertEmpty($I->grabProxiedResponses());
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $request = new ServerRequest('POST', 'http://example.com/safasasdfas');
        $I->sendMockedRequest($request);
        $I->waitTillNextRequestResolves(20);
        $I->stopEchoUpstream();
        $this->_validateRequestWithResponse($I, $request, new Response(200));
        $I->assertNotEmpty($I->grabProxiedRequests());
        $I->assertNotEmpty($I->grabProxiedResponses());
        $I->flushRecordedProxyRequests();
        $I->assertEmpty($I->grabProxiedRequests());
        $I->assertEmpty($I->grabProxiedResponses());
    }
    public function testSaveRecordedProxyRequests(ServiceGuy $I)
    {
        $I->wantTo('Save Recorded proxied requests');
        $I->createEchoUpstream(33333);
        $I->setUpstreamUrl($I->grabEchoServiceUrl());
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $request = new ServerRequest('POST', 'http://example.com/safasasdfas');
        $I->sendMockedRequest($request);
        $I->waitTillNextRequestResolves(2);
        $I->sendMockedRequest($request);
        $I->waitTillNextRequestResolves(2);
        $I->stopEchoUpstream();
        $proxiedRequests = $I->grabProxiedRequests();
        $proxiedResponses = $I->grabProxiedResponses();
        $I->assertNotEmpty($proxiedRequests);
        $I->assertNotEmpty($proxiedResponses);
        $I->assertCount(2, $proxiedRequests);
        $I->assertCount(2, $proxiedResponses);
        $saveFile = codecept_output_dir("save_proxy.json");
        $I->assertFileDoesNotExist($saveFile);
        $I->saveRecordedProxyedInformation($saveFile);
        $I->assertFileExists($saveFile);
        $saveFileContent = json_decode(file_get_contents($saveFile), true);
        unlink($saveFile);
        $I->assertCount(2, $saveFileContent);
        $firstRecord = reset($saveFileContent);
        $I->assertEquals($firstRecord['request'], reset($proxiedRequests));
        $I->assertEquals($firstRecord['response'], reset($proxiedResponses));
        $this->_validateRequestWithResponse($I, $request, new Response(200));
    }

    public function testProxyRequestWhenEchoUpstreamNotEnabled(ServiceGuy $I)
    {
        $I->wantTo("Test proxy to a service not available");
        if (version_compare(phpversion(), '5.6', '<')) {
            $I->markTestSkipped("Socket timeout is tricky on php 5.6");
        }
        $I->setUpstreamUrl("http://localhost:43334");
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $request = new ServerRequest('POST', 'http://example.com/safasasdfas');
        $I->sendMockedRequest($request);
        $I->waitTillNextRequestResolves(2);
        $proxiedRequests = $I->grabProxiedRequests();
        $proxiedResponses = $I->grabProxiedResponses();
        $I->assertNotEmpty($proxiedRequests);
        $I->assertNotEmpty($proxiedResponses);
        $requests = $I->grabRecordedRequests();
        $responses = $I->grabRecordedResponses();
        $I->assertNotEmpty($requests);
        $I->assertNotEmpty($responses);
        $proxyResponse = reset($proxiedResponses);
        $I->assertArrayHasKey("errorCode", $proxyResponse);
        $I->assertArrayHasKey("message", $proxyResponse);
        $I->assertArrayHasKey("trace", $proxyResponse);
        $I->stopFakeApi();
    }

    public function testProxyRequestWhenUpstreamServerError(ServiceGuy $I)
    {
        $I->wantTo("Test proxy to a service responding server error");
        $I->setEchoServiceStatusCode(500);
        $I->createEchoUpstream(33334);
        $I->setUpstreamUrl($I->grabEchoServiceUrl());
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $request = new ServerRequest('POST', 'http://example.com/');
        $I->sendMockedRequest($request);
        $I->waitTillNextRequestResolves(2);
        $proxiedRequests = $I->grabProxiedRequests();
        $proxiedResponses = $I->grabProxiedResponses();
        $I->assertNotEmpty($proxiedRequests);
        $I->assertNotEmpty($proxiedResponses);
        $proxyResponse = reset($proxiedResponses);
        $I->assertArrayHasKey("statusCode", $proxyResponse);
        $I->assertEquals(500, $proxyResponse['statusCode']);
        $I->assertArrayHasKey("headers", $proxyResponse);
        $I->assertArrayHasKey("content", $proxyResponse);
        $I->setEchoServiceStatusCode(200);
        $I->stopFakeApi();
    }

    public function testProxyRequestWhenClientRequestError(ServiceGuy $I)
    {
        $I->wantTo("Test proxy to a service responding client error");
        $I->setEchoServiceStatusCode(403);
        $I->createEchoUpstream(33334);
        $I->setUpstreamUrl($I->grabEchoServiceUrl());
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $request = new ServerRequest('POST', 'http://example.com/');
        $I->sendMockedRequest($request);
        $I->waitTillNextRequestResolves(2);
        $proxiedRequests = $I->grabProxiedRequests();
        $proxiedResponses = $I->grabProxiedResponses();
        $I->assertNotEmpty($proxiedRequests);
        $I->assertNotEmpty($proxiedResponses);
        $proxyResponse = reset($proxiedResponses);
        $I->assertArrayHasKey("statusCode", $proxyResponse);
        $I->assertEquals(403, $proxyResponse['statusCode']);
        $I->assertArrayHasKey("headers", $proxyResponse);
        $I->assertArrayHasKey("content", $proxyResponse);
        $I->setEchoServiceStatusCode(200);
        $I->stopFakeApi();
    }

    public function testProxyRequestWhenUpstreamTimeout(ServiceGuy $I)
    {
        $I->wantTo("Test proxy to a service that timeouts");
        if (version_compare(phpversion(), '5.6', '<')) {
            $I->markTestSkipped("Socket timeout is tricky on php 5.6");
        }
        $I->createEchoUpstream(33334);
        $I->setUpstreamUrl($I->grabEchoServiceUrl());
        $I->disableEchoUpstreamLoop();
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        // The underLaying ReactPhp client not modifies the default_socket_timeout.
        // Todo: Should we modify it?
        $socketTimeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', 1);
        $I->sendRequest();
        $I->waitTillNextRequestResolves(10);
        ini_set('default_socket_timeout', $socketTimeout);
        $proxiedRequests = $I->grabProxiedRequests();
        $proxiedResponses = $I->grabProxiedResponses();
        $I->assertNotEmpty($proxiedRequests);
        $I->assertNotEmpty($proxiedResponses);
        $proxyResponse = reset($proxiedResponses);
        $I->assertArrayHasKey("errorCode", $proxyResponse);
        $I->assertArrayHasKey("message", $proxyResponse);
        $I->assertArrayHasKey("trace", $proxyResponse);
        $I->stopFakeApi();
    }
}
