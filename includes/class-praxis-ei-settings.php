<?php
/**
 * Settings storage.
 *
 * The whole settings array is stored as base64-encoded JSON. This is deliberate:
 * migration tools (WP Migrate, WP-CLI search-replace, etc.) rewrite every
 * occurrence of the old domain in the database. If the domain map were stored
 * in plain text, "abc.com => production" would be rewritten to
 * "staging.abc.com => production" during a prod-to-staging pull, which is
 * exactly the problem this plugin exists to solve. Encoding keeps the domain
 * strings invisible to search-replace.
 */

defined( 'ABSPATH' ) || exit;

final class Praxis_EI_Settings {

	const OPTION = 'praxis_ei_settings';

	/** @var array|null */
	private static $cache = null;

	/**
	 * Default settings. `configured` stays false until the settings page has
	 * been saved once, so a fresh install never changes mail behaviour.
	 */
	public static function defaults(): array {
		return array(
			'configured'    => false,
			'mappings'      => array(),
			'default_env'   => 'staging',
			'redirect_envs' => array(),
			'redirect_to'   => '',
			'favicon'       => true,
		);
	}

	public static function get(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$settings = self::defaults();
		$raw      = get_option( self::OPTION, '' );

		if ( is_string( $raw ) && '' !== $raw ) {
			$json = base64_decode( $raw, true );
			$data = false !== $json ? json_decode( $json, true ) : null;
			if ( is_array( $data ) ) {
				$settings = array_merge( $settings, array_intersect_key( $data, $settings ) );
			}
		}

		self::$cache = $settings;
		return $settings;
	}

	public static function save( array $settings ): void {
		$settings = array_merge( self::defaults(), array_intersect_key( $settings, self::defaults() ) );
		update_option( self::OPTION, base64_encode( wp_json_encode( $settings ) ), true );
		self::$cache = null;
	}
}
