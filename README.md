# Stripe for Craft CMS

Connect your Craft content to [Stripe](https://stripe.com)’s powerful billing tools, and build a streamlined storefront.

## Requirements

This plugin requires Craft CMS 5.1.0 or later, and a Stripe account with access to developer features.

> [!TIP]
> Transitioning from Craft Commerce? Check out the dedicated [migration](#migrating-from-commerce) section.

## Installation

You can install this plugin via the in-app [Plugin Store](#plugin-store) or with [the command line](#composer).

### Plugin Store

Visit the **Plugin Store** screen of your installation’s control panel, then search for **Stripe**.

Click the **Install** button, then check out the [configuration](#configuration) instructions!

### Composer

These instructions assume you are using DDEV, but you can run similar commands in other environments. Open up a terminal and run…

```bash
# Navigate to the project directory:
cd /path/to/my-project

# Require the plugin with Composer:
ddev composer require craftcms/stripe

# Install the plugin with Craft:
ddev craft plugin/install stripe
```

## Configuration

The Stripe plugin builds its configuration from three sources:

- [Project config](https://craftcms.com/docs/5.x/system/project-config.html) — Managed via the **Stripe** &rarr; **Settings** screen in Craft’s control panel.
- **A plugin config file** — Add a `config/stripe.php` file to your project and return a map of options keyed with properties from the `craft\stripe\models\Settings` class.
- **Environment variables** — Some options can be set directly as environment variables.

### API Keys

Stripe uses a pair of “publishable” and “secret” keys to communicate with their API. In your Stripe account, switch into **Test mode**, then visit the **Developer** section and grab your development keys.

> [!NOTE]
> Read more about [Stripe API Keys](https://docs.stripe.com/keys).

Add these keys to your project’s `.env` file:

```bash
STRIPE_PUBLISHABLE_KEY="pk_test_************************"
STRIPE_SECRET_KEY="sk_test_************************"
```

Then, in the control panel, visit **Stripe** &rarr; **Settings**, and enter the variable names you chose, in the respective fields. Craft will provide suggestions based on what it discovers in the environment.

### Webhooks

[Webhooks](https://docs.stripe.com/webhooks) are essential for the plugin to work correctly—they allow changes to product data and off-platform customer activity to be rapidly synchronized into your site.

> [!TIP]
> Be sure and perform an initial [synchronization](#synchronization) to import existing Stripe data.

To test webhooks in your local development environment, we recommend using the [Stripe CLI](https://docs.stripe.com/stripe-cli) to create a tunnel and forward events. Follow the installation instructions for your platform, then run:

```bash
stripe listen --forward-to https://my-craft-project.ddev.site/stripe/webhooks/handle
```

> [!NOTE]
> The hostname you provide here should agree with how you access the project, locally—Stripe does not need to be able to resolve it on the public internet for testing webhooks to be delivered!

The CLI will let you know when it’s ready, and output a webhook signing secret starting with `whsec_`. Add this value to your `.env` file, and return to the **Stripe** &rarr; **Settings** in the control panel

### Synchronization

Webhooks keep product and customer data synchronized between Stripe and Craft, but they will only report _changes_.

You have two options for doing an initial import of Stripe data:

- Small product catalogs can usually get away with using the control panel utility: visit **Utilities** &rarr; **Stripe Sync All** and click **Sync all data**.
- Large catalogs should perform synchronizations via the command line: Run `ddev craft stripe/sync/all` if you’re just getting started, or use one of the finer-grained [CLI tools](#cli) to import specific types of records.

### Content + Fields

Stripe [products](https://docs.stripe.com/products-prices/overview), [prices](https://docs.stripe.com/products-prices/how-products-and-prices-work#what-is-a-price), and [subscriptions](https://docs.stripe.com/subscriptions) are all stored as _elements_ in Craft. This means that they have access to the full suite of content modeling tools you would expect!

Field layouts for each element type are managed in the plugin’s **Settings** screen.

### Product URLs

In addition to a field layout, product elements support **URI Format** and **Template** settings, which work just like they do on other element types: when a product’s URL is requested, Craft loads the element and passes it to the specified template under a `product` variable.

> [!NOTE]
> Prices and subscriptions do _not_ have their own URLs. You can use query parameters or [custom routes](https://craftcms.com/docs/5.x/system/routing.html) to load those elements in response to specific URI patterns.

## Migrating from [Craft Commerce](https://craftcms.com/commerce)

Users of our full-featured ecommerce system, _Craft Commerce_ can migrate existing subscriptions to the standalone Stripe plugin without losing any customer data.

Once you have fully upgraded to Craft 5.1 and Craft Commerce 5.0, follow the normal [installation](#installation) and [configuration](#configuration) instructions, above. Then, run this pair of console commands:

```bash
# Pre-populate plugin tables with existing Stripe data:
ddev craft stripe/commerce/migrate

# Perform a synchronization to bring in additional records:
ddev craft stripe/sync/all
```

### API Changes

You will interact with subscriptions differently than in Craft Commerce, as they have shifted to more closely resemble Stripe’s billing architecture than the legacy single-item “plans”:

- Plans are not configured in Craft. Instead, products (or more accurately, _prices_) can be set up in Stripe as _recurring_. You will see this reflected as a combination of price and interval (i.e. $5.00/day) in **Prices** tables on an individual product element, in the control panel.
- Some gateway-agnostic element query methods were not translated into the Stripe plugin:
  - `dateExpired()`: Not tracked as a native property. You can access the timestamp when a subscription ended with `subscription.data.ended_at`.
  - `isExpired()`: Similar to the above, non-expired subscriptions will have a `null` `subscription.data.ended_at` value.
  - `trialDays()`: Use `subscription.data.trial_start` and `trial_end`, or access the subscription’s underlying `items` array for info about each recurring item’s price and configuration.
  - `status()`: Statuses may not behave in a way that is consistent with Craft Commerce’s definition.

---

## Storefront

Once you have populated your Craft project with data from Stripe (via [synchronization](#synchronization) and/or [webhooks](#webhooks)), you can begin scaffolding your content and storefront.

> [!TIP]
> The [reference section](#reference) has information about each type of object you’re apt to encounter in the system.

### Listing Products

Individual products automatically get URLs based on their [URI format](#product-urls), but it is up to you how they are gathered and displayed.

To get a list of products, use the `craft.stripeProducts` [element query](https://craftcms.com/docs/5.x/development/element-queries.html) factory:

```twig
{% set products = craft.stripeProducts.all() %}

<ul>
  {% for product in products %}
    {% set image = product.featureImage.eagerly().one() %}

    <li>
      <figure>
        {{ image.getImg() }}
      </figure>

      <strong>{{ product.getLink() }}</strong>
    </li>
  {% endfor %}
</ul>
```

### The Product Template

On an individual product’s page, Craft provides the current product under a `product` variable:

```twig
<h1>{{ product.title }}</h1>
```

Any [custom fields](#content--fields) you’ve configured for products will be available as properties, just as they are for other element types:

```twig
{{ product.customDescriptionField|md }}
```

### Prices

Like Craft Commerce, Stripe uses “products” as a means of logically grouping goods and services—the things your customers actually buy are called “prices.”

The Stripe plugin handles this relationship using [nested elements](https://craftcms.com/docs/5.x/system/elements.html). Each product element will own one or more price elements, and expose them via a `prices` property or `getPrices()` method:

```twig
<h1>{{ product.title }}</h1>

<ul>
  {% for price in product.prices %}
    <li>
      {{ price.data|unitAmount }}
      {{ tag('a', {
        text: "Buy now",
        href: price.getCheckoutUrl(
          currentUser ?? false,
          'shop/thank-you?session={CHECKOUT_SESSION_ID}',
          product.url,
          {}
        ),
      }) }}
    </li>
  {% endfor %}
</ul>
```

### Checkout Links

When a customer is ready to buy a product or start a subscription, you’ll provide a _checkout link_. Checkout links are special, secure, parameterized URLs that exist as part of a [checkout session](https://docs.stripe.com/payments/checkout) for a pre-configured list of items. Stripe has no “cart,” per se; instead, products are purchased piecemeal.

Clicking a checkout link takes the customer to Stripe’s hosted checkout page, where they can complete a payment using whatever methods are available and enabled in your account.

To output a checkout link, use the `stripeCheckoutUrl()` function:

```twig
{% set price = product.prices.one() %}

{{ tag('a', {
  href: stripeCheckoutUrl(
    [
      {
        price: price.stripeId,
        quantity: 1,
      },
    ],
    currentUser ?? false,
    'shop/thank-you?session={CHECKOUT_SESSION_ID}',
    product.url,
    {}
  ),
  text: 'Checkout',
}) }}
```

> [!TIP]
> Passing `false` as the second parameter to the `stripeCheckoutUrl()` allows you to create an anonymous checkout URL.

### Checkout Form

As an alternative to generating static Checkout links, you can build a [form](https://craftcms.com/docs/5.x/development/forms.html) that sends a list of items and other params to Craft, which will create a checkout session on-the-fly, then redirect the customer to the Stripe-hosted checkout page:

```twig
{% set prices = product.prices.all() %}

<form method="post">
  {{ csrfInput() }}
  {{ actionInput('stripe/checkout') }}
  {{ hiddenInput('successUrl', 'shop/thank-you?session={CHECKOUT_SESSION_ID}'|hash) }}
  {{ hiddenInput('cancelUrl', 'shop'|hash) }}
  {% if not currentUser %}
    {{ hiddenInput('customer', 'false') }}
  {% endif %}

  <select name="lineItems[0][price]">
    {% for price in prices %}
      <option value="{{ price.stripeId }}">{{ price.data|unitAmount }}</option>
    {% endfor %}
  </select>

  <input type="text" name="lineItems[0][quantity]" value="1">

  <button>Buy now</button>
</form>
```

> [!TIP]
> By default, the currently logged-in user will be used.
> 
> To allow an anonymous checkout, you can add `{{ hiddenInput('customer', 'false') }}` to the form.

If you use this method, you can pass custom field values that should be saved in Craft against a newly created Subscription. These values need to be passed as `fields[<fieldHandle>]`. For example:
```
<input type="text" name="fields[myPlainTextField]">
```


### Billing Portal

Customers can manage their subscriptions and payment methods via Stripe’s hosted [billing portal](https://docs.stripe.com/customer-management). You can generate a URL to a customer’s billing portal using the `currentUser.getStripeBillingPortalSessionUrl()` method:

```twig
{{ tag('a', {
  text: "Billing Portal",
  href: currentUser.getStripeBillingPortalSessionUrl('shop'),
}) }}
```

The method takes a `returnUrl` parameter that specifies the URL to redirect the customer to after they have finished managing their subscriptions and payment methods.

In addition to this method, there is also `currentUser.getStripeBillingPortalSessionPaymentMethodUpdateUrl()`, which generates a URL for the customer to update their default payment method.

```twig
{{ tag('a', {
  text: "Update Payment Method",
  href: currentUser.getStripeBillingPortalSessionPaymentMethodUpdateUrl('shop'),
}) }}
```

This uses the Stripe [flow type](https://docs.stripe.com/customer-management/portal-deep-links#flow-types) to deep link directly to the payment method update screen.

### Element API

Our [Element API](https://plugins.craftcms.com/element-api) plugin works great with Stripe! All three plugin-provided element types (products, prices, and subscriptions) can be used in your `element-api.php` config file:

```php
return [
    'endpoints' => [
        'api/products' => function() {
            return [
                'elementType' => craft\stripe\elements\Product::class,
                // ...
            ];
        },
    ],
];
```

## Other Features

### Product Field

Create a **Stripe Products** field and add it to a field layout to [relate](https://craftcms.com/docs/5.x/system/relations.html) product elements to other content throughout the system.

### Direct API Access

The plugin exposes its Stripe API client for advanced usage. In Twig, you would access it via `craft.stripe.api.client`:

```twig
{% set client = craft.stripe.api.client %}
{% set checkout = client
  .getService('checkout')
  .getService('sessions')
  .retrieve('cs_test_****************************************') %}
```

In PHP, you can make the equivalent call like this:

```php
$client = craft\stripe\Plugin::getInstance()->getApi()->getClient();

$checkout = $client
    ->checkout
    ->sessions
    ->retrieve('cs_test_****************************************');
```

> [!WARNING]
> We cannot provide support for customizations that involve direct use of Stripe APIs. If you find yourself needing access to specific APIs during the course of your project, consider [starting a discussion](https://github.com/craftcms/stripe/discussions)!

## Tips, Troubleshooting, FAQ

### Where do I change a product’s title?

Product and price titles are kept in sync with Stripe to make them easily identifiable in both spaces.

If you would like to customize product names in Craft, create a [plain text field](https://craftcms.com/docs/5.x/reference/field-types/plain-text.html) and add it to the product [field layout](#content--fields). Stripe will only ever display the canonical title at checkout or on invoices, so it is important you have a way for customers to identify which products are which—and not re-use product definitions for other goods.

### I can’t create a webhook.

If Craft can't write to a `.env` file in the project root, you may need to manually create a webhook in the Stripe dashboard, then expose it to the environment:

```bash
STRIPE_WH_ID="we_************************"
STRIPE_WH_KEY="whsec_**************************************************************"
```

> [!WARNING]
> In this case, the environment variable names are strict!

---

## Reference

### Twig Filters

The plugin provides four new [Twig filters](https://craftcms.com/docs/5.x/reference/twig/filters.html):

- `unitPrice` — Takes a [price](#prices) element’s Stripe `data` array and outputs a formatted expression of its cost _and_ interval: `£10.50 per unit/month`
- `pricePerUnit` — Similar to the above, but only outputs the _cost_ component, without the interval: `Starts at $5.00 per unit + $20.00`
- `unitAmount` — Similar to the above, but only outputs the _unit_ component, i.e. `$13.00 per group of 10`.
- `interval` — Similar to the above, but only outputs the _interval_ component, i.e. `One-time` or `Every 1 month`.

In most cases, you will want to use the `unitPrice` filter, as it will provide the most complete information about a price. All filters should be passed the price’s `data` property, which is the raw [Price object](https://docs.stripe.com/api/prices/object) from Stripe:

```twig
{{ price.data|unitPrice }}
```

### CLI

To view all available console commands, run `ddev craft help`. The Stripe plugin adds two main groups of commands:

#### Craft Commerce Migration

Migrates [preexisting Craft Commerce subscriptions](#migrating-from-craft-commerce) to records compatible with the Stripe plugin.

```bash
ddev craft stripe/commerce/migrate
```

#### Atomic Synchronization

You can synchronize _everything_ at once…

```bash
ddev craft stripe/sync/all
```

…or just pull in slices of it:

- **Customers**: `ddev craft stripe/sync/customers`
- **Invoices**: `ddev craft stripe/sync/invoices`
- **Payment Methods**: `ddev craft stripe/sync/payment-methods`
- **Products _and_ Prices**: `ddev craft stripe/sync/products-and-prices`
- **Subscriptions**: `ddev craft stripe/sync/subscriptions`

---

## Extending

### Synchronization Events

The Stripe plugin emits events just before updating each product, price, or subscription element during a [synchronization](#synchronization). A synchronization may occur via the CLI, control panel utility, or in response to a webhook.

Class + Event | Event Model | `$source`
--- | --- | ---
`craft\stripe\services\Products::EVENT_BEFORE_SYNCHRONIZE_PRODUCT` | `craft\stripe\events\StripeProductSyncEvent` | [Product](https://docs.stripe.com/api/products/object)
`craft\stripe\services\Prices::EVENT_BEFORE_SYNCHRONIZE_PRICE` | `craft\stripe\events\StripePriceSyncEvent` | [Price](https://docs.stripe.com/api/prices/object)
`craft\stripe\services\Subscriptions::EVENT_BEFORE_SYNCHRONIZE_SUBSCRIPTION` | `craft\stripe\events\StripeSubscriptionSyncEvent` | [Subscription](https://docs.stripe.com/api/subscriptions/object)

```php
craft\base\Event::on(
    craft\stripe\services\Products::class,
    craft\stripe\services\Products::EVENT_BEFORE_SYNCHRONIZE_PRODUCT,
    function(craft\stripe\events\StripeProductSyncEvent $event) {
        // Set a custom field value when a product looks “shippable”:
        if ($event->source->package_dimensions !== null) {
            $event->element->setFieldValue('requiresShipping', true);
        }
    },
);
```

You can set `$event->isValid` to prevent updates from being persisted during the synchronization.

### Checkout Events

Customize the parameters sent to Stripe when generating a Checkout session by listening to the `craft\stripe\services\Checkout::EVENT_BEFORE_START_CHECKOUT_SESSION` event. The `craft\stripe\events\CheckoutSessionEvent` will have a `params` property containing the request data that is about to be sent with the Stripe API client. You may modify or extend this data to suit your application—whatever the value of the property is after all handlers have been invoked is passed verbatim to the API client:

```php
craft\base\Event::on(
    craft\stripe\services\Checkout::class,
    craft\stripe\services\Checkout::EVENT_BEFORE_START_CHECKOUT_SESSION,
    function(craft\stripe\events\CheckoutSessionEvent $event) {
        // Add metadata if the customer is a logged-in “member”:
        $currentUser = Craft::$app->getUser()->getIdentity();

        // Nothing to do:
        if (!$currentUser) {
            return;
        }

        if ($currentUser->isInGroup('members')) {
            // Memoize + assign values:
            $data = $event->params;
            $data['metadata']['is_member'] = true;

            // Set back onto the event:
            $event->params = $data;
        }
    },
);
```
