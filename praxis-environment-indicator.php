<?php
/**
 * Praxis Environment Indicator
 *
 * @package   Praxis_Environment_Indicator
 * @author    Praxis Web
 * @copyright 2026 Praxis Web
 * @license   GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Praxis Environment Indicator
 * Plugin URI:        https://github.com/chris-schertenlieb/praxis-environment-indicator
 * Description:       Always know which site you're on. Shows a colour-coded Local / Development / Staging / Production badge in the admin bar, detected from the site's domain so it stays correct after database migrations, and safely redirects outgoing email on non-production environments.
 * Version:           1.0.0
 * Requires at least: 5.7
 * Requires PHP:      7.4
 * Author:            Praxis Web
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       praxis-environment-indicator
 * Update URI:        https://github.com/chris-schertenlieb/praxis-environment-indicator
 */

defined( 'ABSPATH' ) || exit;

define( 'PRAXIS_EI_VERSION', '1.0.0' );
define( 'PRAXIS_EI_FILE', __FILE__ );
define( 'PRAXIS_EI_DIR', plugin_dir_path( __FILE__ ) );
define( 'PRAXIS_EI_URL', plugin_dir_url( __FILE__ ) );

require_once PRAXIS_EI_DIR . 'includes/class-praxis-ei-settings.php';
require_once PRAXIS_EI_DIR . 'includes/class-praxis-ei-environment.php';
require_once PRAXIS_EI_DIR . 'includes/class-praxis-ei-admin-bar.php';
require_once PRAXIS_EI_DIR . 'includes/class-praxis-ei-email-redirect.php';

Praxis_EI_Admin_Bar::init();
Praxis_EI_Email_Redirect::init();

if ( is_admin() ) {
	require_once PRAXIS_EI_DIR . 'includes/class-praxis-ei-settings-page.php';
	Praxis_EI_Settings_Page::init();
}
