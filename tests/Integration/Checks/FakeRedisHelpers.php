<?php
/**
 * FakeRedis Helper Classes (PHP 8.0+)
 *
 * Uses union return types to match phpredis extension signatures.
 * Loaded conditionally when PHP_VERSION_ID >= 80000.
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
	 * @return \Redis|bool Never reached.
	 * @throws \Exception Always.
	 */
	public function auth( $credentials ): \Redis|bool {
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
	 * @return \Redis|string|bool Never reached.
	 * @throws \Exception Always.
	 */
	public function set( $key, $value, $options = null ): \Redis|string|bool {
		throw new \Exception( 'READONLY You cannot write' );
	}

	/**
	 * Simulates DEL
	 *
	 * @param mixed $key       Key.
	 * @param mixed ...$otherKeys Other keys.
	 * @return \Redis|int|false 0.
	 */
	public function del( $key, ...$otherKeys ): \Redis|int|false {
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
	 * @return \Redis|string|bool True.
	 */
	public function set( $key, $value, $options = null ): \Redis|string|bool {
		return true;
	}

	/**
	 * Simulates GET with wrong value
	 *
	 * @param string $key Key.
	 * @return mixed Wrong value.
	 */
	public function get( $key ): mixed {
		return 'wrong_value';
	}

	/**
	 * Simulates DEL
	 *
	 * @param mixed $key       Key.
	 * @param mixed ...$otherKeys Other keys.
	 * @return \Redis|int|false 1.
	 */
	public function del( $key, ...$otherKeys ): \Redis|int|false {
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
	 * @return \Redis|bool Never reached.
	 * @throws \Exception Always.
	 */
	public function select( $db ): \Redis|bool {
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
	 * @return \Redis|string|bool False.
	 */
	public function set( $key, $value, $options = null ): \Redis|string|bool {
		return false;
	}

	/**
	 * Simulates DEL
	 *
	 * @param mixed $key       Key.
	 * @param mixed ...$otherKeys Other keys.
	 * @return \Redis|int|false 0.
	 */
	public function del( $key, ...$otherKeys ): \Redis|int|false {
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
	 * Simulates slow SET (300ms, threshold 100ms, 3x margin)
	 *
	 * @param string $key     Key.
	 * @param mixed  $value   Value.
	 * @param mixed  $options Options.
	 * @return \Redis|string|bool True.
	 */
	public function set( $key, $value, $options = null ): \Redis|string|bool {
		usleep( 300000 );
		return true;
	}

	/**
	 * Simulates correct GET
	 *
	 * @param string $key Key.
	 * @return mixed Correct value.
	 */
	public function get( $key ): mixed {
		return RedisCheck::SMOKE_TEST_VALUE;
	}

	/**
	 * Simulates DEL
	 *
	 * @param mixed $key       Key.
	 * @param mixed ...$otherKeys Other keys.
	 * @return \Redis|int|false 1.
	 */
	public function del( $key, ...$otherKeys ): \Redis|int|false {
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
	 * @return \Redis|bool Never reached.
	 * @throws \Exception Always.
	 */
	public function auth( $credentials ): \Redis|bool {
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
	 * @return \Redis|string|bool False.
	 */
	public function set( $key, $value, $options = null ): \Redis|string|bool {
		return false;
	}

	/**
	 * Simulates DEL that throws exception
	 *
	 * @param mixed $key       Key.
	 * @param mixed ...$otherKeys Other keys.
	 * @return \Redis|int|false Never reached.
	 * @throws \Exception Always.
	 */
	public function del( $key, ...$otherKeys ): \Redis|int|false {
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
