<?php

use LeeShan87\React\MultiLoop\MultiLoop;
use Psr\Http\Message\ResponseInterface;
use React\Http\Message\Response;

class PredefinedResponsesCest extends BaseCest
{
    public function testAddPredefinedResponse(ServiceGuy $I)
    {
        $I->wantToTest("Adding predefined responses");
        $I->initFakeServer();
        $I->dontSeePredefinedMessages();
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $lastResponse = $I->grabLastResponse();
        $I->assertEquals(404, $lastResponse->getStatusCode());
        $I->dontSeePredefinedMessages();
        $I->addMessage(201);
        $I->addMessage(200, ['fake' => 'response']);
        $I->addMessage(200, [], 'fake content');
        $I->addMessage();
        $I->seePredefinedMessages();
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $lastResponse = $I->grabLastResponse();
        $I->assertEquals(201, $lastResponse->getStatusCode());

        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $lastResponse = $I->grabLastResponse();
        $I->assertEquals(200, $lastResponse->getStatusCode());
        $I->assertArrayHasKey('fake', $lastResponse->getHeaders());

        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $lastResponse = $I->grabLastResponse();
        $I->assertEquals(200, $lastResponse->getStatusCode());
        $I->assertStringContainsString('fake content', (string)$lastResponse->getBody());

        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $lastResponse = $I->grabLastResponse();
        $I->assertEquals(200, $lastResponse->getStatusCode());
        // serving the last defined message over again until stop
        $I->seePredefinedMessages();
        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $lastResponse = $I->grabLastResponse();
        $I->assertEquals(200, $lastResponse->getStatusCode());
        // over ride the last message
        $I->addMessage(201);
        $I->sendRequest();

        $I->waitTillNextRequestResolves(2);
        $lastResponse = $I->grabLastResponse();
        $I->assertEquals(200, $lastResponse->getStatusCode());

        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $lastResponse = $I->grabLastResponse();
        $I->assertEquals(201, $lastResponse->getStatusCode());

        $I->sendRequest();
        $I->waitTillNextRequestResolves(2);
        $lastResponse = $I->grabLastResponse();
        $I->assertEquals(201, $lastResponse->getStatusCode());
        $I->seePredefinedMessages();
        $I->flushAddedMessages();
        $I->dontSeePredefinedMessages();
        $I->stopFakeApi();
    }
    public function testSendRequestFail(ServiceGuy $I)
    {
        $I->wantTo("See sending request should be rejected when Fake Api not initialized");
        $I->sendRequest()->then(function ($response) use ($I) {
            $I->fail("Exception should happened");
        })->otherwise(function (\Exception $e) use ($I) {
            $I->assertInstanceOf("\Exception", $e);
        })->otherwise(function (\Throwable $e) use ($I) {
            $I->assertInstanceOf("\Throwable", $e);
        });
    }
}
