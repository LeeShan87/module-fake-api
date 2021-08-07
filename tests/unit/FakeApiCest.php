<?php

use React\Http\Message\Response;
use RingCentral\Psr7\Response as PSRResponse;
use RingCentral\Psr7\ServerRequest;

use function RingCentral\Psr7\str;

class FakeApiCest extends BaseCest
{
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
}
