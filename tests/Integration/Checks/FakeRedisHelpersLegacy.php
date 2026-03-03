<?php
/**
 * FakeRedis Helper Classes (PHP 7.4 compatible)
 *
 * No union return types — phpredis on PHP 7.4 does not declare them.
 * Loaded conditionally when PHP_VERSION_ID < 80000.
 *
 * @package OpsHealthDashboard\Tests\Integration\Checks
 */

namespace OpsHealthDashboard\Tests\Integration\Checks;

use OpsHealthDashboard\Checks\RedisCheck;

/**
 * Fake Redis that simulates authentication failure
 *
 * connect() succeeds, auth() throws exception.
 */
class FakeRedisAuthFail extends \Redis {

	/**
	 * Simulates successful connection
	 *
	 * @param string      $host           Redis host.
	 * @param int         $port           Redis port.
	 * @param float       $timeout        Connection timeout.
	 * @param string|null $persistent_id  Persistent ID.
	 * @param int         $retry_interval Retry interval.
	 * @param float       $read_timeout   Read timeout.
	 * @param array|null  $context        Context.
	 * @return bool True.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function connect(
		$host,
		$port = 6379,
		$timeout = 0.0,
		$persistent_id = null,
		$retry_interval = 0,
		$read_timeout = 0.0,
		$context = null
	): bool {
		return true;
	}

	/**
	 * Simulates authentication failure
	 *
	 * @param mixed $credentials Credentials.
	 * @return bool Never reached.
	 * @throws \Exception Always.
	 */
	public function auth( $credentials ) {
		throw new \Exception( 'WRONGPASS invalid password' );
	}

	/**
	 * Simulates connection close
	 *
	 * @return bool True.
	 */
	public function close(): bool {
		return true;
	}
}

/**
 * Fake Redis that simulates smoke test failure (set throws exception)
 *
 * connect() succeeds, set() throws exception.
 */
class FakeRedisSmokeTestFail extends \Redis {

	/**
	 * Simulates successful connection
	 *
	 * @param string      $host           Redis host.
	 * @param int         $port           Redis port.
	 * @param float       $timeout        Connection timeout.
	 * @param string|null $persistent_id  Persistent ID.
	 * @param int         $retry_interval Retry interval.
	 * @param float       $read_timeout   Read timeout.
	 * @param array|null  $context        Context.
	 * @return bool True.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function connect(
		$host,
		$port = 6379,
		$timeout = 0.0,
		$persistent_id = null,
		$retry_interval = 0,
		$read_timeout = 0.0,
		$context = null
	): bool {
		return true;
	}

	/**
	 * Simulates SET failure
	 *
	 * @param string $key     Key.
	 * @param mixed  $value   Value.
	 * @param mixed  $options Options.
	 * @return bool Never reached.
	 * @throws \Exception Always.
	 */
	public function set( $key, $value, $options = null ) {
		throw new \Exception( 'READONLY You cannot write' );
	}

	/**
	 * Simulates DEL
	 *
	 * @param mixed $key       Key.
	 * @param mixed ...$otherKeys Other keys.
	 * @return int 0.
	 */
	public function del( $key, ...$otherKeys ) {
		return 0;
	}

	/**
	 * Simulates close
	 *
	 * @return bool True.
	 */
	public function close(): bool {
		return true;
	}
}

/**
 * Fake Redis that simulates GET mismatch
 *
 * connect() succeeds, set() succeeds, get() returns wrong value.
 */
class FakeRedisGetMismatch extends \Redis {

	/**
	 * Simulates successful connection
	 *
	 * @param string      $host           Redis host.
	 * @param int         $port           Redis port.
	 * @param float       $timeout        Connection timeout.
	 * @param string|null $persistent_id  Persistent ID.
	 * @param int         $retry_interval Retry interval.
	 * @param float       $read_timeout   Read timeout.
	 * @param array|null  $context        Context.
	 * @return bool True.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function connect(
		$host,
		$port = 6379,
		$timeout = 0.0,
		$persistent_id = null,
		$retry_interval = 0,
		$read_timeout = 0.0,
		$context = null
	): bool {
		return true;
	}

	/**
	 * Simulates successful SET
	 *
	 * @param string $key     Key.
	 * @param mixed  $value   Value.
	 * @param mixed  $options Options.
	 * @return bool True.
	 */
	public function set( $key, $value, $options = null ) {
		return true;
	}

	/**
	 * Simulates GET with wrong value
	 *
	 * @param string $key Key.
	 * @return string Wrong value.
	 */
	public function get( $key ) {
		return 'wrong_value';
	}

	/**
	 * Simulates DEL
	 *
	 * @param mixed $key       Key.
	 * @param mixed ...$otherKeys Other keys.
	 * @return int 1.
	 */
	public function del( $key, ...$otherKeys ) {
		return 1;
	}

	/**
	 * Simulates close
	 *
	 * @return bool True.
	 */
	public function close(): bool {
		return true;
	}
}

/**
 * Fake Redis that simulates database selection failure
 *
 * connect() succeeds, select() throws exception.
 */
class FakeRedisDbSelectFail extends \Redis {

	/**
	 * Simulates successful connection
	 *
	 * @param string      $host           Redis host.
	 * @param int         $port           Redis port.
	 * @param float       $timeout        Connection timeout.
	 * @param string|null $persistent_id  Persistent ID.
	 * @param int         $retry_interval Retry interval.
	 * @param float       $read_timeout   Read timeout.
	 * @param array|null  $context        Context.
	 * @return bool True.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function connect(
		$host,
		$port = 6379,
		$timeout = 0.0,
		$persistent_id = null,
		$retry_interval = 0,
		$read_timeout = 0.0,
		$context = null
	): bool {
		return true;
	}

	/**
	 * Simulates database selection failure
	 *
	 * @param int $db Database number.
	 * @return bool Never reached.
	 * @throws \Exception Always.
	 */
	public function select( $db ) {
		throw new \Exception( 'ERR DB index is out of range' );
	}

	/**
	 * Simulates close
	 *
	 * @return bool True.
	 */
	public function close(): bool {
		return true;
	}
}

/**
 * Fake Redis that simulates SET returning false
 *
 * connect() succeeds, set() returns false.
 */
class FakeRedisSetFalse extends \Redis {

	/**
	 * Simulates successful connection
	 *
	 * @param string      $host           Redis host.
	 * @param int         $port           Redis port.
	 * @param float       $timeout        Connection timeout.
	 * @param string|null $persistent_id  Persistent ID.
	 * @param int         $retry_interval Retry interval.
	 * @param float       $read_timeout   Read timeout.
	 * @param array|null  $context        Context.
	 * @return bool True.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function connect(
		$host,
		$port = 6379,
		$timeout = 0.0,
		$persistent_id = null,
		$retry_interval = 0,
		$read_timeout = 0.0,
		$context = null
	): bool {
		return true;
	}

	/**
	 * Simulates SET returning false
	 *
	 * @param string $key     Key.
	 * @param mixed  $value   Value.
	 * @param mixed  $options Options.
	 * @return bool False.
	 */
	public function set( $key, $value, $options = null ) {
		return false;
	}

	/**
	 * Simulates DEL
	 *
	 * @param mixed $key       Key.
	 * @param mixed ...$otherKeys Other keys.
	 * @return int 0.
	 */
	public function del( $key, ...$otherKeys ) {
		return 0;
	}

	/**
	 * Simulates close
	 *
	 * @return bool True.
	 */
	public function close(): bool {
		return true;
	}
}

/**
 * Fake Redis that simulates slow response
 *
 * connect() succeeds, set/get/del succeed but with delay > 100ms.
 */
class FakeRedisSlowResponse extends \Redis {

	/**
	 * Simulates successful connection
	 *
	 * @param string      $host           Redis host.
	 * @param int         $port           Redis port.
	 * @param float       $timeout        Connection timeout.
	 * @param string|null $persistent_id  Persistent ID.
	 * @param int         $retry_interval Retry interval.
	 * @param float       $read_timeout   Read timeout.
	 * @param array|null  $context        Context.
	 * @return bool True.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function connect(
		$host,
		$port = 6379,
		$timeout = 0.0,
		$persistent_id = null,
		$retry_interval = 0,
		$read_timeout = 0.0,
		$context = null
	): bool {
		return true;
	}

	/**
	 * Simulates slow SET (150ms)
	 *
	 * @param string $key     Key.
	 * @param mixed  $value   Value.
	 * @param mixed  $options Options.
	 * @return bool True.
	 */
	public function set( $key, $value, $options = null ) {
		usleep( 300000 );
		return true;
	}

	/**
	 * Simulates correct GET
	 *
	 * @param string $key Key.
	 * @return string Correct value.
	 */
	public function get( $key ) {
		return RedisCheck::SMOKE_TEST_VALUE;
	}

	/**
	 * Simulates DEL
	 *
	 * @param mixed $key       Key.
	 * @param mixed ...$otherKeys Other keys.
	 * @return int 1.
	 */
	public function del( $key, ...$otherKeys ) {
		return 1;
	}

	/**
	 * Simulates close
	 *
	 * @return bool True.
	 */
	public function close(): bool {
		return true;
	}
}

/**
 * Fake Redis where auth fails and close throws exception
 *
 * Covers the catch block in close_connection().
 */
class FakeRedisCloseFail extends \Redis {

	/**
	 * Simulates successful connection
	 *
	 * @param string      $host           Redis host.
	 * @param int         $port           Redis port.
	 * @param float       $timeout        Connection timeout.
	 * @param string|null $persistent_id  Persistent ID.
	 * @param int         $retry_interval Retry interval.
	 * @param float       $read_timeout   Read timeout.
	 * @param array|null  $context        Context.
	 * @return bool True.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function connect(
		$host,
		$port = 6379,
		$timeout = 0.0,
		$persistent_id = null,
		$retry_interval = 0,
		$read_timeout = 0.0,
		$context = null
	): bool {
		return true;
	}

	/**
	 * Simulates authentication failure
	 *
	 * @param mixed $credentials Credentials.
	 * @return bool Never reached.
	 * @throws \Exception Always.
	 */
	public function auth( $credentials ) {
		throw new \Exception( 'Auth failed' );
	}

	/**
	 * Simulates close that throws exception
	 *
	 * @return bool Never reached.
	 * @throws \Exception Always.
	 */
	public function close(): bool {
		throw new \Exception( 'Close failed' );
	}
}

/**
 * Fake Redis where SET returns false and del throws exception
 *
 * Covers the catch block in cleanup_and_close().
 */
class FakeRedisCleanupFail extends \Redis {

	/**
	 * Simulates successful connection
	 *
	 * @param string      $host           Redis host.
	 * @param int         $port           Redis port.
	 * @param float       $timeout        Connection timeout.
	 * @param string|null $persistent_id  Persistent ID.
	 * @param int         $retry_interval Retry interval.
	 * @param float       $read_timeout   Read timeout.
	 * @param array|null  $context        Context.
	 * @return bool True.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function connect(
		$host,
		$port = 6379,
		$timeout = 0.0,
		$persistent_id = null,
		$retry_interval = 0,
		$read_timeout = 0.0,
		$context = null
	): bool {
		return true;
	}

	/**
	 * Simulates SET returning false
	 *
	 * @param string $key     Key.
	 * @param mixed  $value   Value.
	 * @param mixed  $options Options.
	 * @return bool False.
	 */
	public function set( $key, $value, $options = null ) {
		return false;
	}

	/**
	 * Simulates DEL that throws exception
	 *
	 * @param mixed $key       Key.
	 * @param mixed ...$otherKeys Other keys.
	 * @return int Never reached.
	 * @throws \Exception Always.
	 */
	public function del( $key, ...$otherKeys ) {
		throw new \Exception( 'DEL failed during cleanup' );
	}

	/**
	 * Simulates close that throws exception
	 *
	 * @return bool Never reached.
	 * @throws \Exception Always.
	 */
	public function close(): bool {
		throw new \Exception( 'Close failed during cleanup' );
	}
}
