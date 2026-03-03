<?php
/**
 * Alert Manager Interface
 *
 * Contract for the alert orchestrator.
 * Manages state change detection,
 * cooldown, and dispatch to channels.
 *
 * @package OpsHealthDashboard\Interfaces
 */

namespace OpsHealthDashboard\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreStart
	exit;
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreEnd
}

/**
 * Interface AlertManagerInterface
 *
 * Defines the contract for centralized alert management.
 */
interface AlertManagerInterface {

	/**
	 * Adds a notification channel
	 *
	 * @param AlertChannelInterface $channel Channel to add.
	 * @return void
	 */
	public function add_channel( AlertChannelInterface $channel ): void;

	/**
	 * Processes check results and sends alerts on state changes
	 *
	 * Compares current results with previous ones, detects state transitions,
	 * verifies cooldowns, and sends notifications to enabled channels.
	 *
	 * @param array $current_results  Current results from run_all().
	 * @param array $previous_results Previous results (can be empty on first run).
	 * @return array Array of alert dispatch results.
	 */
	public function process( array $current_results, array $previous_results ): array;
}
