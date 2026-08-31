<?php

use craft\console\Application as ConsoleApplication;
use craft\helpers\ArrayHelper;
use craft\services\Config;
use craft\web\Application as WebApplication;

$_SERVER['REMOTE_ADDR'] = '1.1.1.1';
$_SERVER['REMOTE_PORT'] = 654321;

//$basePath = dirname(dirname(dirname(__DIR__)));
$basePath = normalizePathSeparators(dirname(__DIR__, 3));

$srcPluginPath = $basePath . '/src';
$srcPath = $basePath . '/../cms/src';
$vendorPath = CRAFT_VENDOR_PATH;

$appType = appType();

Craft::setAlias('@craftunitsupport', $srcPluginPath . '/test');
Craft::setAlias('@craftunittemplates', $basePath . '/tests/_craft/templates');
Craft::setAlias('@craftunitfixtures', $basePath . '/tests/fixtures');
Craft::setAlias('@testsfolder', $basePath . '/tests');
Craft::setAlias('@crafttestsfolder', $basePath . '/tests/_craft');

// Normalize some Craft defined path aliases.
Craft::setAlias('@lib', normalizePathSeparators(Craft::getAlias('@lib')));
Craft::setAlias('@config', normalizePathSeparators(Craft::getAlias('@config')));
Craft::setAlias('@contentMigrations', normalizePathSeparators(Craft::getAlias('@contentMigrations')));
Craft::setAlias('@storage', normalizePathSeparators(Craft::getAlias('@storage')));
Craft::setAlias('@templates', normalizePathSeparators(Craft::getAlias('@templates')));
Craft::setAlias('@translations', normalizePathSeparators(Craft::getAlias('@translations')));

$configService = createConfigService();

$config = ArrayHelper::merge(
    [
        'components' => [
            'config' => $configService,
        ],
    ],
    require $srcPath . '/config/app.php',
    require $srcPath . '/config/app.' . $appType . '.php',
    $configService->getConfigFromFile('app'),
    $configService->getConfigFromFile("app.$appType")
);

if (defined('CRAFT_SITE')) {
    $config['components']['sites']['currentSite'] = CRAFT_SITE;
}

$config['vendorPath'] = $vendorPath;

$class = appClass($appType);

return ArrayHelper::merge($config, [
    'class' => $class,
    'id' => 'craft-test',
    'env' => 'test',
    'basePath' => $srcPath,
]);


function createConfigService(): Config
{
    $configService = new Config();
    $configService->env = 'test';
    $configService->configDir = CRAFT_CONFIG_PATH;
    $configService->appDefaultsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'defaults';

    return $configService;
}

function appClass(string $preDefinedAppType = ''): string
{
    if (!$preDefinedAppType) {
        $preDefinedAppType = appType();
    }

    return $preDefinedAppType === 'console' ? ConsoleApplication::class : WebApplication::class;
}

function appType(): string
{
//    $appType = 'web';
//    if (isset(CraftTest::$currentTest) && CraftTest::$currentTest instanceof ConsoleTest) {
//        $appType = 'console';
//    }

    return 'web';
}

function normalizePathSeparators(mixed $path): string|false
{
    return is_string($path) ? str_replace("\\", '/', $path) : false;
}