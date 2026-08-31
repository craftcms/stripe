<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\stripe\tests;

use Craft;
use craft\config\DbConfig;
use craft\enums\CmsEdition;
use craft\elements\User;
use craft\helpers\App;
use craft\migrations\Install;
use craft\models\Site;
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

        /** @var Application $app */
        $app = Craft::createObject(require CRAFT_CONFIG_PATH . '/test.php');
        Craft::$app = $app;

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
        $db->schemaCache = false;
        if ($db->schema->getTableNames() === []) {

            // Equivalent to `TestSetup::setupCraftDb($db)`, minus its project-config-seeded-site
            // branch - that branch calls `\craft\test\Craft::$instance`, which forces autoloading
            // of Craft's Codeception-based test module (`craft\test\Craft extends
            // Codeception\Module\Yii2`). This package doesn't seed a project-config folder for
            // tests, so that branch is a no-op for us anyway - inlining lets us skip Codeception
            // entirely, consistent with this package no longer depending on it.
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
        $db->close();

        // Fresh installs default to the Solo edition (1-user cap) - tests that create more than
        // one user (e.g. author fixtures in EntryTest) need Pro so `Users::canCreateUsers()`
        // doesn't silently reject the save in `User::beforeSave()` before validation even runs.
        Craft::$app->setEdition(CmsEdition::Pro);

        if (!Craft::$app->getPlugins()->getPlugin('stripe')) {
            Craft::$app->getPlugins()->installPlugin('stripe');
        }

        // Project config writes (edition + plugin enablement) are normally persisted by a
        // `flush()` listener on `Application::EVENT_AFTER_REQUEST` — which never fires here,
        // since this harness never runs a real request through the app. Without this, those
        // writes stay in memory and are lost at the end of the process.
        Craft::$app->getProjectConfig()->saveModifiedConfigData();

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
