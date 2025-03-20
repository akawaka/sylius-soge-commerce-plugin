<p align="center">
    <a href="https://www.akawaka.fr/" target="_blank">
        <img src="https://www.akawaka.fr/build/front/images/logo_akawaka_noir.svg" />
    </a>
</p>

<h1 align="center">AkawakaSyliusSogeCommercePlugin</h1>

<p align="center">Soge commerce payment method plugin.</p>

## Documentation

### Features

This plugin integrates the **SogeCommerce** payment method using ["Smart Forms"](https://sogecommerce.societegenerale.eu/doc/fr-FR/rest/V4.0/javascript/redirection/reference_smartform.html).

Unlike traditional payment gateways, **the payment form is embedded directly on the payment selection page**. This introduces unique challenges, which are detailed in the next section.

### How It Works  

Since the payment form is available on the **payment selection page**, the user is **paying for a cart, not an order**. This differs from the standard Sylius flow, where payment typically occurs **after** the cart has been converted into an order.

#### Payment Flow  

When a payment is made, **SogeCommerce Smart Forms** allows defining a **return URL**. The controller handling this URL performs the following actions:

1. **Verify the authenticity of the request** to prevent fraud.  
2. **Retrieve the cart** and ensure the correct **payment method** is assigned.  
3. **Check if the payment was successful**:
   - **If the payment fails**, the user is redirected back to the payment selection page with a flash message.  
   - **If the payment succeeds**, the cart is **converted into an order**, and the user is redirected to the next checkout step.  

### Differences from a Traditional Sylius Payment Gateway  

In a standard Sylius payment gateway, the process relies on `StatusAction` and `CaptureAction`. These actions are present in this plugin as well but behave **differently** due to the fact that **payment is processed on the payment selection page** rather than after checkout.

#### `CaptureAction`
- This class is required to work.
- **However, its `execute` method does nothing**, since payment is already processed on the payment selection page.

#### `StatusAction`
- This action **marks the payment as captured or failed** based on the bank’s response.
- It is responsible for validating the final payment status.

### Preventing Cart Modifications After Payment  

#### The Problem:  
Since payment happens **before the cart becomes an order**, the user can modify the cart **in another browser tab** after making a payment. This could lead to a mismatch between the **paid amount** and the **actual cart total**.

#### The Solution:  
- The plugin checks whether the **amount paid matches the current cart total**.
- If there is a discrepancy, the payment is **marked as failed**, and the **SogeCommerce API is used to cancel the payment**. The user can still pays for his order since the last payment is failed.

### Handling Failed Payment Cancellations  

If the cancellation request to **SogeCommerce's API** fails (e.g., the API is disabled), the plugin **dispatches a `PaymentCancelationFailedEvent`**.

> ⚠ **Important:** The plugin does **not** listen for this event by default.  

To handle this scenario, you should:
- **Listen for the event** in your Sylius project.
- **Implement appropriate actions**, such as:
  - Retrying the cancellation.
  - Sending an email notification to the administrator.
  - Logging the issue for manual review.

### Summary  

- Payment occurs on the payment selection page, not after checkout.
- Orders are created only after a successful payment.
- The standard Sylius `CaptureAction` is unused, while `StatusAction` validates payments.
- The plugin prevents users from modifying their cart after payment by verifying the paid amount.
- If payment cancellation fails, an event is dispatched, requiring manual handlin

## Install guide

##### 1. Run `composer require akawaka/sylius-soge-commerce-plugin`

##### 2. Change your `config/bundles.php` file to add the line for the plugin :

```php
<?php

return [
    // ...
    Akawaka\SyliusSogeCommercePlugin\AkawakaSyliusSogeCommercePlugin::class => ['all' => true],
    // ...
];
```

##### 3. Import routing in your `config/routes.yaml` file:

```yaml
# config/routes.yaml

akawaka_sylius_soge_commerce_plugin_shop:
    resource: "@AkawakaSyliusSogeCommercePlugin/config/shop_routing.yml"
    prefix: /{_locale}
    requirements:
        _locale: ^[a-z]{2}(?:_[A-Z]{2})?$
```

##### 4. Update sylius templates:

`cp -R vendor/akawaka/sylius-soge-commerce-plugin/tests/Application/templates/* templates/`

You might want to override this plugin templates:

`cp -R vendor/akawaka/sylius-soge-commerce-plugin/templates/* templates/bundles/AkawakaSyliusSogeCommercePlugin/`

> **_NOTE:_**  The content in `tests/Application/templates/bundles/SyliusShopBundle/_scripts.html.twig` should be adapted to your theme and moved to an asset file.

##### 5. That's it !

## Quickstart Installation

Please refer to https://github.com/Sylius/PluginSkeleton/tree/1.14.
