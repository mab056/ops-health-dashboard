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
	 * @return bool Mai raggiunto.
	 * @throws \Exception Sempre.
	 */
	public function auth( $credentials ) {
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
	 * @return bool Mai raggiunto.
	 * @throws \Exception Sempre.
	 */
	public function set( $key, $value, $options = null ) {
		throw new \Exception( 'READONLY You cannot write' );
	}

	/**
	 * Simula DEL
	 *
	 * @param mixed $key       Chiave.
	 * @param mixed ...$otherKeys Altre chiavi.
	 * @return int 0.
	 */
	public function del( $key, ...$otherKeys ) {
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
	 * @return bool True.
	 */
	public function set( $key, $value, $options = null ) {
		return true;
	}

	/**
	 * Simula GET con valore sbagliato
	 *
	 * @param string $key Chiave.
	 * @return string Valore sbagliato.
	 */
	public function get( $key ) {
		return 'wrong_value';
	}

	/**
	 * Simula DEL
	 *
	 * @param mixed $key       Chiave.
	 * @param mixed ...$otherKeys Altre chiavi.
	 * @return int 1.
	 */
	public function del( $key, ...$otherKeys ) {
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
	 * @return bool Mai raggiunto.
	 * @throws \Exception Sempre.
	 */
	public function select( $db ) {
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
	 * @return bool False.
	 */
	public function set( $key, $value, $options = null ) {
		return false;
	}

	/**
	 * Simula DEL
	 *
	 * @param mixed $key       Chiave.
	 * @param mixed ...$otherKeys Altre chiavi.
	 * @return int 0.
	 */
	public function del( $key, ...$otherKeys ) {
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
	 * Simula SET lento (150ms)
	 *
	 * @param string $key     Chiave.
	 * @param mixed  $value   Valore.
	 * @param mixed  $options Opzioni.
	 * @return bool True.
	 */
	public function set( $key, $value, $options = null ) {
		usleep( 300000 );
		return true;
	}

	/**
	 * Simula GET corretto
	 *
	 * @param string $key Chiave.
	 * @return string Valore corretto.
	 */
	public function get( $key ) {
		return RedisCheck::SMOKE_TEST_VALUE;
	}

	/**
	 * Simula DEL
	 *
	 * @param mixed $key       Chiave.
	 * @param mixed ...$otherKeys Altre chiavi.
	 * @return int 1.
	 */
	public function del( $key, ...$otherKeys ) {
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
	 * @return bool Mai raggiunto.
	 * @throws \Exception Sempre.
	 */
	public function auth( $credentials ) {
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
	 * @return bool False.
	 */
	public function set( $key, $value, $options = null ) {
		return false;
	}

	/**
	 * Simula DEL che lancia eccezione
	 *
	 * @param mixed $key       Chiave.
	 * @param mixed ...$otherKeys Altre chiavi.
	 * @return int Mai raggiunto.
	 * @throws \Exception Sempre.
	 */
	public function del( $key, ...$otherKeys ) {
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
