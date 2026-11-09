<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Tests\Support;

use CraftCms\Cms\Support\Json;
use RuntimeException;
use Stripe\HttpClient\ClientInterface;
use Stripe\Util\CaseInsensitiveArray;

/**
 * An in-memory stand-in for the Stripe API, seeded from `tests/Fixtures/stripe.json` (API version 2026-01-28.clover shapes).
 *
 * It answers the requests the plugin makes, records each one, and only accepts `sk_test_` keys.
 */
class FakeStripe implements ClientInterface
{
    private const array COLLECTIONS = [
        'products' => 'product',
        'prices' => 'price',
        'customers' => 'customer',
        'subscriptions' => 'subscription',
        'invoices' => 'invoice',
        'payment_methods' => 'payment_method',
        'webhook_endpoints' => 'webhook_endpoint',
    ];

    /** @var array<string, mixed> */
    public array $state;

    /** @var list<array{method: string, path: string, params: array<string, mixed>}> */
    public array $requests = [];

    public function __construct()
    {
        $this->state = Json::decode((string) file_get_contents(__DIR__.'/../Fixtures/stripe.json'));
    }

    /**
     * Returns the recorded requests for a method and path.
     *
     * @return list<array<string, mixed>> The requests’ params
     */
    public function requestsTo(string $method, string $path): array
    {
        return array_values(array_map(
            fn (array $request) => $request['params'],
            array_filter($this->requests, fn (array $request) => $request['method'] === $method && $request['path'] === $path),
        ));
    }

    /**
     * @param  'delete'|'get'|'post'  $method
     * @param  string  $absUrl
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $params
     * @param  bool  $hasFile
     * @return array{0: string, 1: int, 2: CaseInsensitiveArray}
     */
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
    {
        if (! str_starts_with($absUrl, 'https://api.stripe.com/')) {
            throw new RuntimeException("Unexpected Stripe URL: $absUrl");
        }

        if (! preg_grep('/^Authorization: Bearer sk_test_/', $headers)) {
            return $this->respond(401, ['error' => ['message' => 'Only sk_test_ keys are accepted by the fake Stripe API.']]);
        }

        $method = strtoupper($method);
        $path = (string) parse_url($absUrl, PHP_URL_PATH);
        parse_str((string) parse_url($absUrl, PHP_URL_QUERY), $query);
        $params = $query + $params;
        $this->requests[] = ['method' => $method, 'path' => $path, 'params' => $params];

        return $this->route($method, array_values(array_filter(explode('/', $path))), $path, $params);
    }

    /**
     * @param  list<string>  $segments
     * @param  array<string, mixed>  $params
     * @return array{0: string, 1: int, 2: CaseInsensitiveArray}
     */
    private function route(string $method, array $segments, string $path, array $params): array
    {
        array_shift($segments);

        if ($segments === ['account']) {
            return $this->respond(200, $this->state['account']);
        }

        if ($method === 'POST' && $segments === ['checkout', 'sessions']) {
            $id = 'cs_test_'.count($this->requests);

            return $this->respond(200, [
                'id' => $id,
                'object' => 'checkout.session',
                'mode' => $params['mode'] ?? null,
                'customer' => $params['customer'] ?? null,
                'customer_email' => $params['customer_email'] ?? null,
                'url' => "https://checkout.stripe.test/c/pay/$id",
            ]);
        }

        if ($method === 'POST' && $segments === ['billing_portal', 'sessions']) {
            $id = 'bps_test_'.count($this->requests);

            return $this->respond(200, [
                'id' => $id,
                'object' => 'billing_portal.session',
                'customer' => $params['customer'] ?? null,
                'url' => "https://billing.stripe.test/p/session/$id",
            ]);
        }

        if ($method === 'GET' && ($segments[0] ?? null) === 'customers' && ($segments[2] ?? null) === 'payment_methods') {
            if (isset($segments[3])) {
                return $this->retrieve($this->state['payment_methods'][$segments[3]] ?? null, $segments[3], $params);
            }

            return $this->list($path, array_filter($this->state['payment_methods'], fn (array $pm) => $pm['customer'] === $segments[1]), $params);
        }

        $collection = $segments[0] ?? '';
        $id = $segments[1] ?? null;

        if (! isset(self::COLLECTIONS[$collection])) {
            return $this->respond(404, ['error' => ['message' => "Unsupported resource $collection"]]);
        }

        $items = $this->state[$collection] ?? [];

        return match (true) {
            $method === 'GET' && $id === null => $this->list($path, $this->filter($items, $params), $params),
            $method === 'GET' => $this->retrieve($items[$id] ?? null, $id, $params),
            $method === 'POST' && $id === null => $this->create($collection, $params),
            $method === 'POST' => $this->update($collection, $id, $params),
            default => $this->delete($collection, $id),
        };
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     * @param  array<string, mixed>  $params
     * @return array<string, array<string, mixed>>
     */
    private function filter(array $items, array $params): array
    {
        foreach (['customer', 'email', 'subscription'] as $filter) {
            if (! empty($params[$filter])) {
                $items = array_filter($items, fn (array $item) => ($item[$filter] ?? null) === $params[$filter]);
            }
        }

        if (($params['status'] ?? 'all') !== 'all') {
            $items = array_filter($items, fn (array $item) => ($item['status'] ?? null) === $params['status']);
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>|null  $object
     * @param  array<string, mixed>  $params
     * @return array{0: string, 1: int, 2: CaseInsensitiveArray}
     */
    private function retrieve(?array $object, string $id, array $params): array
    {
        if ($object === null) {
            return $this->respond(404, ['error' => ['type' => 'invalid_request_error', 'code' => 'resource_missing', 'message' => "No such object: '$id'"]]);
        }

        return $this->respond(200, $this->expand($object, (array) ($params['expand'] ?? [])));
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     * @param  array<string, mixed>  $params
     * @return array{0: string, 1: int, 2: CaseInsensitiveArray}
     */
    private function list(string $url, array $items, array $params): array
    {
        $items = array_values($items);

        if (! empty($params['starting_after'])) {
            $index = array_search($params['starting_after'], array_column($items, 'id'), true);
            $items = $index === false ? [] : array_slice($items, $index + 1);
        }

        $limit = (int) ($params['limit'] ?? 10);
        $page = array_map(fn (array $item) => $this->expand($item, (array) ($params['expand'] ?? [])), array_slice($items, 0, $limit));

        return $this->respond(200, ['object' => 'list', 'url' => $url, 'has_more' => count($items) > $limit, 'data' => $page]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{0: string, 1: int, 2: CaseInsensitiveArray}
     */
    private function create(string $collection, array $params): array
    {
        $id = match ($collection) {
            'customers' => 'cus',
            'webhook_endpoints' => 'we',
            default => 'obj',
        }.'_test_'.count($this->requests);

        $object = ['id' => $id, 'object' => self::COLLECTIONS[$collection], 'created' => time(), 'livemode' => false, 'metadata' => []] + $params;

        if ($collection === 'webhook_endpoints') {
            $object += ['secret' => 'whsec_test_created', 'status' => 'enabled'];
        }

        $this->state[$collection][$id] = $object;

        return $this->respond(200, $object);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{0: string, 1: int, 2: CaseInsensitiveArray}
     */
    private function update(string $collection, string $id, array $params): array
    {
        if (! isset($this->state[$collection][$id])) {
            return $this->retrieve(null, $id, $params);
        }

        // Form-encoded booleans arrive as strings; Stripe stores them as booleans
        array_walk_recursive($params, function (mixed &$value): void {
            if ($value === 'true' || $value === 'false') {
                $value = $value === 'true';
            }
        });

        $this->state[$collection][$id] = array_replace_recursive($this->state[$collection][$id], $params);

        return $this->respond(200, $this->state[$collection][$id]);
    }

    /**
     * @return array{0: string, 1: int, 2: CaseInsensitiveArray}
     */
    private function delete(string $collection, string $id): array
    {
        if (! isset($this->state[$collection][$id])) {
            return $this->retrieve(null, $id, []);
        }

        if ($collection === 'subscriptions') {
            $this->state[$collection][$id]['status'] = 'canceled';
            $this->state[$collection][$id]['canceled_at'] = time();

            return $this->respond(200, $this->state[$collection][$id]);
        }

        unset($this->state[$collection][$id]);

        return $this->respond(200, ['id' => $id, 'object' => self::COLLECTIONS[$collection], 'deleted' => true]);
    }

    /**
     * Expands paths such as `default_price` or `data.default_price` (the `data.` prefix is dropped).
     *
     * @param  array<string, mixed>  $object
     * @param  array<int, string>  $paths
     * @return array<string, mixed>
     */
    private function expand(array $object, array $paths): array
    {
        foreach ($paths as $path) {
            $parts = explode('.', (string) preg_replace('/^data\./', '', $path));
            $first = array_shift($parts);

            if (isset($object[$first]) && is_string($object[$first]) && ($found = $this->find($object[$first]))) {
                $object[$first] = $found;
            }

            if ($parts && isset($object[$first]) && is_array($object[$first])) {
                $object[$first] = $this->expand($object[$first], [implode('.', $parts)]);
            }
        }

        return $object;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function find(string $id): ?array
    {
        foreach (array_keys(self::COLLECTIONS) as $collection) {
            if (isset($this->state[$collection][$id])) {
                return $this->state[$collection][$id];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: int, 2: CaseInsensitiveArray}
     */
    private function respond(int $status, array $data): array
    {
        return [Json::encode($data), $status, new CaseInsensitiveArray(['request-id' => 'req_test'])];
    }
}
