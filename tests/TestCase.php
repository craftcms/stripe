<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests;

use Craft;
use craft\config\DbConfig;
use craft\elements\User;
use craft\helpers\App;
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

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (self::$craftBooted) {
            return;
        }

        // The configured test database may be genuinely empty (no Craft schema at all) — install
        // Craft into it if so, using a standalone connection *before* building the full app.
        // `craft\services\Plugins::loadPlugins()` runs automatically from `Application::init()`
        // and bails permanently for this process if the DB isn't installed yet at that point, so
        // installing after the app is already built is too late — `getPlugin()` would never work.
        $dbConfig = Craft::createObject(array_merge(
            ['class' => DbConfig::class],
            require CRAFT_CONFIG_PATH . '/db.php'
        ));
        $db = Craft::createObject(App::dbConfig($dbConfig));
        $db->open();
        if ($db->schema->getTableNames() === []) {
            TestSetup::setupCraftDb($db);
        }
        $db->close();

        /** @var Application $app */
        $app = Craft::createObject(require CRAFT_CONFIG_PATH . '/test.php');
        Craft::$app = $app;

        if (!Craft::$app->getPlugins()->getPlugin('stripe')) {
            Craft::$app->getPlugins()->installPlugin('stripe');

            // Project config writes are normally persisted by a `flush()` listener on
            // `Application::EVENT_AFTER_REQUEST` — which never fires here, since this harness
            // never runs a real request through the app. Without this, `installPlugin()`'s
            // `plugins.stripe.enabled` write stays in memory and is lost at the end of the
            // process, so `getPlugin('stripe')` would look uninstalled again on every next run.
            Craft::$app->getProjectConfig()->saveModifiedConfigData();
        }

        self::$craftBooted = true;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Craft::$app->getDb()->open();
        $this->transaction = Craft::$app->getDb()->beginTransaction();
    }

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
}
