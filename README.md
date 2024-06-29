# LeeShan87/module-fake-api

[![CI status](https://github.com/LeeShan87/module-fake-api/workflows/CI/badge.svg)](https://github.com/LeeShan87/module-fake-api/actions)
[![Installs on Packagist](https://img.shields.io/packagist/dt/leeshan87/module-fake-api?color=blue&label=installs%20on%20Packagist)](https://packagist.org/packages/LeeShan87/module-fake-api)

Asynchronous Fake Api module for Codeception.

This Codeception module helps to create an async FakeApi http server.
This module requires react/http:^1.0.0 to work.
It provides an async http server, which can

- Respond to http request
- Proxy request to an external http server
- Record proxied requests and store them on the disk
  It can be used in Codeception tests, if you can manage when and how to tick the FakeApi event loop
  Example Cept usage:

```php
<?php
$I = new ServiceGuy($scenario);
$I->wantTo('Save some api calls for testing');
$I->setUpstreamUrl('https://example.com');
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

```

Example usage out side Codeception

```php
 // find vendor dir if possible
$dir = __DIR__;
$ds = DIRECTORY_SEPARATOR;
for ($i = 0; $i < 3; $i++) {
    $projectRootDir = dirname($dir);
    if (!is_dir("$projectRootDir{$ds}vendor")) {
        $dir = $projectRootDir;
        continue;
    }
    include "$projectRootDir{$ds}vendor{$ds}autoload.php";
    include "$projectRootDir{$ds}vendor{$ds}codeception{$ds}codeception{$ds}autoload.php";
}

use Codeception\Module\FakeApi;
use Codeception\Util\Stub;

$api = new FakeApi(Stub::make(\Codeception\Lib\ModuleContainer::class));
$api->setBindPort(8081);
$api->initFakeServer();
$api->addMessage(200, [], 'hello');
$api->addMessage(200, [], 'hello w');
$api->addMessage(200, [], 'hello wor');
$api->addMessage(200, [], 'hello world');
$api->run();
```

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

This will install the latest supported version:

```bash
$ composer require leeshan87/module-fake-api:^0.1
```

See also the [CHANGELOG](CHANGELOG.md) for details about version upgrades.

This project aims to run on any platform and thus does not require any PHP
extensions and supports running on legacy PHP 5.3 through current PHP 8+.
It's _highly recommended to use PHP 7+_ for this project.

## Tests

To run the test suite, you first need to clone this repo and then install all
dependencies [through Composer](https://getcomposer.org):

```bash
$ composer install
```

To run the test suite, go to the project root and run:

```bash
$ php vendor/bin/codecept run unit
```

## License

MIT
