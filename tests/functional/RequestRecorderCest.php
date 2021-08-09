<?php
class RequestRecorderCest extends BaseCest
{
    public function testRequestRecording(ServiceGuy $I)
    {
        $I->wantToTest("Request recording");
        $I->initFakeServer();
        $I->assertEmpty($I->grabRecordedRequests());
        $I->assertEmpty($I->grabRecordedResponses());
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $I->sendRequest();
        $I->waitTillNextRequestResolves(1);
        $requests = $I->grabRecordedRequests();
        $responses = $I->grabRecordedResponses();
        $I->assertNotEmpty($requests);
        $I->assertCount(1, $requests);
        $I->assertNotEmpty($responses);
        $I->assertCount(1, $responses);
        $I->assertInstanceOf("\Psr\Http\Message\ServerRequestInterface", $I->grabLastRequest());
        $I->assertEquals($I->grabLastRequest()->getMethod(), $requests[0]['method']);
        $I->assertInstanceOf("\Psr\Http\Message\ResponseInterface", $I->grabLastResponse());
        $I->assertEquals($I->grabLastResponse()->getStatusCode(), $responses[0]['statusCode']);
        $I->sendRequest();
        $I->sendRequest();
        $I->sendRequest();
        $I->sendRequest();
        $I->dontSeeFakeApiIsRecording();
        $I->recordRequestsForSeconds(2);
        $I->seeFakeApiIsRecording();
        $I->waitTillFakeApiRecordingEnds();
        $requests = $I->grabRecordedRequests();
        $responses = $I->grabRecordedResponses();
        $I->dontSeeFakeApiIsRecording();
        $I->assertNotEmpty($requests);
        $I->assertCount(5, $requests);
        $I->assertNotEmpty($responses);
        $I->assertCount(5, $responses);
        $I->assertInstanceOf("\Psr\Http\Message\ServerRequestInterface", $I->grabLastRequest());
        $I->assertEquals($I->grabLastRequest()->getMethod(), end($requests)['method']);
        $I->assertInstanceOf("\Psr\Http\Message\ResponseInterface", $I->grabLastResponse());
        $I->assertEquals($I->grabLastResponse()->getStatusCode(), end($responses)['statusCode']);
        $saveFile = codecept_output_dir("save.json");
        $I->assertFileDoesNotExist($saveFile);
        $I->saveRecordedInformation($saveFile);
        $I->assertFileExists($saveFile);
        $saveFileContent = json_decode(file_get_contents($saveFile), true);
        unlink($saveFile);
        $I->assertCount(5, $saveFileContent);
        $firstRecord = reset($saveFileContent);
        $I->assertEquals($firstRecord['request'], reset($requests));
        $I->assertEquals($firstRecord['response'], reset($responses));
        $I->flushRecordedRequests();
        $I->assertEmpty($I->grabRecordedRequests());
        $I->assertEmpty($I->grabRecordedResponses());
        $I->assertNull($I->grabLastRequest());
        $I->assertNull($I->grabLastResponse());
        $I->stopFakeApi();
    }
}
