=== Praxis Environment Indicator ===
Contributors: praxisweb
Tags: environment, staging, admin bar, email, development
Requires at least: 5.7
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

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

**Admin bar indicator.** A colour-coded badge in the admin bar (front end and dashboard) with a matching strip under the bar. Administrators get a dropdown showing the current domain, which rule matched, and what is happening to email. Admin page titles are prefixed with the environment (e.g. `[STAGING] Dashboard`) on non-production sites so browser tabs are easy to tell apart.

**Email redirection.** Choose which environments redirect email. The checkboxes are built from the domain list. Production is listed but greyed out: production email is never redirected. On redirected environments:

* every `wp_mail()` message goes to the redirect address(es) instead
* Cc and Bcc are removed
* the subject becomes `[STAGING → original@recipient.com] Original subject`
* original recipients are kept in `X-Praxis-Original-To/Cc/Bcc` headers
* if no valid redirect address is set, email is blocked rather than delivered

**Tab icon.** On non-production environments the site's favicon is shrunk and framed in the environment colour, so you can tell local, development and staging tabs apart at a glance. Production always keeps its normal icon. This applies only to logged-in users and the login page, never to visitors. If the icon can't be read (e.g. a cross-origin CDN without CORS), the frame shows the environment's initial letter instead. You can turn it off in settings.

= Matching rules =

* The domain is taken from the site's home URL (`WP_HOME` / Settings > General), not from the request's Host header, so it can't be spoofed by visitors.
* Scheme, path and a leading `www.` are ignored.
* A rule with a port (`localhost:3000`) only matches that port. A rule without a port matches any port.
* `*.abc.com` matches any subdomain of abc.com. An exact domain always wins over a wildcard.

= Overrides =

* `define( 'PRAXIS_ENVIRONMENT', 'staging' );` in wp-config.php forces the environment (`local`, `development`, `staging` or `production`) and ignores the domain list.
* Filters: `praxis_ei_current_host`, `praxis_ei_environment_types` (labels/colours), `praxis_ei_redirect_active`, `praxis_ei_tint_favicon`.

== Installation ==

1. Upload the `praxis-environment-indicator` folder to `/wp-content/plugins/`, or install the zip from Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Go to Settings > Environment Indicator.
4. Add every domain the site runs on and pick its environment. "Add current domain" fills in the domain you're on.
5. Choose a default environment, tick the environments that should redirect email, enter a redirect address and save.

Do this on production before copying the database to other environments, so every copy inherits the same list.

== Frequently Asked Questions ==

= Nothing happens to email after activating. Is it working? =

Yes. On a fresh install email is left alone until the settings page has been saved once.

= Will a database pull from production break the staging indicator? =

No. The domain list is stored encoded so search-replace can't rewrite it, and the environment is worked out from each site's own home URL.

= Can email redirection be turned on for production? =

No. Production email is always delivered normally, whatever is stored in the settings or returned by the `praxis_ei_redirect_active` filter.

= Does it catch every email? =

Only email sent through `wp_mail()`. Plugins that call an email API directly (bypassing `wp_mail()`) are not affected.

= Who sees the indicator? =

Anyone who can see the admin bar sees the badge. Only administrators (`manage_options`) see the details dropdown and the settings link.

The coloured tab icon is shown to logged-in users and on the login page. Logged-out visitors always see the site's normal icon.

== Changelog ==

= 1.0.0 =
* Initial release: domain-based environment detection, admin bar indicator, environment-coloured tab icon, email redirection and settings page.
