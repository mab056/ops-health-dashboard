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
 * Fake Redis che simula fallimento autenticazione
 *
 * connect() riesce, auth() lancia eccezione.
 */
class FakeRedisAuthFail extends \Redis {

	/**
	 * Simula connessione riuscita
	 *
	 * @param string      $host           Host Redis.
	 * @param int         $port           Porta Redis.
	 * @param float       $timeout        Timeout connessione.
	 * @param string|null $persistent_id  ID persistente.
	 * @param int         $retry_interval Intervallo retry.
	 * @param float       $read_timeout   Timeout lettura.
	 * @param array|null  $context        Contesto.
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
	 * Simula fallimento autenticazione
	 *
	 * @param mixed $credentials Credenziali.
	 * @return \Redis|bool Mai raggiunto.
	 * @throws \Exception Sempre.
	 */
	public function auth( $credentials ): \Redis|bool {
		throw new \Exception( 'WRONGPASS invalid password' );
	}

	/**
	 * Simula chiusura connessione
	 *
	 * @return bool True.
	 */
	public function close(): bool {
		return true;
	}
}

/**
 * Fake Redis che simula fallimento smoke test (set lancia eccezione)
 *
 * connect() riesce, set() lancia eccezione.
 */
class FakeRedisSmokeTestFail extends \Redis {

	/**
	 * Simula connessione riuscita
	 *
	 * @param string      $host           Host Redis.
	 * @param int         $port           Porta Redis.
	 * @param float       $timeout        Timeout connessione.
	 * @param string|null $persistent_id  ID persistente.
	 * @param int         $retry_interval Intervallo retry.
	 * @param float       $read_timeout   Timeout lettura.
	 * @param array|null  $context        Contesto.
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
	 * Simula fallimento SET
	 *
	 * @param string $key     Chiave.
	 * @param mixed  $value   Valore.
	 * @param mixed  $options Opzioni.
	 * @return \Redis|string|bool Mai raggiunto.
	 * @throws \Exception Sempre.
	 */
	public function set( $key, $value, $options = null ): \Redis|string|bool {
		throw new \Exception( 'READONLY You cannot write' );
	}

	/**
	 * Simula DEL
	 *
	 * @param mixed $key       Chiave.
	 * @param mixed ...$otherKeys Altre chiavi.
	 * @return \Redis|int|false 0.
	 */
	public function del( $key, ...$otherKeys ): \Redis|int|false {
		return 0;
	}

	/**
	 * Simula chiusura
	 *
	 * @return bool True.
	 */
	public function close(): bool {
		return true;
	}
}

/**
 * Fake Redis che simula GET mismatch
 *
 * connect() riesce, set() riesce, get() ritorna valore sbagliato.
 */
class FakeRedisGetMismatch extends \Redis {

	/**
	 * Simula connessione riuscita
	 *
	 * @param string      $host           Host Redis.
	 * @param int         $port           Porta Redis.
	 * @param float       $timeout        Timeout connessione.
	 * @param string|null $persistent_id  ID persistente.
	 * @param int         $retry_interval Intervallo retry.
	 * @param float       $read_timeout   Timeout lettura.
	 * @param array|null  $context        Contesto.
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
	 * Simula SET riuscito
	 *
	 * @param string $key     Chiave.
	 * @param mixed  $value   Valore.
	 * @param mixed  $options Opzioni.
	 * @return \Redis|string|bool True.
	 */
	public function set( $key, $value, $options = null ): \Redis|string|bool {
		return true;
	}

	/**
	 * Simula GET con valore sbagliato
	 *
	 * @param string $key Chiave.
	 * @return mixed Valore sbagliato.
	 */
	public function get( $key ): mixed {
		return 'wrong_value';
	}

	/**
	 * Simula DEL
	 *
	 * @param mixed $key       Chiave.
	 * @param mixed ...$otherKeys Altre chiavi.
	 * @return \Redis|int|false 1.
	 */
	public function del( $key, ...$otherKeys ): \Redis|int|false {
		return 1;
	}

	/**
	 * Simula chiusura
	 *
	 * @return bool True.
	 */
	public function close(): bool {
		return true;
	}
}

/**
 * Fake Redis che simula fallimento selezione database
 *
 * connect() riesce, select() lancia eccezione.
 */
class FakeRedisDbSelectFail extends \Redis {

	/**
	 * Simula connessione riuscita
	 *
	 * @param string      $host           Host Redis.
	 * @param int         $port           Porta Redis.
	 * @param float       $timeout        Timeout connessione.
	 * @param string|null $persistent_id  ID persistente.
	 * @param int         $retry_interval Intervallo retry.
	 * @param float       $read_timeout   Timeout lettura.
	 * @param array|null  $context        Contesto.
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
	 * Simula fallimento selezione database
	 *
	 * @param int $db Numero database.
	 * @return \Redis|bool Mai raggiunto.
	 * @throws \Exception Sempre.
	 */
	public function select( $db ): \Redis|bool {
		throw new \Exception( 'ERR DB index is out of range' );
	}

	/**
	 * Simula chiusura
	 *
	 * @return bool True.
	 */
	public function close(): bool {
		return true;
	}
}

/**
 * Fake Redis che simula SET che restituisce false
 *
 * connect() riesce, set() restituisce false.
 */
class FakeRedisSetFalse extends \Redis {

	/**
	 * Simula connessione riuscita
	 *
	 * @param string      $host           Host Redis.
	 * @param int         $port           Porta Redis.
	 * @param float       $timeout        Timeout connessione.
	 * @param string|null $persistent_id  ID persistente.
	 * @param int         $retry_interval Intervallo retry.
	 * @param float       $read_timeout   Timeout lettura.
	 * @param array|null  $context        Contesto.
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
	 * Simula SET che restituisce false
	 *
	 * @param string $key     Chiave.
	 * @param mixed  $value   Valore.
	 * @param mixed  $options Opzioni.
	 * @return \Redis|string|bool False.
	 */
	public function set( $key, $value, $options = null ): \Redis|string|bool {
		return false;
	}

	/**
	 * Simula DEL
	 *
	 * @param mixed $key       Chiave.
	 * @param mixed ...$otherKeys Altre chiavi.
	 * @return \Redis|int|false 0.
	 */
	public function del( $key, ...$otherKeys ): \Redis|int|false {
		return 0;
	}

	/**
	 * Simula chiusura
	 *
	 * @return bool True.
	 */
	public function close(): bool {
		return true;
	}
}

/**
 * Fake Redis che simula risposta lenta
 *
 * connect() riesce, set/get/del riescono ma con ritardo > 100ms.
 */
class FakeRedisSlowResponse extends \Redis {

	/**
	 * Simula connessione riuscita
	 *
	 * @param string      $host           Host Redis.
	 * @param int         $port           Porta Redis.
	 * @param float       $timeout        Timeout connessione.
	 * @param string|null $persistent_id  ID persistente.
	 * @param int         $retry_interval Intervallo retry.
	 * @param float       $read_timeout   Timeout lettura.
	 * @param array|null  $context        Contesto.
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
	 * Simula SET lento (300ms, soglia 100ms, margine 3x)
	 *
	 * @param string $key     Chiave.
	 * @param mixed  $value   Valore.
	 * @param mixed  $options Opzioni.
	 * @return \Redis|string|bool True.
	 */
	public function set( $key, $value, $options = null ): \Redis|string|bool {
		usleep( 300000 );
		return true;
	}

	/**
	 * Simula GET corretto
	 *
	 * @param string $key Chiave.
	 * @return mixed Valore corretto.
	 */
	public function get( $key ): mixed {
		return RedisCheck::SMOKE_TEST_VALUE;
	}

	/**
	 * Simula DEL
	 *
	 * @param mixed $key       Chiave.
	 * @param mixed ...$otherKeys Altre chiavi.
	 * @return \Redis|int|false 1.
	 */
	public function del( $key, ...$otherKeys ): \Redis|int|false {
		return 1;
	}

	/**
	 * Simula chiusura
	 *
	 * @return bool True.
	 */
	public function close(): bool {
		return true;
	}
}

/**
 * Fake Redis dove auth fallisce e close lancia eccezione
 *
 * Copre il catch block in close_connection().
 */
class FakeRedisCloseFail extends \Redis {

	/**
	 * Simula connessione riuscita
	 *
	 * @param string      $host           Host Redis.
	 * @param int         $port           Porta Redis.
	 * @param float       $timeout        Timeout connessione.
	 * @param string|null $persistent_id  ID persistente.
	 * @param int         $retry_interval Intervallo retry.
	 * @param float       $read_timeout   Timeout lettura.
	 * @param array|null  $context        Contesto.
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
	 * Simula fallimento autenticazione
	 *
	 * @param mixed $credentials Credenziali.
	 * @return \Redis|bool Mai raggiunto.
	 * @throws \Exception Sempre.
	 */
	public function auth( $credentials ): \Redis|bool {
		throw new \Exception( 'Auth failed' );
	}

	/**
	 * Simula chiusura che lancia eccezione
	 *
	 * @return bool Mai raggiunto.
	 * @throws \Exception Sempre.
	 */
	public function close(): bool {
		throw new \Exception( 'Close failed' );
	}
}

/**
 * Fake Redis dove SET ritorna false e del lancia eccezione
 *
 * Copre il catch block in cleanup_and_close().
 */
class FakeRedisCleanupFail extends \Redis {

	/**
	 * Simula connessione riuscita
	 *
	 * @param string      $host           Host Redis.
	 * @param int         $port           Porta Redis.
	 * @param float       $timeout        Timeout connessione.
	 * @param string|null $persistent_id  ID persistente.
	 * @param int         $retry_interval Intervallo retry.
	 * @param float       $read_timeout   Timeout lettura.
	 * @param array|null  $context        Contesto.
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
	 * Simula SET che ritorna false
	 *
	 * @param string $key     Chiave.
	 * @param mixed  $value   Valore.
	 * @param mixed  $options Opzioni.
	 * @return \Redis|string|bool False.
	 */
	public function set( $key, $value, $options = null ): \Redis|string|bool {
		return false;
	}

	/**
	 * Simula DEL che lancia eccezione
	 *
	 * @param mixed $key       Chiave.
	 * @param mixed ...$otherKeys Altre chiavi.
	 * @return \Redis|int|false Mai raggiunto.
	 * @throws \Exception Sempre.
	 */
	public function del( $key, ...$otherKeys ): \Redis|int|false {
		throw new \Exception( 'DEL failed during cleanup' );
	}

	/**
	 * Simula chiusura che lancia eccezione
	 *
	 * @return bool Mai raggiunto.
	 * @throws \Exception Sempre.
	 */
	public function close(): bool {
		throw new \Exception( 'Close failed during cleanup' );
	}
}
