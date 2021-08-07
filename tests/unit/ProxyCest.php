<?php

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
        // Todo: Move Upstream tests to a separate test class
        // Todo: Timeouts should be tested too.
        $I->wantTo('Send Request when upstream enabled');
        $I->createEchoUpstream(33333);
        $I->setUpstreamUrl($I->grabEchoServiceUrl());
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $request = new ServerRequest('POST', 'http://example.com/safasasdfas');
        $I->sendMockedRequest($request);
        $I->waitTillNextRequestResolves(20);
        $I->stopEchoUpstream();
        $this->_validateRequestWithResponse($I, $request, new Response(200));
        $I->assertNotEmpty($I->grabProxiedRequests());
        $I->assertNotEmpty($I->grabProxiedResponses());
    }
}
