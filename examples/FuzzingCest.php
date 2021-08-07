<?php

use React\Http\Message\Response;

class FuzzingCest
{
    // Response altering tests
    public function testAddAlteredResponse(ServiceGuy $I)
    {
        $I->wantTo('Add altered responses before a request arrive');
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
        $I->wantTo('Add altered responses when a request arrive');
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
        $I->wantTo('Add altered responses when a request arrive step by step');
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
