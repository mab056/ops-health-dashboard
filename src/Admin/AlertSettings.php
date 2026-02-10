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

use OpsHealthDashboard\Interfaces\StorageInterface;

/**
 * Class AlertSettings
 *
 * Gestisce la pagina di configurazione degli alert.
 */
class AlertSettings {

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
		$email_recipients = isset( $email['recipients'] ) ? $email['recipients'] : '';
		$webhook_url      = isset( $webhook['url'] ) ? $webhook['url'] : '';
		$webhook_secret   = isset( $webhook['secret'] ) ? $webhook['secret'] : '';
		$slack_url        = isset( $slack['webhook_url'] ) ? $slack['webhook_url'] : '';
		$tg_bot_token     = isset( $telegram['bot_token'] ) ? $telegram['bot_token'] : '';
		$tg_chat_id       = isset( $telegram['chat_id'] ) ? $telegram['chat_id'] : '';
		$wa_webhook_url   = isset( $whatsapp['webhook_url'] ) ? $whatsapp['webhook_url'] : '';
		$wa_phone         = isset( $whatsapp['phone_number'] ) ? $whatsapp['phone_number'] : '';
		$wa_token         = isset( $whatsapp['api_token'] ) ? $whatsapp['api_token'] : '';

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

				<h2><?php echo esc_html__( 'Email', 'ops-health-dashboard' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php echo esc_html__( 'Enabled', 'ops-health-dashboard' ); ?></th>
						<td>
							<input type="checkbox" name="email_enabled" value="1"
								<?php checked( ! empty( $email['enabled'] ) ); ?> />
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

				<h2><?php echo esc_html__( 'Webhook', 'ops-health-dashboard' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php echo esc_html__( 'Enabled', 'ops-health-dashboard' ); ?></th>
						<td>
							<input type="checkbox" name="webhook_enabled" value="1"
								<?php checked( ! empty( $webhook['enabled'] ) ); ?> />
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
							<input type="text" name="webhook_secret" class="regular-text"
								value="<?php echo esc_attr( $webhook_secret ); ?>" />
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'Slack', 'ops-health-dashboard' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php echo esc_html__( 'Enabled', 'ops-health-dashboard' ); ?></th>
						<td>
							<input type="checkbox" name="slack_enabled" value="1"
								<?php checked( ! empty( $slack['enabled'] ) ); ?> />
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

				<h2><?php echo esc_html__( 'Telegram', 'ops-health-dashboard' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php echo esc_html__( 'Enabled', 'ops-health-dashboard' ); ?></th>
						<td>
							<input type="checkbox" name="telegram_enabled" value="1"
								<?php checked( ! empty( $telegram['enabled'] ) ); ?> />
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html__( 'Bot Token', 'ops-health-dashboard' ); ?></th>
						<td>
							<input type="text" name="telegram_bot_token" class="regular-text"
								value="<?php echo esc_attr( $tg_bot_token ); ?>" />
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

				<h2><?php echo esc_html__( 'WhatsApp', 'ops-health-dashboard' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php echo esc_html__( 'Enabled', 'ops-health-dashboard' ); ?></th>
						<td>
							<input type="checkbox" name="whatsapp_enabled" value="1"
								<?php checked( ! empty( $whatsapp['enabled'] ) ); ?> />
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
							<input type="text" name="whatsapp_api_token" class="regular-text"
								value="<?php echo esc_attr( $wa_token ); ?>" />
						</td>
					</tr>
				</table>

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
	 * Costruisce l'array delle impostazioni dal POST
	 *
	 * Chiamato solo dopo verifica nonce e capability in process_actions().
	 *
	 * @return array Impostazioni sanitizzate.
	 */
	private function build_settings_from_post(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in process_actions().
		return [
			'email'            => [
				'enabled'    => ! empty( $_POST['email_enabled'] ),
				'recipients' => sanitize_text_field( wp_unslash( $_POST['email_recipients'] ?? '' ) ),
			],
			'webhook'          => [
				'enabled' => ! empty( $_POST['webhook_enabled'] ),
				'url'     => esc_url_raw( wp_unslash( $_POST['webhook_url'] ?? '' ) ),
				'secret'  => sanitize_text_field( wp_unslash( $_POST['webhook_secret'] ?? '' ) ),
			],
			'slack'            => [
				'enabled'     => ! empty( $_POST['slack_enabled'] ),
				'webhook_url' => esc_url_raw( wp_unslash( $_POST['slack_webhook_url'] ?? '' ) ),
			],
			'telegram'         => [
				'enabled'   => ! empty( $_POST['telegram_enabled'] ),
				'bot_token' => sanitize_text_field( wp_unslash( $_POST['telegram_bot_token'] ?? '' ) ),
				'chat_id'   => sanitize_text_field( wp_unslash( $_POST['telegram_chat_id'] ?? '' ) ),
			],
			'whatsapp'         => [
				'enabled'      => ! empty( $_POST['whatsapp_enabled'] ),
				'webhook_url'  => esc_url_raw( wp_unslash( $_POST['whatsapp_webhook_url'] ?? '' ) ),
				'phone_number' => sanitize_text_field( wp_unslash( $_POST['whatsapp_phone_number'] ?? '' ) ),
				'api_token'    => sanitize_text_field( wp_unslash( $_POST['whatsapp_api_token'] ?? '' ) ),
			],
			'cooldown_minutes' => absint( $_POST['cooldown_minutes'] ?? 60 ),
		];
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
