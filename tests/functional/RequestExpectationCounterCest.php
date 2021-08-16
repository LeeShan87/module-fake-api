<?php
class RequestExpectationCounterCest extends BaseCest
{
    public function testExactly(ServiceGuy $I)
    {
        $I->wantToTest("Exactly expectation counter when it pass");
        $I->expectApiCall()
            ->exactly(2)
            ->withUrl("/")
            ->willReturnResponse(204);
        $I->initFakeServer();
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $response = $I->grabLastResponse();
        $I->assertEquals(204, $response->getStatusCode());
        $I->stopFakeApi();
    }

    public function testExactlyWhenItCalledMore(ServiceGuy $I)
    {
        $I->wantToTest("Exactly expectation counter when it called more than expected");
        $I->expectApiCall()
            ->withUrl("/")
            ->willReturnResponse(204)
            ->exactly(3);

        $I->initFakeServer();
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $response = $I->grabLastResponse();
        $I->assertEquals(204, $response->getStatusCode());
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $response = $I->grabLastResponse();
        $I->assertEquals(204, $response->getStatusCode());
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $response = $I->grabLastResponse();
        $I->assertEquals(204, $response->getStatusCode());
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);

        try {
            $I->stopFakeApi();
        } catch (\PHPUnit\Framework\ExpectationFailedException $e) {
            $I->assertStringContainsString('Api endpoint was called not exactly', $e->getMessage());
        }
    }

    public function testExactlyWhenItCalledLess(ServiceGuy $I)
    {
        $I->wantToTest("Exactly expectation counter when it called less than expected");
        $I->expectApiCall()
            ->withUrl("/")
            ->willReturnResponse(204)
            ->exactly(3);

        $I->initFakeServer();
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $response = $I->grabLastResponse();
        $I->assertEquals(204, $response->getStatusCode());

        try {
            $I->stopFakeApi();
        } catch (\PHPUnit\Framework\ExpectationFailedException $e) {
            $I->assertStringContainsString('Api endpoint was called not exactly', $e->getMessage());
        }
    }
    public function testNever(ServiceGuy $I)
    {
        $I->wantToTest("Never expectation counter when it pass");
        $I->expectApiCall()
            ->never()
            ->withUrl("/a");

        $I->initFakeServer();
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $response = $I->grabLastResponse();
        $I->assertEquals(404, $response->getStatusCode());
        $I->stopFakeApi();
    }
    public function testNeverWhenItFail(ServiceGuy $I)
    {
        $I->wantToTest("Never expectation counter when it called");
        $I->expectApiCall()
            ->never()
            ->withUrl("/");

        $I->initFakeServer();
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $response = $I->grabLastResponse();
        $I->assertEquals(404, $response->getStatusCode());

        try {
            $I->stopFakeApi();
        } catch (\PHPUnit\Framework\ExpectationFailedException $e) {
            $I->assertStringContainsString('Api endpoint was called not exactly', $e->getMessage());
        }
    }
    public function testOnce(ServiceGuy $I)
    {
        $I->wantToTest("Once expectation counter when it pass");
        $I->expectApiCall()
            ->once()
            ->withUrl("/")
            ->willReturnResponse(204);
        $I->initFakeServer();
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $response = $I->grabLastResponse();
        $I->assertEquals(204, $response->getStatusCode());
        $I->stopFakeApi();
    }

    public function testOnceWhenNotCalled(ServiceGuy $I)
    {
        $I->wantToTest("Once expectation counter when it not called");
        $I->expectApiCall()
            ->once()
            ->withUrl("/once")
            ->willReturnResponse(204);
        $I->initFakeServer();
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $response = $I->grabLastResponse();
        $I->assertEquals(404, $response->getStatusCode());
        try {
            $I->stopFakeApi();
        } catch (\PHPUnit\Framework\ExpectationFailedException $e) {
            $I->assertStringContainsString('Api endpoint was called not exactly', $e->getMessage());
        }
    }

    public function testOnceWhenItCalledMore(ServiceGuy $I)
    {
        $I->wantToTest("Once expectation counter when it called more than expected");
        $I->expectApiCall()
            ->once()
            ->withUrl("/")
            ->willReturnResponse(204);
        $I->initFakeServer();
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $response = $I->grabLastResponse();
        $I->assertEquals(204, $response->getStatusCode());
        try {
            $I->stopFakeApi();
        } catch (\PHPUnit\Framework\ExpectationFailedException $e) {
            $I->assertStringContainsString('Api endpoint was called not exactly', $e->getMessage());
        }
    }

    public function testAtLeast(ServiceGuy $I)
    {
        $I->wantToTest("AtLeast expectation counter when it pass");
        $I->expectApiCall()
            ->atLeast(2)
            ->withUrl("/")
            ->willReturnResponse(204);
        $I->initFakeServer();
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $response = $I->grabLastResponse();
        $I->assertEquals(204, $response->getStatusCode());
        $I->stopFakeApi();
    }

    public function testAtLeastWhenItCalledMore(ServiceGuy $I)
    {
        $I->wantToTest("AtLeast expectation counter when it called more");
        $I->expectApiCall()
            ->atLeast(2)
            ->withUrl("/")
            ->willReturnResponse(204);
        $I->initFakeServer();
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $response = $I->grabLastResponse();
        $I->assertEquals(204, $response->getStatusCode());
        $I->stopFakeApi();
    }

    public function testAtLeastWhenItCalledLess(ServiceGuy $I)
    {
        $I->wantToTest("AtLeast expectation counter when it called less");
        $expectation = $I->expectApiCall()
            ->atLeast(2)
            ->withUrl("/")
            ->willReturnResponse(204);
        $I->initFakeServer();
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $response = $I->grabLastResponse();
        $I->assertEquals(204, $response->getStatusCode());
        try {
            $I->stopFakeApi();
        } catch (\PHPUnit\Framework\ExpectationFailedException $e) {
            $I->assertStringContainsString($expectation->grabVerificationMessage(), $e->getMessage());
        }
    }

    public function testAtLeastOnce(ServiceGuy $I)
    {
        $I->wantToTest("AtLeastOnce expectation counter when it pass");
        $I->expectApiCall()
            ->atLeastOnce()
            ->withUrl("/")
            ->willReturnResponse(204);
        $I->initFakeServer();
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $response = $I->grabLastResponse();
        $I->assertEquals(204, $response->getStatusCode());
        $I->stopFakeApi();
    }

    public function testAtLeastOnceWhenItCalledMore(ServiceGuy $I)
    {
        $I->wantToTest("AtLeastOnce expectation counter when it called more");
        $I->expectApiCall()
            ->atLeastOnce()
            ->withUrl("/")
            ->willReturnResponse(204);
        $I->initFakeServer();
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $response = $I->grabLastResponse();
        $I->assertEquals(204, $response->getStatusCode());
        $I->stopFakeApi();
    }

    public function testAtLeastOnceWhenItCalledLess(ServiceGuy $I)
    {
        $I->wantToTest("AtLeastOnce expectation counter when it called less");
        $expectation = $I->expectApiCall()
            ->atLeastOnce()
            ->withUrl("/")
            ->willReturnResponse(204);
        $I->initFakeServer();
        try {
            $I->stopFakeApi();
        } catch (\PHPUnit\Framework\ExpectationFailedException $e) {
            $I->assertStringContainsString($expectation->grabVerificationMessage(), $e->getMessage());
        }
    }

    //any
    public function testAny(ServiceGuy $I)
    {
        $I->wantToTest("Any expectation counter when not called");
        $I->expectApiCall()
            ->any()
            ->withUrl("/")
            ->willReturnResponse(204);
        $I->initFakeServer();
        $I->stopFakeApi();
    }

    public function testAnyWhenCalledOnce(ServiceGuy $I)
    {
        $I->wantToTest("Any expectation counter when it called");
        $I->expectApiCall()
            ->any()
            ->withUrl("/")
            ->willReturnResponse(204);
        $I->initFakeServer();
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $response = $I->grabLastResponse();
        $I->assertEquals(204, $response->getStatusCode());
        $I->stopFakeApi();
    }

    public function testAnyWhenCalledMore(ServiceGuy $I)
    {
        $I->wantToTest("Any expectation counter when it called more");
        $I->expectApiCall()
            ->any()
            ->withUrl("/")
            ->willReturnResponse(204);
        $I->initFakeServer();
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $response = $I->grabLastResponse();
        $I->assertEquals(204, $response->getStatusCode());
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $response = $I->grabLastResponse();
        $I->assertEquals(204, $response->getStatusCode());
        $I->stopFakeApi();
    }
}
