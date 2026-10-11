<?php
/**
 * Environment detection: maps the current site domain to an environment type
 * using the domain map stored in settings.
 */

defined( 'ABSPATH' ) || exit;

final class Praxis_EI_Environment {

	/** @var array|null */
	private static $current = null;

	/**
	 * Environment types, in display order.
	 */
	public static function types(): array {
		$types = array(
			'local'       => array(
				'label' => __( 'Local', 'praxis-environment-indicator' ),
				'color' => '#2e7d32',
			),
			'development' => array(
				'label' => __( 'Development', 'praxis-environment-indicator' ),
				'color' => '#1565c0',
			),
			'staging'     => array(
				'label' => __( 'Staging', 'praxis-environment-indicator' ),
				'color' => '#e65100',
			),
			'production'  => array(
				'label' => __( 'Production', 'praxis-environment-indicator' ),
				'color' => '#c62828',
			),
		);

		/**
		 * Filter the label/colour of environment types. Keys cannot be added
		 * or removed; only label and color are honoured.
		 */
		$filtered = apply_filters( 'praxis_ei_environment_types', $types );
		foreach ( $types as $key => $type ) {
			if ( isset( $filtered[ $key ] ) && is_array( $filtered[ $key ] ) ) {
				$types[ $key ] = array_merge( $type, array_intersect_key( $filtered[ $key ], $type ) );
			}
		}

		return $types;
	}

	public static function is_valid_type( $key ): bool {
		return is_string( $key ) && array_key_exists( $key, self::types() );
	}

	/**
	 * The current site host (with port if non-standard), e.g. "abc.com" or
	 * "localhost:3000".
	 *
	 * Uses home_url() rather than $_SERVER['HTTP_HOST'] because the Host header
	 * is client-controlled: on production, a spoofed Host must not be able to
	 * flip the site into "staging" and redirect someone's password reset email.
	 * home_url() honours WP_HOME and is updated by migration search-replace.
	 */
	public static function current_host(): string {
		$parts = wp_parse_url( home_url() );
		$host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
		if ( '' !== $host && isset( $parts['port'] ) ) {
			$host .= ':' . (int) $parts['port'];
		}

		return (string) apply_filters( 'praxis_ei_current_host', $host );
	}

	/**
	 * Resolve the current environment.
	 *
	 * @return array {
	 *     @type string|null $key    Environment type key, or null if unconfigured.
	 *     @type string      $label
	 *     @type string      $color
	 *     @type string      $source One of 'constant', 'mapping', 'default', 'unconfigured'.
	 *     @type string      $host   Host that was matched against.
	 *     @type string      $rule   The matching domain rule (source 'mapping' only).
	 * }
	 */
	public static function current(): array {
		if ( null !== self::$current ) {
			return self::$current;
		}

		$host     = self::current_host();
		$settings = Praxis_EI_Settings::get();
		$key      = null;
		$source   = 'unconfigured';
		$rule     = '';

		if ( defined( 'PRAXIS_ENVIRONMENT' ) && self::is_valid_type( PRAXIS_ENVIRONMENT ) ) {
			$key    = PRAXIS_ENVIRONMENT;
			$source = 'constant';
		} elseif ( $settings['configured'] ) {
			$match = self::match( $host, $settings['mappings'] );
			if ( $match && self::is_valid_type( $match['env'] ) ) {
				$key    = $match['env'];
				$source = 'mapping';
				$rule   = $match['domain'];
			} elseif ( self::is_valid_type( $settings['default_env'] ) ) {
				$key    = $settings['default_env'];
				$source = 'default';
			}
		}

		if ( null === $key ) {
			$env = array(
				'key'    => null,
				'label'  => __( 'Environment not set', 'praxis-environment-indicator' ),
				'color'  => '#646970',
				'source' => 'unconfigured',
				'host'   => $host,
				'rule'   => '',
			);
		} else {
			$types = self::types();
			$env   = array(
				'key'    => $key,
				'label'  => $types[ $key ]['label'],
				'color'  => $types[ $key ]['color'],
				'source' => $source,
				'host'   => $host,
				'rule'   => $rule,
			);
		}

		self::$current = $env;
		return $env;
	}

	public static function is_production(): bool {
		return 'production' === self::current()['key'];
	}

	/**
	 * Normalise a user-entered domain: strips scheme, credentials, path, query,
	 * trailing dots, and lowercases. Keeps the port and a leading "*." wildcard.
	 * Returns '' if nothing usable remains.
	 */
	public static function normalize_domain( string $domain ): string {
		$domain = strtolower( trim( $domain ) );
		$domain = preg_replace( '#^[a-z][a-z0-9+.\-]*://#', '', $domain );
		$domain = preg_replace( '#[/?\#].*$#', '', $domain );
		$domain = preg_replace( '#^.*@#', '', $domain );
		$domain = rtrim( $domain, '.' );

		if ( ! preg_match( '#^(\*\.)?[a-z0-9.\-\[\]:]+$#', $domain ) ) {
			return '';
		}

		return $domain;
	}

	/**
	 * Find the best rule for $host.
	 *
	 * Precedence: exact host (ignoring a leading "www.") beats wildcard; a rule
	 * that specifies the port beats one that doesn't; longer wildcards beat
	 * shorter ones. A rule with a port only matches that port; a rule without a
	 * port matches any port.
	 *
	 * @param string $host     Current host, e.g. "localhost:3000".
	 * @param array  $mappings List of [ 'domain' => ..., 'env' => ... ].
	 * @return array|null The winning mapping.
	 */
	public static function match( string $host, array $mappings ): ?array {
		list( $host_name, $host_port ) = self::split_port( self::normalize_domain( $host ) );
		if ( '' === $host_name ) {
			return null;
		}
		$host_bare = self::strip_www( $host_name );

		$best       = null;
		$best_score = 0;

		foreach ( $mappings as $mapping ) {
			if ( empty( $mapping['domain'] ) || empty( $mapping['env'] ) ) {
				continue;
			}

			list( $rule_name, $rule_port ) = self::split_port( (string) $mapping['domain'] );
			if ( '' !== $rule_port && $rule_port !== $host_port ) {
				continue;
			}

			$score = 0;
			if ( 0 === strpos( $rule_name, '*.' ) ) {
				$suffix = substr( $rule_name, 1 ); // ".abc.com".
				if ( strlen( $host_name ) > strlen( $suffix ) && substr( $host_name, -strlen( $suffix ) ) === $suffix ) {
					$score = 10 + strlen( $suffix );
				}
			} elseif ( self::strip_www( $rule_name ) === $host_bare ) {
				$score = 10000;
			}

			if ( $score > 0 && '' !== $rule_port ) {
				$score += 1000;
			}

			if ( $score > $best_score ) {
				$best       = $mapping;
				$best_score = $score;
			}
		}

		return $best;
	}

	/**
	 * @return array{0:string,1:string} [ host, port ].
	 */
	private static function split_port( string $domain ): array {
		if ( preg_match( '#^(.*?):(\d+)$#', $domain, $m ) && '' !== $m[1] && substr_count( $m[1], ':' ) === 0 || ( isset( $m[1] ) && 0 === strpos( $m[1], '[' ) ) ) {
			return array( $m[1], $m[2] );
		}
		return array( $domain, '' );
	}

	private static function strip_www( string $host ): string {
		return 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	}
}
