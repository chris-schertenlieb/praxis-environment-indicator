<div align="center">

# Praxis Environment Indicator

**Always know which site you're on. Never email real customers from staging.**

A WordPress plugin that shows a colour-coded environment badge in the admin bar and redirects outgoing email on non-production sites. The environment comes from the site's domain, so it stays correct after a database migration.

![WordPress 5.7+](https://img.shields.io/badge/WordPress-5.7%2B-21759b?logo=wordpress&logoColor=white)
![Tested up to 6.8](https://img.shields.io/badge/tested%20up%20to-6.8-21759b)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)
![License GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)

![Local](https://img.shields.io/badge/-LOCAL-2e7d32?style=for-the-badge)
![Development](https://img.shields.io/badge/-DEVELOPMENT-1565c0?style=for-the-badge)
![Staging](https://img.shields.io/badge/-STAGING-e65100?style=for-the-badge)
![Production](https://img.shields.io/badge/-PRODUCTION-c62828?style=for-the-badge)

</div>

---

## Why

Most environment plugins store a flag like "this site is production" in the database. Copy production down to staging and the flag comes with it, so staging now says it's production.

This plugin stores a **list of domains** instead, and each copy of the site looks up its own domain:

| Domain            | Environment                                                               |
| ----------------- | ------------------------------------------------------------------------- |
| `localhost:3000`  | ![Local](https://img.shields.io/badge/-Local-2e7d32)                       |
| `abc.test`        | ![Development](https://img.shields.io/badge/-Development-1565c0)           |
| `staging.abc.com` | ![Staging](https://img.shields.io/badge/-Staging-e65100)                   |
| `abc.com`         | ![Production](https://img.shields.io/badge/-Production-c62828)             |

The same list goes with every copy of the database, and each site picks the right row. A domain that matches no row falls back to a **default environment** that you choose.

> [!NOTE]
> The list is stored base64-encoded so migration search-replace tools (WP Migrate, `wp search-replace`) can't rewrite the domains inside it. Otherwise a production-to-staging pull would turn `abc.com → Production` into `staging.abc.com → Production`.

## Features

- 🏷️ **Admin bar badge** on the front end and in the dashboard, with a coloured strip under the bar.
- 🔎 **Details dropdown** for administrators: current domain, which rule matched, and what's happening to email.
- 🗂️ **Tagged browser tabs**: admin page titles get a prefix like `[STAGING] Dashboard` on non-production sites.
- 🎨 **Coloured tab icon**: on non-production sites the favicon is shrunk and framed in the environment colour, for logged-in users and the login page. Production and logged-out visitors always get the normal icon.
- ✉️ **Email redirection** on the environments you choose. Production is never redirected.
- 🛑 **Fails closed**: if redirection is on but no valid address is set, email is blocked, not delivered.
- 🔒 **Can't be spoofed**: the domain comes from the site's home URL, not the request's `Host` header.
- 🧩 **Overridable** with a `wp-config.php` constant and filters.

## Installation

1. Download or clone this repository into `wp-content/plugins/praxis-environment-indicator`.
2. Activate **Praxis Environment Indicator** in **Plugins**.
3. Open **Settings → Environment Indicator**.
4. Add every domain the site runs on and choose its environment. **Add current domain** fills in the domain you're on.
5. Choose a default environment, tick the environments that should redirect email, enter a redirect address and **Save**.

> [!TIP]
> Set this up on **production** before copying the database to other environments, so every copy inherits the full list.

On a fresh install, email is left alone until the settings page has been saved once.

## Email redirection

On every environment you tick, each `wp_mail()` message is rewritten before it is sent:

| | Before | After |
| --- | --- | --- |
| **To** | `customer@example.com` | your redirect address(es) |
| **Cc / Bcc** | `sales@example.com` | *removed* |
| **Subject** | `Your order #1042` | `[STAGING → customer@example.com] Your order #1042` |
| **Headers** | | `X-Praxis-Original-To`, `X-Praxis-Original-Cc`, `X-Praxis-Original-Bcc` |

Recipients are reset again on `phpmailer_init`, so a plugin that adds addresses directly to PHPMailer can't get around the redirect.

> [!WARNING]
> Only email sent through `wp_mail()` is redirected. Plugins that call an email provider's API directly bypass `wp_mail()` and are **not** affected.

## Matching rules

| Rule                      | Matches                                                        |
| ------------------------- | -------------------------------------------------------------- |
| `abc.com`                 | `abc.com` and `www.abc.com`, on any port                       |
| `localhost:3000`          | `localhost:3000` only                                          |
| `*.abc.com`               | any subdomain of `abc.com`, e.g. `pr-42.abc.com`               |
| `[::1]:8080`              | IPv6 localhost on port 8080                                    |

- Scheme, path, query string and a leading `www.` are ignored, so pasting a full URL works.
- An **exact** domain beats a wildcard. A rule **with a port** beats one without. A **longer** wildcard beats a shorter one.
- The domain is read from `home_url()` (`WP_HOME` / **Settings → General**).

## Developer reference

### Force the environment

Add this to `wp-config.php` to ignore the domain list on a particular install:

```php
define( 'PRAXIS_ENVIRONMENT', 'staging' ); // local | development | staging | production
```

### Filters

#### `praxis_ei_environment_types`

Change the label or colour of an environment. Keys can't be added or removed.

```php
add_filter( 'praxis_ei_environment_types', function ( $types ) {
	$types['staging']['label'] = 'UAT';
	$types['staging']['color'] = '#6a1b9a';
	return $types;
} );
```

#### `praxis_ei_redirect_active`

Turn email redirection on or off for the current request. This can't turn redirection on for production.

```php
add_filter( 'praxis_ei_redirect_active', function ( $active, $env ) {
	return 'local' === $env['key'] ? true : $active;
}, 10, 2 );
```

#### `praxis_ei_current_host`

Change the host that is matched against the domain list, e.g. when the home URL doesn't reflect the real environment.

```php
add_filter( 'praxis_ei_current_host', function ( $host ) {
	return defined( 'MY_PUBLIC_HOST' ) ? MY_PUBLIC_HOST : $host;
} );
```

#### `praxis_ei_tint_favicon`

Turn the coloured tab icon off for the current request. It is never applied on production, whatever this returns.

```php
add_filter( 'praxis_ei_tint_favicon', function ( $tint, $env ) {
	return 'local' === $env['key'] ? false : $tint;
}, 10, 2 );
```

### Body class

Every admin page gets a `praxis-env-{key}` body class (`praxis-env-staging`, `praxis-env-unconfigured`, …) for custom styling.

## Project structure

```
praxis-environment-indicator/
├── praxis-environment-indicator.php         Plugin bootstrap
├── uninstall.php                            Removes the stored option on uninstall
├── readme.txt                               WordPress.org-style readme
├── README.md                                You are here
├── assets/
│   ├── admin.css                            Settings page styles
│   ├── admin.js                             Live domain rows and redirect checkboxes
│   └── favicon.js                           Draws the environment-coloured tab icon
└── includes/
    ├── class-praxis-ei-environment.php      Domain normalising, matching and resolution
    ├── class-praxis-ei-settings.php         Encoded option storage
    ├── class-praxis-ei-admin-bar.php        Admin bar node, styles, title prefix
    ├── class-praxis-ei-email-redirect.php   wp_mail() redirection
    ├── class-praxis-ei-favicon.php          Loads the tab icon script on non-production sites
    └── class-praxis-ei-settings-page.php    Settings → Environment Indicator
```

## Uninstalling

Deleting the plugin from **Plugins** removes its single option, `praxis_ei_settings`. Nothing else is left behind.

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html) © Praxis Web
