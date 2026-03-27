<?php
/**
 * WP Consent API Integration
 *
 * Declares plugin compatibility with the WP Consent API
 * and registers privacy policy content.
 *
 * @package OpsHealthDashboard\Core
 */

namespace OpsHealthDashboard\Core;

if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreStart
	exit;
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreEnd
}

/**
 * Class ConsentIntegration
 *
 * Integrates with the WP Consent API plugin to declare
 * this plugin as consent-aware. Since Ops Health Dashboard
 * is an admin-only monitoring tool with no cookies or user
 * tracking, all functionality falls under the "functional"
 * consent category.
 */
class ConsentIntegration {

	/**
	 * Registers hooks for Consent API and privacy policy
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		$basename = $this->get_plugin_basename();

		add_filter(
			'wp_consent_api_registered_' . $basename,
			'__return_true'
		);

		add_action( 'admin_init', [ $this, 'add_privacy_policy_content' ] );
	}

	/**
	 * Adds suggested privacy policy content to WordPress
	 *
	 * Registers text in the WordPress Privacy Policy Guide page
	 * (Settings → Privacy) documenting what data the plugin
	 * processes and transmits.
	 *
	 * @return void
	 */
	public function add_privacy_policy_content(): void {
		$content = $this->build_privacy_policy_text();

		wp_add_privacy_policy_content(
			__( 'Ops Health Dashboard', 'ops-health-dashboard' ),
			$content
		);
	}

	/**
	 * Gets the plugin basename for filter registration
	 *
	 * @return string Plugin basename.
	 */
	protected function get_plugin_basename(): string {
		return plugin_basename( OPS_HEALTH_DASHBOARD_FILE );
	}

	/**
	 * Builds the privacy policy suggested text
	 *
	 * @return string HTML privacy policy content.
	 */
	private function build_privacy_policy_text(): string {
		// phpcs:disable Generic.Files.LineLength.MaxExceeded -- i18n strings must be single literals.
		$heading = __(
			'Ops Health Dashboard — Data Processing',
			'ops-health-dashboard'
		);

		$overview = __(
			'This plugin monitors the operational health of the WordPress installation. It operates exclusively in the admin area and does not collect, track, or process any visitor or front-end user data.',
			'ops-health-dashboard'
		);

		$cookies_heading = __(
			'Cookies',
			'ops-health-dashboard'
		);

		$cookies_text = __(
			'This plugin does not set any cookies.',
			'ops-health-dashboard'
		);

		$alerts_heading = __(
			'External Services (Alerts)',
			'ops-health-dashboard'
		);

		$alerts_text = __(
			'When configured by an administrator, the plugin may send operational alert notifications to external services: Email, Webhook, Slack, Telegram, and WhatsApp. These alerts contain only technical health-check data (check name, status, site URL) and no personal user data. Alert channels are disabled by default and require explicit administrator configuration.',
			'ops-health-dashboard'
		);

		$redaction_heading = __(
			'Data Redaction',
			'ops-health-dashboard'
		);

		$redaction_text = __(
			'All diagnostic data (error log samples, system messages) is automatically redacted before storage or transmission. Sensitive information such as file paths, database credentials, API keys, email addresses, and IP addresses are replaced with placeholder tokens.',
			'ops-health-dashboard'
		);

		$storage_heading = __(
			'Data Storage',
			'ops-health-dashboard'
		);

		$storage_text = __(
			'Health check results and alert configuration are stored in the WordPress database via the Options API. All data is removed upon plugin uninstallation.',
			'ops-health-dashboard'
		);
		// phpcs:enable Generic.Files.LineLength.MaxExceeded

		return '<h2>' . esc_html( $heading ) . '</h2>'
			. '<p>' . esc_html( $overview ) . '</p>'
			. '<h3>' . esc_html( $cookies_heading ) . '</h3>'
			. '<p>' . esc_html( $cookies_text ) . '</p>'
			. '<h3>' . esc_html( $alerts_heading ) . '</h3>'
			. '<p>' . esc_html( $alerts_text ) . '</p>'
			. '<h3>' . esc_html( $redaction_heading ) . '</h3>'
			. '<p>' . esc_html( $redaction_text ) . '</p>'
			. '<h3>' . esc_html( $storage_heading ) . '</h3>'
			. '<p>' . esc_html( $storage_text ) . '</p>';
	}
}
