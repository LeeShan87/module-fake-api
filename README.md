# LeeShan87/module-fake-api

[![CI status](https://github.com/LeeShan87/module-fake-api/workflows/CI/badge.svg)](https://github.com/LeeShan87/module-fake-api/actions)
[![Installs on Packagist](https://img.shields.io/packagist/dt/leeshan87/module-fake-apicolor=blue&label=installs%20on%20Packagist)](https://packagist.org/packages/LeeShan87/module-fake-api)

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
$I->wantTo('Save some api calls for testing');
$I->setUpstreamUrl('https://example.com');
$I->initFakeServer();
$loop = $I->grabFakeApiLoop();
$I->recordRequestsForSeconds(30);
$I->waitTillFakeApiRecordingEnds();
$I->stopFakeApi();
$I->saveRecordedInformation(codecept_output_dir(date('Y_m_d_H_i_s') . ".json"));
```

Example usage out side Codeception

```php
 <?php
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
It's *highly recommended to use PHP 7+* for this project.

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

## Support

<p>If you like my work, don’t forget to <a href="https://www.buymeacoffee.com/leeshan87" target="_blank"><img src="https://www.buymeacoffee.com/assets/img/custom_images/orange_img.png" alt="Buy Me A Coffee" style="height: 41px !important;width: 174px !important;box-shadow: 0px 3px 2px 0px rgba(190, 190, 190, 0.5) !important;-webkit-box-shadow: 0px 3px 2px 0px rgba(190, 190, 190, 0.5) !important;" ></a>
</p>