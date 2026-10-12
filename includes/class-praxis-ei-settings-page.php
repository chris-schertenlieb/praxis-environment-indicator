<?php
/**
 * Settings > Environment Indicator.
 *
 * Saved through admin-post.php rather than the Settings API: register_setting()
 * runs the sanitize callback twice when the option doesn't exist yet, which
 * would double-encode the stored value.
 */

defined( 'ABSPATH' ) || exit;

final class Praxis_EI_Settings_Page {

	const ACTION = 'praxis_ei_save';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PRAXIS_EI_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu(): void {
		$hook = add_options_page(
			__( 'Environment Indicator', 'praxis-environment-indicator' ),
			__( 'Environment Indicator', 'praxis-environment-indicator' ),
			'manage_options',
			Praxis_EI_Settings_Link::SLUG,
			array( __CLASS__, 'render' )
		);
		add_action( 'admin_print_scripts-' . $hook, array( __CLASS__, 'assets' ) );
	}

	public static function action_links( array $links ): array {
		array_unshift(
			$links,
			'<a href="' . esc_url( Praxis_EI_Settings_Link::url() ) . '">' . esc_html__( 'Settings', 'praxis-environment-indicator' ) . '</a>'
		);
		return $links;
	}

	public static function assets(): void {
		wp_enqueue_style( 'praxis-ei-admin', PRAXIS_EI_URL . 'assets/admin.css', array(), PRAXIS_EI_VERSION );
		wp_enqueue_script( 'praxis-ei-admin', PRAXIS_EI_URL . 'assets/admin.js', array(), PRAXIS_EI_VERSION, true );
		wp_localize_script(
			'praxis-ei-admin',
			'praxisEI',
			array(
				'types' => array_map(
					function ( $type ) {
						return $type['label'];
					},
					Praxis_EI_Environment::types()
				),
				'i18n'  => array(
					'default'        => __( 'default (unmatched domains)', 'praxis-environment-indicator' ),
					'productionNote' => __( 'Production email is always delivered normally.', 'praxis-environment-indicator' ),
				),
			)
		);
	}

	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'praxis-environment-indicator' ), 403 );
		}
		check_admin_referer( self::ACTION );

		$post = wp_unslash( $_POST );

		$mappings = array();
		$seen     = array();
		if ( isset( $post['mappings'] ) && is_array( $post['mappings'] ) ) {
			foreach ( $post['mappings'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$domain = Praxis_EI_Environment::normalize_domain( isset( $row['domain'] ) ? (string) $row['domain'] : '' );
				$env    = isset( $row['env'] ) ? (string) $row['env'] : '';
				if ( '' === $domain || ! Praxis_EI_Environment::is_valid_type( $env ) || isset( $seen[ $domain ] ) ) {
					continue;
				}
				$seen[ $domain ] = true;
				$mappings[]      = array(
					'domain' => $domain,
					'env'    => $env,
				);
			}
		}

		$default_env = isset( $post['default_env'] ) ? (string) $post['default_env'] : '';
		if ( ! Praxis_EI_Environment::is_valid_type( $default_env ) ) {
			$default_env = Praxis_EI_Settings::defaults()['default_env'];
		}

		// Only environments that are actually in use can redirect, and never production.
		$in_use        = array_merge( wp_list_pluck( $mappings, 'env' ), array( $default_env ) );
		$redirect_envs = array();
		if ( isset( $post['redirect_envs'] ) && is_array( $post['redirect_envs'] ) ) {
			foreach ( $post['redirect_envs'] as $env ) {
				$env = (string) $env;
				if ( 'production' !== $env && Praxis_EI_Environment::is_valid_type( $env ) && in_array( $env, $in_use, true ) ) {
					$redirect_envs[] = $env;
				}
			}
		}

		$redirect_to = Praxis_EI_Email_Redirect::parse_addresses( isset( $post['redirect_to'] ) ? $post['redirect_to'] : '' );

		Praxis_EI_Settings::save(
			array(
				'configured'    => true,
				'mappings'      => $mappings,
				'default_env'   => $default_env,
				'redirect_envs' => array_values( array_unique( $redirect_envs ) ),
				'redirect_to'   => implode( ', ', $redirect_to ),
			)
		);

		wp_safe_redirect( add_query_arg( 'praxis-ei-saved', '1', Praxis_EI_Settings_Link::url() ) );
		exit;
	}

	public static function notices(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'settings_page_' . Praxis_EI_Settings_Link::SLUG !== $screen->id ) {
			return;
		}

		if ( isset( $_GET['praxis-ei-saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'praxis-environment-indicator' ) . '</p></div>';
		}

		$settings = Praxis_EI_Settings::get();
		if ( $settings['configured'] && $settings['redirect_envs'] && ! Praxis_EI_Email_Redirect::recipients() ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Email redirection is enabled but no valid redirect address is set. All email on the redirected environments will be blocked until you add one.', 'praxis-environment-indicator' ) . '</p></div>';
		}

		$env = Praxis_EI_Environment::current();
		if ( 'default' === $env['source'] ) {
			echo '<div class="notice notice-warning"><p>' . sprintf(
				/* translators: 1: current domain, 2: default environment label */
				esc_html__( 'The current domain %1$s doesn\'t match any rule, so the default (%2$s) is being used. Add it below if that\'s not right.', 'praxis-environment-indicator' ),
				'<code>' . esc_html( $env['host'] ) . '</code>',
				'<strong>' . esc_html( $env['label'] ) . '</strong>'
			) . '</p></div>';
		}
	}

	public static function render(): void {
		$settings = Praxis_EI_Settings::get();
		$types    = Praxis_EI_Environment::types();
		$env      = Praxis_EI_Environment::current();
		$mappings = $settings['mappings'];

		?>
		<div class="wrap praxis-ei">
			<h1><?php esc_html_e( 'Environment Indicator', 'praxis-environment-indicator' ); ?></h1>

			<div class="praxis-ei-current" style="--praxis-ei-color: <?php echo esc_attr( $env['color'] ); ?>">
				<span class="praxis-ei-badge"><?php echo esc_html( $env['label'] ); ?></span>
				<span>
					<?php
					printf(
						/* translators: %s: current domain */
						esc_html__( 'Current domain: %s', 'praxis-environment-indicator' ),
						'<code>' . esc_html( '' !== $env['host'] ? $env['host'] : '—' ) . '</code>'
					);
					?>
					&middot; <?php echo esc_html( Praxis_EI_Email_Redirect::status_text() ); ?>
				</span>
			</div>

			<?php if ( 'constant' === $env['source'] ) : ?>
				<div class="notice notice-info inline"><p>
					<?php
					printf(
						/* translators: %s: constant name */
						esc_html__( 'The environment is forced by the %s constant in wp-config.php. The domain rules below are ignored on this install.', 'praxis-environment-indicator' ),
						'<code>PRAXIS_ENVIRONMENT</code>'
					);
					?>
				</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::ACTION ); ?>

				<h2><?php esc_html_e( 'Domains', 'praxis-environment-indicator' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Map each domain the site runs on to an environment. This list travels with the database, so when production is copied to staging the staging site still knows it is staging.', 'praxis-environment-indicator' ); ?><br>
					<?php
					printf(
						/* translators: 1-3: example domains */
						esc_html__( 'Include the port when it matters (%1$s). A leading www. is ignored. Use %2$s to match any subdomain. Example: %3$s', 'praxis-environment-indicator' ),
						'<code>localhost:3000</code>',
						'<code>*.example.com</code>',
						'<code>staging.example.com</code>'
					);
					?>
				</p>

				<table class="widefat striped praxis-ei-mappings">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Domain', 'praxis-environment-indicator' ); ?></th>
							<th><?php esc_html_e( 'Environment', 'praxis-environment-indicator' ); ?></th>
							<th class="praxis-ei-col-remove"><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'praxis-environment-indicator' ); ?></span></th>
						</tr>
					</thead>
					<tbody id="praxis-ei-rows">
						<?php
						foreach ( $mappings as $i => $mapping ) {
							self::render_row( (string) $i, $mapping['domain'], $mapping['env'], $types );
						}
						?>
					</tbody>
					<tfoot>
						<tr>
							<td colspan="3">
								<button type="button" class="button" id="praxis-ei-add"><?php esc_html_e( 'Add domain', 'praxis-environment-indicator' ); ?></button>
								<?php if ( '' !== $env['host'] ) : ?>
									<button type="button" class="button-link" id="praxis-ei-add-current" data-domain="<?php echo esc_attr( $env['host'] ); ?>">
										<?php
										/* translators: %s: current domain */
										printf( esc_html__( 'Add current domain (%s)', 'praxis-environment-indicator' ), esc_html( $env['host'] ) );
										?>
									</button>
								<?php endif; ?>
							</td>
						</tr>
					</tfoot>
				</table>

				<template id="praxis-ei-row-template">
					<?php self::render_row( '__INDEX__', '', 'staging', $types ); ?>
				</template>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="praxis-ei-default"><?php esc_html_e( 'Default environment', 'praxis-environment-indicator' ); ?></label></th>
						<td>
							<?php self::render_select( 'default_env', $settings['default_env'], $types, 'praxis-ei-default' ); ?>
							<p class="description"><?php esc_html_e( 'Used when the current domain matches none of the rules above, e.g. a new staging or preview URL nobody has added yet. A non-production default is the safer choice.', 'praxis-environment-indicator' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Email redirection', 'praxis-environment-indicator' ); ?></h2>
				<p class="description"><?php esc_html_e( 'On the ticked environments, all email sent through WordPress goes to the address below instead of the real recipients. Cc and Bcc are removed, and the subject shows the environment and the original recipient.', 'praxis-environment-indicator' ); ?></p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Redirect email on', 'praxis-environment-indicator' ); ?></th>
						<td>
							<fieldset id="praxis-ei-redirect-envs" data-checked="<?php echo esc_attr( wp_json_encode( array_values( $settings['redirect_envs'] ) ) ); ?>">
								<?php self::render_redirect_checkboxes( $mappings, $settings['default_env'], $settings['redirect_envs'], $types ); ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="praxis-ei-redirect-to"><?php esc_html_e( 'Send redirected email to', 'praxis-environment-indicator' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="praxis-ei-redirect-to" name="redirect_to" value="<?php echo esc_attr( $settings['redirect_to'] ); ?>" placeholder="dev@example.com">
							<p class="description"><?php esc_html_e( 'Separate multiple addresses with commas. If redirection is on and this is empty, email is blocked rather than sent to real recipients.', 'praxis-environment-indicator' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private static function render_row( string $index, string $domain, string $env, array $types ): void {
		?>
		<tr class="praxis-ei-row">
			<td>
				<input type="text" class="regular-text praxis-ei-domain" name="mappings[<?php echo esc_attr( $index ); ?>][domain]" value="<?php echo esc_attr( $domain ); ?>" placeholder="example.com" aria-label="<?php esc_attr_e( 'Domain', 'praxis-environment-indicator' ); ?>">
			</td>
			<td>
				<?php self::render_select( 'mappings[' . $index . '][env]', $env, $types, '', 'praxis-ei-env' ); ?>
			</td>
			<td class="praxis-ei-col-remove">
				<button type="button" class="button-link praxis-ei-remove" aria-label="<?php esc_attr_e( 'Remove domain', 'praxis-environment-indicator' ); ?>">&times;</button>
			</td>
		</tr>
		<?php
	}

	private static function render_select( string $name, string $selected, array $types, string $id = '', string $class = '' ): void {
		printf(
			'<select name="%s"%s%s>',
			esc_attr( $name ),
			$id ? ' id="' . esc_attr( $id ) . '"' : '',
			$class ? ' class="' . esc_attr( $class ) . '"' : ''
		);
		foreach ( $types as $key => $type ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $selected, $key, false ), esc_html( $type['label'] ) );
		}
		echo '</select>';
	}

	/**
	 * One checkbox per environment that appears in the config. Mirrored in
	 * assets/admin.js, which re-renders this list live as rows are edited.
	 */
	private static function render_redirect_checkboxes( array $mappings, string $default_env, array $checked, array $types ): void {
		$domains = array();
		foreach ( $mappings as $mapping ) {
			$domains[ $mapping['env'] ][] = $mapping['domain'];
		}
		$domains[ $default_env ][] = __( 'default (unmatched domains)', 'praxis-environment-indicator' );

		foreach ( $types as $key => $type ) {
			if ( empty( $domains[ $key ] ) ) {
				continue;
			}
			$is_prod = 'production' === $key;
			?>
			<label class="praxis-ei-check<?php echo $is_prod ? ' is-disabled' : ''; ?>">
				<input type="checkbox" name="redirect_envs[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( ! $is_prod && in_array( $key, $checked, true ) ); ?> <?php disabled( $is_prod ); ?>>
				<strong><?php echo esc_html( $type['label'] ); ?></strong>
				<span class="praxis-ei-domains"><?php echo esc_html( implode( ', ', $domains[ $key ] ) ); ?></span>
				<?php if ( $is_prod ) : ?>
					<span class="description"><?php esc_html_e( 'Production email is always delivered normally.', 'praxis-environment-indicator' ); ?></span>
				<?php endif; ?>
			</label>
			<?php
		}
	}
}
