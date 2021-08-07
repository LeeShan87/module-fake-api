<?php

use React\Http\Message\Response;
use RingCentral\Psr7\Response as PSRResponse;
use RingCentral\Psr7\ServerRequest;

use function RingCentral\Psr7\str;

class BaseCest
{
    public function _failed(ServiceGuy $I)
    {
        try {
            $I->stopFakeApi();
        } catch (Exception $e) {
        }
    }
    // helper functions
    public function _validateRequest(ServiceGuy $I, ServerRequest $request = null)
    {
        $I->stopFakeApi();
        $grabbedRequest = $I->grabLastRequest();
        $grabbedResponse = $I->grabLastResponse();
        $I->assertNotNull($grabbedRequest);
        $I->assertNotNull($grabbedResponse);
        if ($grabbedResponse instanceof Response) {
            $I->assertEquals(404, $grabbedResponse->getStatusCode());
        }
        if ($grabbedResponse instanceof PSRResponse) {
            $I->assertEquals(404, $grabbedResponse->getStatusCode());
        }
        if ($grabbedResponse instanceof Exception) {
            $I->assertInstanceOf(\Exception::class, $grabbedResponse);
        }
        if (!is_null($request)) {
            $I->assertEquals($request->getMethod(), $grabbedRequest->getMethod());
            $I->assertEquals($request->getUri()->getHost(), $grabbedRequest->getUri()->getHost());
        }
    }
    public function _validateRequestWithResponse(ServiceGuy $I, ServerRequest $request = null, Response $response = null)
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
    public function _validateRequestExpectationFailed(ServiceGuy $I, ServerRequest $request = null)
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
}
