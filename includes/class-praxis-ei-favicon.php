<?php
/**
 * Tints the browser tab icon with the environment colour, so tabs for local,
 * staging and production are distinguishable at a glance.
 *
 * The drawing happens client side (assets/favicon.js): it takes whatever icon
 * the page already declares, shrinks it, and puts it on a rounded square in the
 * environment colour.
 */

defined( 'ABSPATH' ) || exit;

final class Praxis_EI_Favicon {

	const HANDLE = 'praxis-ei-favicon';

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_front' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Front end: logged-in users only. Visitors always get the real icon.
	 */
	public static function enqueue_front(): void {
		if ( is_user_logged_in() ) {
			self::enqueue();
		}
	}

	public static function enqueue(): void {
		$env = Praxis_EI_Environment::current();
		// Production keeps its real icon, always.
		if ( null === $env['key'] || 'production' === $env['key'] || ! Praxis_EI_Settings::get()['favicon'] ) {
			return;
		}

		/** Filter whether to tint the favicon on this request. */
		if ( ! apply_filters( 'praxis_ei_tint_favicon', true, $env ) ) {
			return;
		}

		wp_enqueue_script( self::HANDLE, PRAXIS_EI_URL . 'assets/favicon.js', array(), PRAXIS_EI_VERSION, true );
		wp_localize_script(
			self::HANDLE,
			'praxisEIFavicon',
			array(
				'color'    => $env['color'],
				'letter'   => strtoupper( substr( $env['label'], 0, 1 ) ),
				// Used when the page declares no icon link.
				'fallback' => self::fallback_url(),
			)
		);
	}

	private static function fallback_url(): string {
		$url = get_site_icon_url( 64 );
		return $url ? $url : home_url( '/favicon.ico' );
	}
}
