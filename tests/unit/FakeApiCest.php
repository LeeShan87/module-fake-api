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
        $originalBind = $I->grabFakeApiBindPort();
        $I->setFakeApiBindPort(11180);
        $loop = Factory::create();
        $server = new \React\Socket\Server('11180', $loop);
        $server2 = new \React\Socket\Server('11181', $loop);
        $I->initFakeServer();
        $fakeApiUrl = $I->grabFakeApiUrl();
        $I->assertStringContainsString('11182', $fakeApiUrl);
        $I->setFakeApiBindPort($originalBind);
        $server->close();
        $server2->close();
        $I->stopFakeApi();
    }

    public function shouldThrowExceptionWhenAllPortsUsed(ServiceGuy $I)
    {
        $I->wantTo('Bind next port if FakeApi port is under use');
        $originalBind = $I->grabFakeApiBindPort();
        $bindStart = 22280;
        $I->setFakeApiBindPort($bindStart);
        $loop = Factory::create();
        $servers = [];
        for ($i = 0; $i < 11; $i++) {
            $servers[] = new \React\Socket\Server($bindStart + $i, $loop);
        }
        try {
            $I->initFakeServer();
            $I->fail("Exception should happened");
        } catch (\Throwable $e) {
            $I->assertInstanceOf("\RuntimeException", $e);
            $I->assertStringContainsString('Failed to listen on', $e->getMessage());
        } finally {
            foreach ($servers as $server) {
                $server->close();
            }
            $I->stopFakeApi();
            $I->setFakeApiBindPort($originalBind);
        }
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
    public function testWaitForSeconds(ServiceGuy $I)
    {
        $I->wantTo('Test Async wait for seconds');
        $startTime = time();
        $I->initFakeServer();
        $I->waitForSeconds(2);
        $I->stopFakeApi();
        $I->assertGreaterOrEquals($startTime + 1, time());
    }
}
