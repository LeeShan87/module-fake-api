<?php

$I = new ServiceGuy($scenario);
$I->wantTo('Save some api calls for testing');
// We don't want to bombard a website, that we do not have rights to test.
//$I->setUpstreamUrl('https://example.com');
$I->createEchoUpstream(1234);
$I->setUpstreamUrl($I->grabEchoServiceUrl());
$I->initFakeServer();
$I->assertEmpty($I->grabRecordedRequests());
$I->assertEmpty($I->grabRecordedResponses());
$I->sendRequest('GET', '/');
$I->waitTillNextRequestResolves();
$I->assertNotEmpty($I->grabRecordedRequests());
$I->assertNotEmpty($I->grabRecordedResponses());
// Record all uncovered API call for 30 sec
//$I->recordRequestsForSeconds(30);
//$I->waitTillFakeApiRecordingEnds();
$I->stopFakeApi();
// Save Requests if needed
//$I->saveRecordedInformation(codecept_output_dir(date('Y_m_d_H_i_s') . ".json"));
