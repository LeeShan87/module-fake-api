<?php
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
