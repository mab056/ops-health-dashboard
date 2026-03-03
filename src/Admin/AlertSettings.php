<?php
/**
 * Alert Settings Admin Page
 *
 * Pagina admin per configurare i canali di alert e il cooldown.
 * Segue il pattern PRG (Post-Redirect-Get) con transient per le notice.
 *
 * @package OpsHealthDashboard\Admin
 */

namespace OpsHealthDashboard\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreStart
	exit;
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreEnd
}

use OpsHealthDashboard\Interfaces\StorageInterface;

/**
 * Class AlertSettings
 *
 * Gestisce la pagina di configurazione degli alert.
 */
class AlertSettings {

	/**
	 * Screen ID per la pagina alert settings
	 *
	 * @var string
	 */
	const SCREEN_ID = 'ops-health_page_ops-health-alert-settings';

	/**
	 * Storage per leggere/salvare le impostazioni
	 *
	 * @var StorageInterface
	 */
	private $storage;

	/**
	 * Constructor
	 *
	 * @param StorageInterface $storage Storage per le impostazioni.
	 */
	public function __construct( StorageInterface $storage ) {
		$this->storage = $storage;
	}

	/**
	 * Registra gli hook WordPress
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Carica JS e CSS sulla pagina alert settings
	 *
	 * Enqueue solo sulla schermata corretta per admin autorizzati.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( null === $screen || self::SCREEN_ID !== $screen->id ) {
			return;
		}

		wp_enqueue_style(
			'ops-health-alert-settings',
			plugin_dir_url( OPS_HEALTH_DASHBOARD_FILE ) . 'assets/css/alert-settings.css',
			[],
			OPS_HEALTH_DASHBOARD_VERSION
		);

		wp_enqueue_script(
			'ops-health-alert-settings',
			plugin_dir_url( OPS_HEALTH_DASHBOARD_FILE ) . 'assets/js/alert-settings.js',
			[],
			OPS_HEALTH_DASHBOARD_VERSION,
			true
		);
	}

	/**
	 * Registers contextual help tabs for the alert settings screen
	 *
	 * Called on the load-{$hook} action to add WordPress-native help tabs.
	 *
	 * @return void
	 */
	public function add_help_tabs(): void {
		$screen = get_current_screen();

		if ( null === $screen ) {
			return;
		}

		$this->add_alert_overview_help_tab( $screen );
		$this->add_alert_channels_help_tab( $screen );
		$this->add_alert_config_help_tab( $screen );

		$github_url   = esc_url( 'https://github.com/mab056/ops-health-dashboard' );
		$dash_url     = esc_url(
			admin_url( 'admin.php?page=ops-health-dashboard' )
		);
		$github_label = __( 'GitHub Repository', 'ops-health-dashboard' );
		$dash_label   = __( 'Health Dashboard', 'ops-health-dashboard' );
		$more_info    = __( 'For more information:', 'ops-health-dashboard' );

		$screen->set_help_sidebar(
			'<p><strong>' . $more_info . '</strong></p>'
			. '<p><a href="' . $github_url . '" target="_blank">'
			. $github_label . '</a></p>'
			. '<p><a href="' . $dash_url . '">'
			. $dash_label . '</a></p>'
		);
	}

	/**
	 * Aggiunge la tab Overview all'help screen
	 *
	 * @param \WP_Screen $screen Schermata corrente.
	 * @return void
	 */
	private function add_alert_overview_help_tab( $screen ): void {
		$monitor  = __( 'The alert system monitors health check status changes.', 'ops-health-dashboard' );
		$notify   = __(
			'Notifications are sent when a check transitions to Warning or Critical.',
			'ops-health-dashboard'
		);
		$toggle   = __( 'Each channel can be independently enabled or disabled.', 'ops-health-dashboard' );
		$preserve = __( 'Disabled channels preserve their configuration.', 'ops-health-dashboard' );

		$content = '<p>' . $monitor . ' ' . $notify . '</p>'
			. '<p>' . $toggle . ' ' . $preserve . '</p>';

		$screen->add_help_tab(
			[
				'id'      => 'ops_health_alert_overview',
				'title'   => __( 'Overview', 'ops-health-dashboard' ),
				'content' => $content,
			]
		);
	}

	/**
	 * Aggiunge la tab Channels all'help screen
	 *
	 * @param \WP_Screen $screen Schermata corrente.
	 * @return void
	 */
	private function add_alert_channels_help_tab( $screen ): void {
		$intro     = __( 'Five notification channels are available:', 'ops-health-dashboard' );
		$email     = __( 'Email', 'ops-health-dashboard' );
		$email_d   = __( 'Sends alerts via wp_mail to comma-separated recipients.', 'ops-health-dashboard' );
		$webhook   = __( 'Webhook', 'ops-health-dashboard' );
		$webhook_d = __( 'Posts JSON to a URL with an HMAC signature.', 'ops-health-dashboard' );
		$slack     = __( 'Slack', 'ops-health-dashboard' );
		$slack_d   = __( 'Sends Block Kit messages to a Slack incoming webhook.', 'ops-health-dashboard' );
		$tg        = __( 'Telegram', 'ops-health-dashboard' );
		$tg_d      = __( 'Sends messages via Bot API using a token and chat ID.', 'ops-health-dashboard' );
		$wa        = __( 'WhatsApp', 'ops-health-dashboard' );
		$wa_d      = __( 'Sends messages via a webhook with Bearer auth.', 'ops-health-dashboard' );

		$content = '<p>' . $intro . '</p><ul>'
			. '<li><strong>' . $email . '</strong> &mdash; ' . $email_d . '</li>'
			. '<li><strong>' . $webhook . '</strong> &mdash; ' . $webhook_d . '</li>'
			. '<li><strong>' . $slack . '</strong> &mdash; ' . $slack_d . '</li>'
			. '<li><strong>' . $tg . '</strong> &mdash; ' . $tg_d . '</li>'
			. '<li><strong>' . $wa . '</strong> &mdash; ' . $wa_d . '</li>'
			. '</ul>';

		$screen->add_help_tab(
			[
				'id'      => 'ops_health_alert_channels',
				'title'   => __( 'Channels', 'ops-health-dashboard' ),
				'content' => $content,
			]
		);
	}

	/**
	 * Aggiunge la tab Configuration all'help screen
	 *
	 * @param \WP_Screen $screen Schermata corrente.
	 * @return void
	 */
	private function add_alert_config_help_tab( $screen ): void {
		$cd_label = __( 'Cooldown', 'ops-health-dashboard' );
		$cd_desc  = __( 'Minimum time between repeated alerts for the same check.', 'ops-health-dashboard' );
		$cr_label = __( 'Credentials', 'ops-health-dashboard' );
		$cr_desc  = __(
			'Sensitive fields are never displayed. Leave empty to keep existing values.',
			'ops-health-dashboard'
		);

		$content = '<p><strong>' . $cd_label . '</strong> &mdash; '
			. $cd_desc . '</p>'
			. '<p><strong>' . $cr_label . '</strong> &mdash; '
			. $cr_desc . '</p>';

		$screen->add_help_tab(
			[
				'id'      => 'ops_health_alert_config',
				'title'   => __( 'Configuration', 'ops-health-dashboard' ),
				'content' => $content,
			]
		);
	}

	/**
	 * Processa le azioni del form (salvataggio impostazioni)
	 *
	 * Verifica nonce e capability prima di salvare.
	 *
	 * @return void
	 */
	public function process_actions(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified below.
		if ( ! isset( $_POST['ops_health_alert_action'] ) ) {
			return;
		}

		if (
			! isset( $_POST['_ops_health_alert_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['_ops_health_alert_nonce'] ) ),
				'ops_health_alert_settings'
			)
		) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->build_settings_from_post();

		$this->storage->set( 'alert_settings', $settings );

		set_transient(
			'ops_health_alert_notice',
			__( 'Alert settings saved.', 'ops-health-dashboard' ),
			30
		);

		wp_safe_redirect( admin_url( 'admin.php?page=ops-health-alert-settings' ) );
		$this->do_exit();
	}

	/**
	 * Termina l'esecuzione dello script
	 *
	 * Estratto in metodo protetto per testabilità.
	 *
	 * @return void
	 * @codeCoverageIgnore
	 */
	protected function do_exit(): void {
		exit;
	}

	/**
	 * Renderizza la pagina delle impostazioni alert
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__(
					'You do not have sufficient permissions to access this page.',
					'ops-health-dashboard'
				)
			);
		}

		$settings = $this->storage->get( 'alert_settings', [] );

		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		$notice = get_transient( 'ops_health_alert_notice' );

		if ( false !== $notice ) {
			delete_transient( 'ops_health_alert_notice' );
		}

		$email    = isset( $settings['email'] ) && is_array( $settings['email'] )
			? $settings['email'] : [];
		$webhook  = isset( $settings['webhook'] ) && is_array( $settings['webhook'] )
			? $settings['webhook'] : [];
		$slack    = isset( $settings['slack'] ) && is_array( $settings['slack'] )
			? $settings['slack'] : [];
		$telegram = isset( $settings['telegram'] ) && is_array( $settings['telegram'] )
			? $settings['telegram'] : [];
		$whatsapp = isset( $settings['whatsapp'] ) && is_array( $settings['whatsapp'] )
			? $settings['whatsapp'] : [];
		$cooldown = isset( $settings['cooldown_minutes'] )
			? (int) $settings['cooldown_minutes'] : 60;

		// Extract values for template output.
		$email_recipients   = isset( $email['recipients'] ) ? $email['recipients'] : '';
		$webhook_url        = isset( $webhook['url'] ) ? $webhook['url'] : '';
		$has_webhook_secret = isset( $webhook['secret'] ) && '' !== $webhook['secret'];
		$slack_url          = isset( $slack['webhook_url'] ) ? $slack['webhook_url'] : '';
		$has_tg_bot_token   = isset( $telegram['bot_token'] ) && '' !== $telegram['bot_token'];
		$tg_chat_id         = isset( $telegram['chat_id'] ) ? $telegram['chat_id'] : '';
		$wa_webhook_url     = isset( $whatsapp['webhook_url'] ) ? $whatsapp['webhook_url'] : '';
		$wa_phone           = isset( $whatsapp['phone_number'] ) ? $whatsapp['phone_number'] : '';
		$has_wa_token       = isset( $whatsapp['api_token'] ) && '' !== $whatsapp['api_token'];

		$email_enabled    = ! empty( $email['enabled'] );
		$webhook_enabled  = ! empty( $webhook['enabled'] );
		$slack_enabled    = ! empty( $slack['enabled'] );
		$telegram_enabled = ! empty( $telegram['enabled'] );
		$whatsapp_enabled = ! empty( $whatsapp['enabled'] );

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Alert Settings', 'ops-health-dashboard' ); ?></h1>

			<?php if ( false !== $notice ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html( $notice ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'ops_health_alert_settings', '_ops_health_alert_nonce' ); ?>
				<input type="hidden" name="ops_health_alert_action" value="save" />

				<h2><?php echo esc_html__( 'General', 'ops-health-dashboard' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php echo esc_html__( 'Cooldown (minutes)', 'ops-health-dashboard' ); ?></th>
						<td>
							<input type="number" name="cooldown_minutes" min="0" class="small-text"
								value="<?php echo esc_attr( (string) $cooldown ); ?>" />
							<p class="description">
							<?php
								echo esc_html__(
									'Minimum time between alerts for the same check.',
									'ops-health-dashboard'
								);
							?>
							</p>
						</td>
					</tr>
				</table>

				<?php
				$this->render_channel_section( 'email', 'Email', $email_enabled );
				?>
				<table class="form-table">
					<tr>
						<th><?php echo esc_html__( 'Enabled', 'ops-health-dashboard' ); ?></th>
						<td>
							<input type="checkbox" name="email_enabled" value="1"
								<?php checked( $email_enabled ); ?> />
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Recipients', 'ops-health-dashboard' ); ?></th>
						<td>
							<input type="text" name="email_recipients" class="regular-text"
								value="<?php echo esc_attr( $email_recipients ); ?>" />
							<p class="description">
								<?php echo esc_html__( 'Comma-separated email addresses.', 'ops-health-dashboard' ); ?>
							</p>
						</td>
					</tr>
				</table>
				</details>

				<?php $this->render_channel_section( 'webhook', 'Webhook', $webhook_enabled ); ?>
				<table class="form-table">
					<tr>
						<th><?php echo esc_html__( 'Enabled', 'ops-health-dashboard' ); ?></th>
						<td>
							<input type="checkbox" name="webhook_enabled" value="1"
								<?php checked( $webhook_enabled ); ?> />
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'URL', 'ops-health-dashboard' ); ?></th>
						<td>
							<input type="url" name="webhook_url" class="regular-text"
								value="<?php echo esc_attr( $webhook_url ); ?>" />
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Secret (HMAC)', 'ops-health-dashboard' ); ?></th>
						<td>
							<input type="password" name="webhook_secret" class="regular-text"
								value=""
								placeholder="<?php echo $has_webhook_secret ? esc_attr( '********' ) : ''; ?>"
								autocomplete="off" />
						</td>
					</tr>
				</table>
				</details>

				<?php $this->render_channel_section( 'slack', 'Slack', $slack_enabled ); ?>
				<table class="form-table">
					<tr>
						<th><?php echo esc_html__( 'Enabled', 'ops-health-dashboard' ); ?></th>
						<td>
							<input type="checkbox" name="slack_enabled" value="1"
								<?php checked( $slack_enabled ); ?> />
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Webhook URL', 'ops-health-dashboard' ); ?></th>
						<td>
							<input type="url" name="slack_webhook_url" class="regular-text"
								value="<?php echo esc_attr( $slack_url ); ?>" />
						</td>
					</tr>
				</table>
				</details>

				<?php $this->render_channel_section( 'telegram', 'Telegram', $telegram_enabled ); ?>
				<table class="form-table">
					<tr>
						<th><?php echo esc_html__( 'Enabled', 'ops-health-dashboard' ); ?></th>
						<td>
							<input type="checkbox" name="telegram_enabled" value="1"
								<?php checked( $telegram_enabled ); ?> />
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Bot Token', 'ops-health-dashboard' ); ?></th>
						<td>
							<input type="password" name="telegram_bot_token" class="regular-text"
								value=""
								placeholder="<?php echo $has_tg_bot_token ? esc_attr( '********' ) : ''; ?>"
								autocomplete="off" />
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Chat ID', 'ops-health-dashboard' ); ?></th>
						<td>
							<input type="text" name="telegram_chat_id" class="regular-text"
								value="<?php echo esc_attr( $tg_chat_id ); ?>" />
						</td>
					</tr>
				</table>
				</details>

				<?php $this->render_channel_section( 'whatsapp', 'WhatsApp', $whatsapp_enabled ); ?>
				<table class="form-table">
					<tr>
						<th><?php echo esc_html__( 'Enabled', 'ops-health-dashboard' ); ?></th>
						<td>
							<input type="checkbox" name="whatsapp_enabled" value="1"
								<?php checked( $whatsapp_enabled ); ?> />
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Webhook URL', 'ops-health-dashboard' ); ?></th>
						<td>
							<input type="url" name="whatsapp_webhook_url" class="regular-text"
								value="<?php echo esc_attr( $wa_webhook_url ); ?>" />
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Phone Number', 'ops-health-dashboard' ); ?></th>
						<td>
							<input type="text" name="whatsapp_phone_number" class="regular-text"
								value="<?php echo esc_attr( $wa_phone ); ?>" />
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'API Token', 'ops-health-dashboard' ); ?></th>
						<td>
							<input type="password" name="whatsapp_api_token" class="regular-text"
								value=""
								placeholder="<?php echo $has_wa_token ? esc_attr( '********' ) : ''; ?>"
								autocomplete="off" />
						</td>
					</tr>
				</table>
				</details>

				<?php
				submit_button(
					esc_html__( 'Save Settings', 'ops-health-dashboard' ),
					'primary',
					'ops_health_alert_submit',
					true
				);
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renderizza l'apertura di una sezione canale collassabile
	 *
	 * Ogni sezione è un elemento details/summary con badge di stato.
	 * Il contenuto (form-table) segue nella chiamata, chiuso da </details>.
	 *
	 * @param string $channel_id ID del canale.
	 * @param string $label      Label del canale.
	 * @param bool   $is_enabled Se il canale è abilitato.
	 * @return void
	 */
	private function render_channel_section(
		string $channel_id,
		string $label,
		bool $is_enabled
	): void {
		$open_attr   = $is_enabled ? ' open' : '';
		$badge_text  = $is_enabled
			? __( 'Enabled', 'ops-health-dashboard' )
			: __( 'Disabled', 'ops-health-dashboard' );
		$badge_class = $is_enabled
			? 'ops-health-alert-status-enabled'
			: 'ops-health-alert-status-disabled';
		?>
		<details class="ops-health-alert-section"
			id="alert-<?php echo esc_attr( $channel_id ); ?>"
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static string.
			echo $open_attr;
			?>
		>
			<summary>
				<?php echo esc_html( $label ); ?>
				<span class="ops-health-alert-status <?php echo esc_attr( $badge_class ); ?>">
					<?php echo esc_html( $badge_text ); ?>
				</span>
			</summary>
		<?php
	}

	/**
	 * Costruisce l'array delle impostazioni dal POST
	 *
	 * Chiamato solo dopo verifica nonce e capability in process_actions().
	 *
	 * @return array Impostazioni sanitizzate.
	 */
	private function build_settings_from_post(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in process_actions().
		$existing = $this->storage->get( 'alert_settings', [] );

		if ( ! is_array( $existing ) ) {
			$existing = [];
		}

		$webhook_secret = sanitize_text_field( wp_unslash( $_POST['webhook_secret'] ?? '' ) );
		$tg_bot_token   = sanitize_text_field( wp_unslash( $_POST['telegram_bot_token'] ?? '' ) );
		$wa_api_token   = sanitize_text_field( wp_unslash( $_POST['whatsapp_api_token'] ?? '' ) );

		// Preserve existing secrets when password field submitted empty.
		if ( '' === $webhook_secret && isset( $existing['webhook']['secret'] ) ) {
			$webhook_secret = $existing['webhook']['secret'];
		}

		if ( '' === $tg_bot_token && isset( $existing['telegram']['bot_token'] ) ) {
			$tg_bot_token = $existing['telegram']['bot_token'];
		}

		if ( '' === $wa_api_token && isset( $existing['whatsapp']['api_token'] ) ) {
			$wa_api_token = $existing['whatsapp']['api_token'];
		}

		return [
			'email'            => [
				'enabled'    => ! empty( $_POST['email_enabled'] ),
				'recipients' => sanitize_text_field( wp_unslash( $_POST['email_recipients'] ?? '' ) ),
			],
			'webhook'          => [
				'enabled' => ! empty( $_POST['webhook_enabled'] ),
				'url'     => esc_url_raw( wp_unslash( $_POST['webhook_url'] ?? '' ) ),
				'secret'  => $webhook_secret,
			],
			'slack'            => [
				'enabled'     => ! empty( $_POST['slack_enabled'] ),
				'webhook_url' => esc_url_raw( wp_unslash( $_POST['slack_webhook_url'] ?? '' ) ),
			],
			'telegram'         => [
				'enabled'   => ! empty( $_POST['telegram_enabled'] ),
				'bot_token' => $tg_bot_token,
				'chat_id'   => sanitize_text_field( wp_unslash( $_POST['telegram_chat_id'] ?? '' ) ),
			],
			'whatsapp'         => [
				'enabled'      => ! empty( $_POST['whatsapp_enabled'] ),
				'webhook_url'  => esc_url_raw( wp_unslash( $_POST['whatsapp_webhook_url'] ?? '' ) ),
				'phone_number' => sanitize_text_field( wp_unslash( $_POST['whatsapp_phone_number'] ?? '' ) ),
				'api_token'    => $wa_api_token,
			],
			'cooldown_minutes' => absint( $_POST['cooldown_minutes'] ?? 60 ),
		];
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
