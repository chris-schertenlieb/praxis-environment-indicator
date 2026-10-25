<?php
/**
 * Plugin Name:       Praxis Environment Indicator
 * Description:       Shows which environment (local, development, staging, production) you are on, detected from the current domain, and redirects outgoing email on non-production environments.
 * Version:           1.0.0
 * Requires at least: 5.7
 * Requires PHP:      7.4
 * Author:            Praxis Web
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       praxis-environment-indicator
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
require_once PRAXIS_EI_DIR . 'includes/class-praxis-ei-favicon.php';

Praxis_EI_Admin_Bar::init();
Praxis_EI_Email_Redirect::init();
Praxis_EI_Favicon::init();

if ( is_admin() ) {
	require_once PRAXIS_EI_DIR . 'includes/class-praxis-ei-settings-page.php';
	Praxis_EI_Settings_Page::init();
}
