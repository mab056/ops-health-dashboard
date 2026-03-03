<?php
/**
 * Storage Service
 *
 * StorageInterface implementation using WordPress Options API.
 * Simple wrapper for get_option(), update_option(), delete_option().
 *
 * @package OpsHealthDashboard\Services
 */

namespace OpsHealthDashboard\Services;

if ( ! defined( 'ABSPATH' ) ) {
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreStart
	exit;
	// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
	// @codeCoverageIgnoreEnd
}

use OpsHealthDashboard\Interfaces\StorageInterface;

/**
 * Class Storage
 *
 * Persistent storage service using WordPress Options API.
 */
class Storage implements StorageInterface {

	/**
	 * Prefix for option keys
	 *
	 * @var string
	 */
	private $prefix = 'ops_health_';

	/**
	 * Retrieves a value from storage
	 *
	 * @param string $key     Key to retrieve (without prefix).
	 * @param mixed  $default Default value if the key does not exist.
	 * @return mixed Retrieved value or default.
	 */
	public function get( string $key, $default = null ) {
		$prefixed_key = $this->get_prefixed_key( $key );
		return get_option( $prefixed_key, $default );
	}

	/**
	 * Saves a value to storage
	 *
	 * @param string $key   Key to save (without prefix).
	 * @param mixed  $value Value to save.
	 * @return bool True if the save was successful.
	 */
	public function set( string $key, $value ): bool {
		$prefixed_key = $this->get_prefixed_key( $key );
		return update_option( $prefixed_key, $value, false );
	}

	/**
	 * Deletes a value from storage
	 *
	 * @param string $key Key to delete (without prefix).
	 * @return bool True if the deletion was successful.
	 */
	public function delete( string $key ): bool {
		$prefixed_key = $this->get_prefixed_key( $key );
		return delete_option( $prefixed_key );
	}

	/**
	 * Checks if a key exists in storage
	 *
	 * @param string $key Key to check (without prefix).
	 * @return bool True if the key exists.
	 */
	public function has( string $key ): bool {
		$prefixed_key = $this->get_prefixed_key( $key );
		$sentinel     = new \stdClass();
		$value        = get_option( $prefixed_key, $sentinel );
		return $sentinel !== $value;
	}

	/**
	 * Gets the prefixed key
	 *
	 * @param string $key Key without prefix.
	 * @return string Prefixed key.
	 */
	private function get_prefixed_key( string $key ): string {
		return $this->prefix . $key;
	}
}
