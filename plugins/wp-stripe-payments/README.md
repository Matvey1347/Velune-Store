# CommerceKit Stripe Billing

CommerceKit Stripe Billing is a premium WooCommerce + Stripe plugin for hosted checkout and subscription billing operations.

It combines:
- Stripe checkout and subscription flows
- Local subscription lifecycle records
- Billing history visibility
- Admin setup guidance
- Analytics and logs tooling

## Features

- Stripe Checkout integration for subscription plans
- WooCommerce gateway powered by plugin-managed settings
- Dedicated admin dashboard with configuration health
- Guided Setup page for Stripe onboarding
- Structured Settings page (mode, keys, webhooks, billing portal, logging)
- Customer Subscriptions control center with filters, details, and safe actions
- Billing History tab with filters and summary metrics
- Analytics page for trend and KPI reporting
- Logs page with filtering, search, refresh, and secure clear action
- Subscription Plans custom post type with Stripe sync controls and sync status

## Admin Pages

- `Dashboard`
- `Setup Guide`
- `Settings`
- `Customer Subscriptions`
- `Analytics`
- `Plans`
- `Logs`

## Stripe Setup

### 1) Configure Test Mode

1. Open Stripe Dashboard > Developers > API keys.
2. Copy:
   - Test Publishable Key (`pk_test_...`)
   - Test Secret Key (`sk_test_...`)
3. In plugin Settings, keep Test Mode enabled and save these keys.

### 2) Configure Webhook

1. Use endpoint from Setup Guide / Settings:
   - `REST: /wp-json/wp-stripe-payments/webhook`
2. In Stripe Dashboard > Developers > Webhooks, add endpoint.
3. Subscribe to subscription + invoice events.
4. Copy Signing Secret (`whsec_...`) into plugin Settings.

### 3) Configure Billing Portal (Optional)

1. Enable Stripe Billing Portal in Stripe Dashboard.
2. In plugin Settings, enable Billing Portal actions.
3. Optionally define a custom return URL.

### 4) Test Checkout

1. Create/activate a plan.
2. Sync plan to Stripe.
3. Run checkout in test mode.
4. Verify updates in:
   - Customer Subscriptions
   - Billing History
   - Analytics
   - Logs

### 5) Go Live Safely

1. Add live keys (`pk_live_...`, `sk_live_...`).
2. Configure live webhook endpoint + live webhook secret.
3. Switch mode from Test to Live only after successful test validation.

## Test vs Live Mode

- Test mode uses test API credentials and sandbox payment flows.
- Live mode uses production credentials and real charges.
- Mode switch should be performed only after webhook and checkout are verified.

## Subscription Management Capabilities

Supported admin actions:
- View subscription details
- Cancel at period end
- Resume auto-renew
- Sync latest state from Stripe
- Open billing portal
- Mark for manual review

Not automated intentionally (safety):
- direct manual extension/reactivation mutations without a dedicated controlled workflow

## Analytics and Reporting

Analytics provides:
- Active subscriptions
- New subscriptions in period
- Canceled subscriptions in period
- Renewals in period
- Failed payments in period
- Estimated billed revenue in period
- Daily trend tables for subscriptions and revenue/failures

## Logging

- Local logs stored in option: `wp_stripe_payments_logs`
- Max local records retained: `200`
- Supports filter by level + search
- Secure clear logs action with nonce and capability checks

## Developer Notes

- Existing option key and text domain are preserved for compatibility:
  - Option: `wp_stripe_payments_settings`
  - Text Domain: `wp-stripe-payments`
- Existing DB tables and service architecture are preserved and extended.
- Plan post type key remains unchanged for compatibility.

## Changelog

### 2.0.0 (Portfolio Upgrade)

- Rebranded plugin as **CommerceKit Stripe Billing**
- Added Setup Guide admin page with checklist and copyable values
- Rebuilt Settings UX into clear sections/cards with helper text
- Upgraded Dashboard into KPI + configuration health control center
- Rebuilt Customer Subscriptions admin page with filters, pagination, detail view, and safe actions
- Exposed Billing History in admin with filters and summary stats
- Added Analytics admin page with period controls and trend tables
- Upgraded Logs page with refresh, clear, filtering, and structured context display
- Fixed price formatting bug in plan admin columns (`wc_price` output handling)
- Improved Plans UX with Stripe sync status, manual sync action, and notices
- Added shared admin CSS/JS polish for premium admin experience
- Added stronger nonce/capability guards around admin actions
