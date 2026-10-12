=== Praxis Environment Indicator ===
Contributors: praxisweb
Requires at least: 5.7
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later

Shows which environment you're on in the admin bar, detected from the domain, and redirects email on non-production environments.

== Description ==

A replacement for the discontinued LHL Environment Indicator.

**Environment from the domain, not a stored flag.** Instead of setting "this site is production" (which is wrong as soon as the database is copied to staging), you set up a list of domains and the environment each one is:

* `localhost:3000` → Local
* `abc.test` → Development
* `staging.abc.com` → Staging
* `abc.com` → Production

The list is stored in the database, so it travels with every copy. Each copy looks up its own domain. Domains that don't match anything fall back to a **default environment**.

The list is stored encoded so that migration search-replace (WP Migrate, `wp search-replace`) doesn't rewrite the domains inside it.

**Email redirection.** Choose which environments redirect email. The checkboxes are built from the domain list. Production is listed but greyed out: production email is never redirected. On redirected environments:

* every `wp_mail()` message goes to the redirect address(es) instead
* Cc and Bcc are removed
* the subject becomes `[STAGING → original@recipient.com] Original subject`
* original recipients are kept in `X-Praxis-Original-To/Cc/Bcc` headers
* if no valid redirect address is set, email is blocked rather than delivered

== Matching rules ==

* The domain is taken from the site's home URL (`WP_HOME` / Settings > General), not from the request's Host header, so it can't be spoofed by visitors.
* Scheme, path and a leading `www.` are ignored.
* A rule with a port (`localhost:3000`) only matches that port. A rule without a port matches any port.
* `*.abc.com` matches any subdomain of abc.com. An exact domain always wins over a wildcard.

== Overrides ==

* `define( 'PRAXIS_ENVIRONMENT', 'staging' );` in wp-config.php forces the environment (`local`, `development`, `staging` or `production`) and ignores the domain list.
* Filters: `praxis_ei_current_host`, `praxis_ei_environment_types` (labels/colours), `praxis_ei_redirect_active`.

== Notes ==

Only email sent through `wp_mail()` is redirected. Plugins that call an email API directly (bypassing `wp_mail()`) are not affected.

On a fresh install nothing happens to email until the settings page has been saved.
