<?php

namespace craft\stripe\services;

use Craft;
use craft\helpers\App;
use craft\stripe\Plugin;
use Stripe\Collection;
use yii\base\Component;
use Stripe\StripeClient;

/**
 * Api service
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class Api extends Component
{
    /**
     * @var string
     */
    public const STRIPE_API_VERSION = '2023-10-16';

    /**
     * @var StripeClient|null
     */
    private ?StripeClient $_client = null;

    /**
     * Retrieve all products.
     *
     * @return array
     */
    public function getAllProducts(): array
    {
        return $this->getAll('products', ['expand' => ['data.default_price']]);
    }

    /**
     * Retrieve all prices.
     *
     * @return array
     */
    public function getAllPrices(): array
    {
        return $this->getAll('prices'/*, ['expand' => ['data.product']]*/);
    }

    /**
     * Retrieve all subscriptions.
     *
     * @return array
     */
    public function getAllSubscriptions(): array
    {
        return $this->getAll('subscriptions', ['status' => 'all']);
    }

    /**
     * Iteratively retrieves a paginated collection of API resource.
     *
     * @param string $type name of the Stripe resource
     * @param array $params
     * @return array
     */
    public function getAll(string $type, array $params = []): array
    {
        $resources = [];

        // Force maximum page size:
        $params['limit'] = 100;

        $batch = $this->getClient()->$type->all($params);
        foreach ($batch->autoPagingIterator() as $item) {
            $resources[] = $item;
        }

        return $resources;
    }

//    /**
//     * Retrieve a single product by its Shopify ID.
//     *
//     * @return ShopifyProduct
//     */
//    public function getProductByShopifyId($id): ShopifyProduct
//    {
//        return ShopifyProduct::find($this->getSession(), $id);
//    }
//
//    /**
//     * Retrieve a product ID by a variant's inventory item ID.
//     *
//     * @return ?int The product Shopify ID
//     */
//    public function getProductIdByInventoryItemId($id): ?int
//    {
//        $variant = Plugin::getInstance()->getApi()->get('variants', [
//            'inventory_item_id' => $id,
//        ]);
//
//        if ($variant['variants']) {
//            return $variant['variants'][0]['product_id'];
//        }
//
//        return null;
//    }
//
//    /**
//     * Retrieves "metafields" for the provided Shopify product ID.
//     *
//     * @param int $id Shopify Product ID
//     * @return ShopifyMetafield[]
//     */
//    public function getMetafieldsByProductId(int $id): array
//    {
//        /** @var ShopifyMetafield[] $metafields */
//        $metafields = $this->getAll(ShopifyMetafield::class, [
//            'metafield' => [
//                'owner_id' => $id,
//                'owner_resource' => 'product',
//            ],
//        ]);
//
//        return $metafields;
//    }
//
//    /**
//     * Retrieves "metafields" for the provided Shopify product ID.
//     *
//     * @param int $id Shopify Product ID
//     */
//    public function getVariantsByProductId(int $id): array
//    {
//        $variants = $this->get("products/{$id}/variants");
//
//        return $variants['variants'];
//    }

//    /**
//     * Shortcut for retrieving arbitrary API resources. A plain (parsed) response body is returned, so it’s the caller’s responsibility for unpacking it properly.
//     *
//     * @see Rest::get();
//     */
//    public function get($path, array $query = [])
//    {
//        $response = $this->getClient()->get($path, [], $query);
//
//        return $response->getDecodedBody();
//    }

    /**
     * Returns or sets up a StripeClient.
     *
     * @return StripeClient
     */
    public function getClient(): StripeClient
    {
        if ($this->_client === null) {
            $settings = Plugin::getInstance()->getSettings();
            //$session = $this->getSession();
            $this->_client = new StripeClient([
                "api_key" => App::parseEnv($settings->secretKey),
                "stripe_version" => self::STRIPE_API_VERSION,
            ]);
        }

        return $this->_client;
    }

//    /**
//     * Returns or initializes a context + session.
//     *
//     * @return Session|null
//     * @throws \Shopify\Exception\MissingArgumentException
//     */
//    public function getSession(): ?Session
//    {
//        $pluginSettings = Plugin::getInstance()->getSettings();
//
//        if (
//            $this->_session === null &&
//            ($apiKey = App::parseEnv($pluginSettings->apiKey)) &&
//            ($apiSecretKey = App::parseEnv($pluginSettings->apiSecretKey))
//        ) {
//            /** @var MonologTarget $webLogTarget */
//            $webLogTarget = Craft::$app->getLog()->targets['web'];
//
//            Context::initialize(
//                apiKey: $apiKey,
//                apiSecretKey: $apiSecretKey,
//                scopes: ['write_products', 'read_products', 'read_inventory'],
//                // This `hostName` is different from the `shop` value used when creating a Session!
//                // Shopify wants a name for the host/environment that is initiating the connection.
//                hostName: !Craft::$app->request->isConsoleRequest ? Craft::$app->getRequest()->getHostName() : 'localhost',
//                sessionStorage: new FileSessionStorage(Craft::$app->getPath()->getStoragePath() . DIRECTORY_SEPARATOR . 'shopify_api_sessions'),
//                apiVersion: self::SHOPIFY_API_VERSION,
//                isEmbeddedApp: false,
//                logger: $webLogTarget->getLogger(),
//            );
//
//            $hostName = App::parseEnv($pluginSettings->hostName);
//            $accessToken = App::parseEnv($pluginSettings->accessToken);
//
//            $this->_session = new Session(
//                id: 'NA',
//                shop: $hostName,
//                isOnline: false,
//                state: 'NA'
//            );
//
//            $this->_session->setAccessToken($accessToken); // this is the most important part of the authentication
//        }
//
//        return $this->_session;
//    }
}
