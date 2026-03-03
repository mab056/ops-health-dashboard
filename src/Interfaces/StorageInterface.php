<?php
/**
 * Storage Interface
 *
 * Contract for storage services. Implementations can use WordPress
 * Options API, transients, custom tables, or other mechanisms.
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
 * Interface StorageInterface
 *
 * Defines the methods for storage operations (get, set, delete, has).
 */
interface StorageInterface {

	/**
	 * Retrieves a value from storage
	 *
	 * @param string $key     Key to retrieve.
	 * @param mixed  $default Default value if the key does not exist.
	 * @return mixed Retrieved value or default.
	 */
	public function get( string $key, $default = null );

	/**
	 * Saves a value to storage
	 *
	 * @param string $key   Key to save.
	 * @param mixed  $value Value to save.
	 * @return bool True if the save was successful.
	 */
	public function set( string $key, $value ): bool;

	/**
	 * Deletes a value from storage
	 *
	 * @param string $key Key to delete.
	 * @return bool True if the deletion was successful.
	 */
	public function delete( string $key ): bool;

	/**
	 * Checks if a key exists in storage
	 *
	 * @param string $key Key to check.
	 * @return bool True if the key exists.
	 */
	public function has( string $key ): bool;
}
