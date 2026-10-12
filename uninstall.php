<?php
/**
 * Remove plugin data on uninstall.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'praxis_ei_settings' );
