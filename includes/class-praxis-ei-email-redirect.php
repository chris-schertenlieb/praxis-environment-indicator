<?php
/**
 * Email redirection for non-production environments.
 *
 * When the current environment is ticked for redirection, every wp_mail()
 * message goes to the configured address(es) instead of the real recipients.
 * Production is never redirected, regardless of what is stored in settings.
 */

defined( 'ABSPATH' ) || exit;

final class Praxis_EI_Email_Redirect {

	public static function init(): void {
		add_filter( 'pre_wp_mail', array( __CLASS__, 'maybe_block' ), PHP_INT_MAX, 2 );
		add_filter( 'wp_mail', array( __CLASS__, 'rewrite' ), PHP_INT_MAX );
		add_action( 'phpmailer_init', array( __CLASS__, 'enforce_recipients' ), PHP_INT_MAX );
	}

	/**
	 * Is redirection on for the current request?
	 */
	public static function is_active(): bool {
		$env = Praxis_EI_Environment::current();
		if ( null === $env['key'] || 'production' === $env['key'] ) {
			return false;
		}

		$settings = Praxis_EI_Settings::get();
		$active   = in_array( $env['key'], (array) $settings['redirect_envs'], true );

		/**
		 * Filter whether email redirection is active. Cannot enable redirection
		 * on production.
		 */
		return (bool) apply_filters( 'praxis_ei_redirect_active', $active, $env );
	}

	/**
	 * Valid redirect addresses from settings.
	 *
	 * @return string[]
	 */
	public static function recipients(): array {
		return self::parse_addresses( Praxis_EI_Settings::get()['redirect_to'] );
	}

	/**
	 * @param string|array $value Comma/semicolon/newline separated addresses.
	 * @return string[]
	 */
	public static function parse_addresses( $value ): array {
		$parts = is_array( $value ) ? $value : preg_split( '/[\s,;]+/', (string) $value );
		$out   = array();
		foreach ( $parts as $part ) {
			$email = sanitize_email( trim( (string) $part ) );
			if ( '' !== $email && is_email( $email ) ) {
				$out[] = $email;
			}
		}
		return array_values( array_unique( $out ) );
	}

	public static function status_text(): string {
		if ( Praxis_EI_Environment::is_production() ) {
			return __( 'Email: delivered normally (production)', 'praxis-environment-indicator' );
		}
		if ( ! self::is_active() ) {
			return __( 'Email: NOT redirected — delivered to real recipients', 'praxis-environment-indicator' );
		}
		$to = self::recipients();
		if ( ! $to ) {
			return __( 'Email: blocked (no redirect address set)', 'praxis-environment-indicator' );
		}
		/* translators: %s: redirect addresses */
		return sprintf( __( 'Email: redirected to %s', 'praxis-environment-indicator' ), implode( ', ', $to ) );
	}

	/**
	 * Redirection is on but no valid address is configured: refuse to send
	 * rather than leak mail to real recipients.
	 */
	public static function maybe_block( $return, $atts ) {
		if ( null !== $return || ! self::is_active() || self::recipients() ) {
			return $return;
		}

		$error = new WP_Error(
			'wp_mail_failed',
			__( 'Email blocked by Praxis Environment Indicator: redirection is enabled for this environment but no redirect address is set.', 'praxis-environment-indicator' ),
			$atts
		);
		do_action( 'wp_mail_failed', $error );

		return false;
	}

	/**
	 * Swap recipients, strip Cc/Bcc and tag the subject.
	 */
	public static function rewrite( $atts ) {
		if ( ! self::is_active() || ! self::recipients() || ! is_array( $atts ) ) {
			return $atts;
		}

		$original = array(
			'To'  => self::list_addresses( isset( $atts['to'] ) ? $atts['to'] : array() ),
			'Cc'  => array(),
			'Bcc' => array(),
		);

		$headers = isset( $atts['headers'] ) ? $atts['headers'] : array();
		if ( ! is_array( $headers ) ) {
			$headers = explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );
		}

		$kept = array();
		foreach ( $headers as $header ) {
			$header = trim( (string) $header );
			if ( '' === $header ) {
				continue;
			}
			if ( preg_match( '/^(cc|bcc)\s*:\s*(.*)$/i', $header, $m ) ) {
				$key              = 'cc' === strtolower( $m[1] ) ? 'Cc' : 'Bcc';
				$original[ $key ] = array_merge( $original[ $key ], self::list_addresses( $m[2] ) );
				continue;
			}
			$kept[] = $header;
		}

		foreach ( $original as $type => $list ) {
			if ( $list ) {
				$kept[] = 'X-Praxis-Original-' . $type . ': ' . self::header_safe( implode( ', ', $list ) );
			}
		}

		$env   = Praxis_EI_Environment::current();
		$label = strtoupper( $env['label'] );
		$to    = $original['To'] ? implode( ', ', $original['To'] ) : '—';

		$atts['to']      = self::recipients();
		$atts['headers'] = $kept;
		$atts['subject'] = sprintf( '[%s → %s] %s', $label, $to, isset( $atts['subject'] ) ? $atts['subject'] : '' );

		return $atts;
	}

	/**
	 * Belt and braces: other plugins can add recipients directly on PHPMailer
	 * after the wp_mail filter runs. Reset them to the redirect addresses.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer
	 */
	public static function enforce_recipients( $phpmailer ): void {
		if ( ! self::is_active() ) {
			return;
		}

		$phpmailer->clearAllRecipients();
		foreach ( self::recipients() as $email ) {
			$phpmailer->addAddress( $email );
		}
	}

	/**
	 * @param string|array $value
	 * @return string[]
	 */
	private static function list_addresses( $value ): array {
		$parts = is_array( $value ) ? $value : explode( ',', (string) $value );
		return array_values( array_filter( array_map( 'trim', array_map( 'strval', $parts ) ) ) );
	}

	private static function header_safe( string $value ): string {
		return str_replace( array( "\r", "\n" ), ' ', $value );
	}
}
