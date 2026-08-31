<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests;

use Craft;
use craft\enums\CmsEdition;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\migrations\Install;
use craft\models\Site;
use craft\services\Config;
use craft\stripe\Plugin;
use craft\test\TestSetup;
use craft\web\Application;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase as BaseTestCase;
use yii\db\Transaction;

/**
 * Base test case for tests that need a booted Craft application and database.
 *
 * Boots Craft once per test run against the `tests/_craft` fixture install (an empty schema,
 * migrated by installing the plugin below), then wraps each test in a DB transaction that's
 * rolled back afterwards. Tests seed their own fixture data via the real plugin services (see
 * `craft\stripe\tests\Helpers\StripeApiObjectFactory`), so no pre-seeded DB dump is needed.
 */
class TestCase extends BaseTestCase
{
    private static bool $craftBooted = false;

    private Transaction $transaction;

    /** @var array<string, string> Component ID => original class, for restoring in tearDown() */
    private array $mockedComponents = [];

    /**
     * This runs before each test class.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (self::$craftBooted) {
            return;
        }

        /** @var Application $app */
        $app = Craft::createObject(self::createTestCraftObjectConfig());
        Craft::$app = $app;

        self::setUpDb();

        Craft::$app->setEdition(CmsEdition::Pro);

        if (!Craft::$app->getPlugins()->getPlugin('stripe')) {
            Craft::$app->getPlugins()->installPlugin('stripe');
        }

        Craft::$app->getProjectConfig()->saveModifiedConfigData();

        self::$craftBooted = true;
    }

    /**
     * This runs before each test method.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Craft::$app->getDb()->open();
        $this->transaction = Craft::$app->getDb()->beginTransaction();
    }

    /**
     * This runs after each test method.
     */
    protected function tearDown(): void
    {
        $this->transaction->rollBack();

        // Restore any plugin components swapped out via mockComponent()/partialMockComponent().
        foreach ($this->mockedComponents as $id => $class) {
            Plugin::getInstance()->set($id, $class);
        }
        $this->mockedComponents = [];

        // Craft::$app persists across tests in the same run, so a logged-in identity, or query/body
        // params/method set for a controller test, would otherwise leak into the next test.
        Craft::$app->getUser()->setIdentity(null);
        Craft::$app->getRequest()->setQueryParams([]);
        Craft::$app->getRequest()->setBodyParams([]);
        Craft::$app->getRequest()->setAcceptableContentTypes([]);
        unset($_SERVER['REQUEST_METHOD']);

        // Craft::$app->getResponse() is also a shared singleton — a controller action that set a
        // status code/format/data (e.g. asFailure()'s setStatusCode(400)) would otherwise leak too.
        $response = Craft::$app->getResponse();
        $response->setStatusCode(200);
        $response->format = \yii\web\Response::FORMAT_HTML;
        $response->data = null;
        $response->content = null;

        parent::tearDown();
    }

    /**
     * Logs in as the environment's real admin user, so controller tests can pass permission checks
     * without needing to create a new user (Solo edition caps the install at one user).
     */
    protected function loginAsAdmin(): User
    {
        /** @var User $admin */
        $admin = User::find()->admin()->status(null)->one();
        Craft::$app->getUser()->setIdentity($admin);

        return $admin;
    }

    /**
     * Swaps a plugin component (e.g. `'api'`) for a full mock, automatically restored in tearDown().
     *
     * @param string $id Component ID as registered in `Plugin::config()`
     * @param class-string $class
     * @return MockObject
     */
    protected function mockComponent(string $id, string $class): MockObject
    {
        $mock = $this->createMock($class);
        Plugin::getInstance()->set($id, $mock);
        $this->mockedComponents[$id] = $class;

        return $mock;
    }

    /**
     * Swaps a plugin component for a partial mock that only stubs the given methods, leaving
     * everything else calling through to the real implementation. Automatically restored in
     * tearDown(). Useful when a method under test calls other real methods on the same service.
     *
     * @param string $id Component ID as registered in `Plugin::config()`
     * @param class-string $class
     * @param string[] $onlyMethods
     * @return MockObject
     */
    protected function partialMockComponent(string $id, string $class, array $onlyMethods): MockObject
    {
        $mock = $this->getMockBuilder($class)
            ->onlyMethods($onlyMethods)
            ->getMock();
        Plugin::getInstance()->set($id, $mock);
        $this->mockedComponents[$id] = $class;

        return $mock;
    }

    ////////
    public static function createTestCraftObjectConfig(): array
    {
        $_SERVER['REMOTE_ADDR'] = '1.1.1.1';
        $_SERVER['REMOTE_PORT'] = 654321;

        //$basePath = dirname(dirname(dirname(__DIR__)));
        $basePath = self::normalizePathSeparators(CRAFT_ROOT_PATH);

        $srcPluginPath = $basePath . '/src';
        $srcPath = $basePath . '/../cms/src';
        $vendorPath = CRAFT_VENDOR_PATH;

        $appType = 'web';

        // Normalize some Craft-defined path aliases.
        Craft::setAlias('@lib', self::normalizePathSeparators(Craft::getAlias('@lib')));
        Craft::setAlias('@config', self::normalizePathSeparators(Craft::getAlias('@config')));
        Craft::setAlias('@contentMigrations', self::normalizePathSeparators(Craft::getAlias('@contentMigrations')));
        Craft::setAlias('@storage', self::normalizePathSeparators(Craft::getAlias('@storage')));
        Craft::setAlias('@templates', self::normalizePathSeparators(Craft::getAlias('@templates')));
        Craft::setAlias('@translations', self::normalizePathSeparators(Craft::getAlias('@translations')));

        $configService = self::createConfigService();

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

        return ArrayHelper::merge($config, [
            'class' => Application::class,
            'id' => 'craft-test',
            'env' => 'test',
            'basePath' => $srcPath,
        ]);
    }

    protected static function createConfigService(): Config
    {
        $configService = new Config();
        $configService->env = 'test';
        $configService->configDir = CRAFT_CONFIG_PATH;
        $configService->appDefaultsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'defaults';

        return $configService;
    }

    protected static function normalizePathSeparators(mixed $path): string|false
    {
        return is_string($path) ? str_replace("\\", '/', $path) : false;
    }

    protected static function setUpDb(): void
    {
        $db = Craft::$app->getDb();
        $db->schemaCache = false;
        $db->emulatePrepare = false;

        if ($db->schema->getTableNames() === []) {
            $site = new Site([
                'name' => 'Craft test site',
                'handle' => 'defaultSite',
                'hasUrls' => true,
                'baseUrl' => TestSetup::SITE_URL,
                'language' => 'en-US',
                'primary' => true,
            ]);

            $migration = new Install([
                'db' => $db,
                'username' => TestSetup::USERNAME,
                'password' => 'craftcms2018!!',
                'email' => 'support@craftcms.com',
                'site' => $site,
                // Also requires `Craft::$app` (for `getProjectConfig()`), and this package
                // doesn't seed a `config/project/` folder for tests, so there's nothing to apply.
                'applyProjectConfigYaml' => false,
            ]);
            try {
                $migration->up(true);
            } catch (\Throwable $e) {
                TestSetup::cleanseDb($db);
                throw $e;
            }
        }
    }
}
