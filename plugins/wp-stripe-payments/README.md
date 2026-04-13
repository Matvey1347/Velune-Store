# CommerceKit Stripe Billing (WordPress Plugin)

CommerceKit Stripe Billing is a custom WooCommerce + Stripe plugin for:
- hosted checkout payments
- subscription plan checkout
- local subscription/invoice data sync
- admin operations, analytics, and diagnostics

It is built as a standalone plugin and can be showcased independently in a portfolio.

## Version

- Current version: `2.0.0`
- Main file: `wp-stripe-payments.php`
- Text domain: `wp-stripe-payments`

## Core Capabilities

### Stripe + WooCommerce Payments
- Registers custom WooCommerce gateway (`wp_stripe_gateway`)
- Starts hosted Stripe Checkout sessions for WooCommerce orders
- Handles payment completion/failure updates through webhooks

### Subscription Billing
- Custom subscription plan post type (`wp_sp_sub_plan`)
- Plan metadata (price, interval, image, status, Stripe IDs)
- Plan sync to Stripe product/price
- Hosted Stripe Checkout for subscription signups
- Account endpoint for subscriptions: `/my-account/wp-sp-subscriptions/`

### Webhooks and Sync
- REST webhook route: `/wp-json/wp-stripe-payments/webhook`
- Signature verification using webhook secret
- Processes key Stripe events including:
  - `checkout.session.completed`
  - `customer.subscription.created|updated|deleted`
  - `invoice.paid`, `invoice.payment_failed`, `invoice.finalized`, `invoice.voided`
  - `charge.refunded`
- Event idempotency via processed event cache

### Admin Suite
- Dashboard
- Setup Guide
- Settings
- Customer Subscriptions
- Analytics
- Plans
- Logs

### Safety and Ops Controls
- Nonce + capability checks for admin actions
- Safe actions for subscriptions (cancel/resume/sync/manual review/portal)
- Local structured logs (up to 200 records) + WooCommerce logger integration

## Data Model

On activation, plugin creates and migrates local tables:
- `wp_sp_customer_subscriptions`
- `wp_sp_subscription_invoices`

This allows filtered admin views, analytics KPIs, and billing history timelines without relying only on live API reads.

## Settings Model

Option key:
- `wp_stripe_payments_settings`

Supports:
- test/live API keys
- test/live mode switching
- webhook secret
- billing portal enable/return URL
- debug logging toggle
- gateway title/description

## Installation (Standalone Plugin)

1. Copy folder `wp-stripe-payments` to:
   - `wp-content/plugins/wp-stripe-payments`
2. Ensure WooCommerce is active
3. Activate **CommerceKit Stripe Billing** in WordPress admin
4. Open plugin menu and run setup

## Stripe Setup (Recommended Order)

1. Enable **Test mode** and save `pk_test` + `sk_test`
2. Add webhook endpoint:
   - `/wp-json/wp-stripe-payments/webhook`
3. Subscribe to subscription/invoice/checkout events
4. Save webhook signing secret (`whsec_...`) in plugin settings
5. Create/sync at least one active subscription plan
6. Run test checkout and verify admin records
7. Only then switch to Live mode with live keys + live webhook

## Shortcode and Frontend

Shortcode available:
- `[wp_sp_subscription_plans]`

Use it on a landing page to render subscription plans and start Stripe checkout.

## Architecture Snapshot

- `includes/Core/*` — bootstrap, loader, hooks
- `includes/Gateway/*` — WooCommerce payment gateway
- `includes/Stripe/*` — Stripe API client, checkout/session services, webhook handling
- `includes/Subscriptions/*` — plans, checkout controller, subscription and billing domain logic
- `includes/Admin/*` — admin pages, assets, settings UX
- `includes/Utils/Logger.php` — logging abstraction and local log storage

## Portfolio Notes

This plugin demonstrates practical WordPress product engineering:
- custom billing domain on top of WooCommerce + Stripe
- maintainable modular architecture (services/repositories/controllers)
- secure admin workflows and operational tooling
- merchant-oriented UX (setup guidance, diagnostics, analytics)

## Compatibility

- WordPress + WooCommerce
- PHP `8.0+`
- Stripe account with Checkout + Billing enabled

## Repository Context

In this repository, plugin lives in:
- `plugins/wp-stripe-payments`

A full project-level README (theme + plugin + docker stack) is available in repository root:
- `README.md`
