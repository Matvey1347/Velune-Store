# Velune — WooCommerce Store + Stripe Subscription Platform

Velune is a portfolio-grade eCommerce project built on WordPress + WooCommerce with:
- a fully custom storefront theme (`themes/Velune`)
- a production-style Stripe billing plugin (`plugins/wp-stripe-payments`)
- local Docker environment for fast setup and demo

This repository is designed to be presented as a complete solution: branded storefront UX, account flow, checkout, and subscription billing operations.

## Portfolio Positioning

This project can be showcased on Upwork in two formats:
- **Full project case**: end-to-end WooCommerce implementation (theme + checkout + subscriptions + admin tooling)
- **Plugin-only case**: reusable Stripe billing plugin with independent install and admin suite

The plugin is intentionally isolated in `plugins/wp-stripe-payments` so it can be shipped and demonstrated separately.

## What Is Implemented

### 1) Custom WooCommerce Theme (`themes/Velune`)
- Custom homepage and branded content sections
- Custom product presentation and refined single-product hooks
- AJAX cart drawer and quantity controls
- Custom account UX (orders/subscriptions/profile focus)
- Frontend auth page bootstrap (login/register/forgot/reset)
- Live search with practical relevance scoring and grouping
- WooCommerce template overrides for key account/order/product areas

### 2) Stripe Billing Plugin (`plugins/wp-stripe-payments`)
- Hosted Stripe Checkout for WooCommerce orders
- Subscription checkout flow for plans
- Webhook-driven sync of subscription and invoice states
- Customer Subscriptions admin center with safe actions
- Billing history storage and filtering
- Analytics dashboard (KPI + trend tables)
- Setup Guide + Settings + Logs pages
- Plan post type with Stripe sync controls

### 3) Local Dev Environment
- `mysql:8.0`
- `wordpress:latest`
- `phpmyadmin:latest`
- Mounted volumes for live theme/plugin development

## Tech Stack

- WordPress (latest docker image)
- WooCommerce
- PHP 8+
- MySQL 8
- Stripe API (Checkout, Billing Portal, Webhooks)
- Vanilla JS + custom CSS (theme and plugin admin UI)
- Docker Compose

## Project Structure

```text
.
├─ docker-compose.yml
├─ mu-plugins/
├─ plugins/
│  ├─ woocommerce/
│  └─ wp-stripe-payments/
└─ themes/
   └─ Velune/
```

## Local Run

### 1. Start services

```bash
docker compose up -d
```

### 2. Open local services

- WordPress: `http://localhost:8000`
- phpMyAdmin: `http://localhost:8181`

### 3. Activate components

In WordPress admin:
1. Activate theme **Velune**
2. Ensure **WooCommerce** is active
3. Activate plugin **CommerceKit Stripe Billing**

## Stripe Subscription Demo Flow

1. Open plugin admin menu `CommerceKit Stripe Billing`
2. In **Settings**, keep Test mode enabled and add Stripe test keys
3. In **Setup Guide**, copy webhook endpoint and configure Stripe webhook
4. Create or edit a plan in **Plans**, then sync to Stripe
5. Open `/subscription/` and run checkout with Stripe test card
6. Verify data in:
   - Customer Subscriptions
   - Billing History
   - Analytics
   - Logs

## Quality and Engineering Focus

- Compatibility-aware plugin architecture (preserved option keys/text domain)
- Nonce/capability checks on sensitive admin actions
- Local DB persistence for subscription and billing records
- Webhook event idempotency handling
- Clear split between domain services, repositories, and UI pages

## Separate Plugin Package

If you need to ship only the plugin as a standalone portfolio item, use:
- `plugins/wp-stripe-payments`

Plugin-specific documentation is maintained here:
- `plugins/wp-stripe-payments/README.md`

## Notes

- This repository includes a local development setup and project assets intended for portfolio demonstration.
- Stripe credentials are not stored in the repository and must be configured per environment.
