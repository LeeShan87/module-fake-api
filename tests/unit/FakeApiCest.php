<?php

use React\EventLoop\Factory;
use React\Http\Message\Response;
use RingCentral\Psr7\Response as PSRResponse;
use RingCentral\Psr7\ServerRequest;

use function RingCentral\Psr7\str;

class FakeApiCest extends BaseCest
{
    // tests
    public function shouldBindNextPort(ServiceGuy $I)
    {
        $I->wantTo('Bind next port if FakeApi port is under use');
        $loop = Factory::create();
        $server = new \React\Socket\Server('8080', $loop);
        $server2 = new \React\Socket\Server('8081', $loop);
        $I->initFakeServer();
        $fakeApiUrl = $I->grabFakeApiUrl();
        $I->assertStringContainsString('8082', $fakeApiUrl);
        $server->close();
        $server2->close();
    }
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

    public function willReturnJsonResponse(ServiceGuy $I)
    {
        $I->wantTo('Send Request and expecting json response');
        $I->expectApiCall(1)->withUrl('/')->willReturnJsonResponse(200, [], ['result' => 'ok']);
        $expectedResponse = new Response(200, [], json_encode(['result' => 'ok']));
        $I->initFakeServer();
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $request = new ServerRequest('POST', 'http://example.com');
        $I->sendMockedRequest($request);
        $this->_validateRequestWithResponse($I, $request, $expectedResponse);
        $I->assertEmpty($I->grabProxiedRequests());
        $I->assertEmpty($I->grabProxiedResponses());
        $actualResponse = $I->grabLastResponse();
        $responseJson = json_decode((string)$actualResponse->getBody(), true);
        $I->assertEquals(['result' => 'ok'], $responseJson);
    }
}
