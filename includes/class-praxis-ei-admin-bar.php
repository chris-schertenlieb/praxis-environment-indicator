<?php
/**
 * Admin bar indicator.
 */

defined( 'ABSPATH' ) || exit;

final class Praxis_EI_Admin_Bar {

	public static function init(): void {
		add_action( 'admin_bar_menu', array( __CLASS__, 'add_node' ), 0 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'admin_title', array( __CLASS__, 'admin_title' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );
	}

	public static function add_node( WP_Admin_Bar $bar ): void {
		$env   = Praxis_EI_Environment::current();
		$admin = current_user_can( 'manage_options' );

		$bar->add_node(
			array(
				'id'    => 'praxis-ei',
				'title' => '<span class="ab-label">' . esc_html( $env['label'] ) . '</span>',
				'href'  => $admin ? Praxis_EI_Settings_Link::url() : false,
				'meta'  => array( 'class' => 'praxis-ei-node' ),
			)
		);

		if ( ! $admin ) {
			return;
		}

		$details = array();

		$details['host'] = sprintf(
			/* translators: %s: current host */
			__( 'Domain: %s', 'praxis-environment-indicator' ),
			'' !== $env['host'] ? $env['host'] : '—'
		);

		switch ( $env['source'] ) {
			case 'constant':
				$details['source'] = __( 'Set by PRAXIS_ENVIRONMENT constant', 'praxis-environment-indicator' );
				break;
			case 'mapping':
				/* translators: %s: matching domain rule */
				$details['source'] = sprintf( __( 'Matched rule: %s', 'praxis-environment-indicator' ), $env['rule'] );
				break;
			case 'default':
				$details['source'] = __( 'No rule matched — using default', 'praxis-environment-indicator' );
				break;
			default:
				$details['source'] = __( 'Not configured yet — click to set up', 'praxis-environment-indicator' );
		}

		$details['email'] = Praxis_EI_Email_Redirect::status_text();

		foreach ( $details as $id => $text ) {
			$bar->add_node(
				array(
					'parent' => 'praxis-ei',
					'id'     => 'praxis-ei-' . $id,
					'title'  => esc_html( $text ),
					'href'   => Praxis_EI_Settings_Link::url(),
				)
			);
		}
	}

	public static function enqueue(): void {
		if ( ! is_admin_bar_showing() ) {
			return;
		}

		$color = Praxis_EI_Environment::current()['color'];
		if ( ! preg_match( '/^#[0-9a-f]{3,8}$/i', $color ) ) {
			$color = '#646970';
		}

		$css = "
#wpadminbar { border-bottom: 5px solid {$color}; box-sizing: content-box; }
#wpadminbar #wp-admin-bar-praxis-ei > .ab-item { background: {$color}; color: #fff; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
#wpadminbar #wp-admin-bar-praxis-ei:hover > .ab-item,
#wpadminbar #wp-admin-bar-praxis-ei.hover > .ab-item { background: {$color}; color: #fff; filter: brightness(1.15); }
@media screen and (max-width: 782px) {
	#wpadminbar #wp-admin-bar-praxis-ei { display: block; }
	#wpadminbar #wp-admin-bar-praxis-ei > .ab-item { padding: 0 8px; font-size: 12px; }
}";

		wp_add_inline_style( 'admin-bar', $css );
	}

	/**
	 * Prefix admin page titles on non-production environments, so browser tabs
	 * for different environments are easy to tell apart.
	 */
	public static function admin_title( $title ) {
		$env = Praxis_EI_Environment::current();
		if ( null === $env['key'] || 'production' === $env['key'] ) {
			return $title;
		}
		return '[' . strtoupper( $env['label'] ) . '] ' . $title;
	}

	public static function admin_body_class( $classes ) {
		$key = Praxis_EI_Environment::current()['key'];
		return $classes . ' praxis-env-' . ( $key ? $key : 'unconfigured' );
	}
}

/**
 * Tiny helper so the settings URL is available on the front end too, where the
 * settings page class isn't loaded.
 */
final class Praxis_EI_Settings_Link {
	const SLUG = 'praxis-environment-indicator';

	public static function url(): string {
		return admin_url( 'options-general.php?page=' . self::SLUG );
	}
}
