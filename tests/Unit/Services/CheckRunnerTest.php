<?php
/**
 * Unit Test per CheckRunner
 *
 * Test unitario con Brain\Monkey per CheckRunner.
 *
 * @package OpsHealthDashboard\Tests\Unit\Services
 */

namespace OpsHealthDashboard\Tests\Unit\Services;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OpsHealthDashboard\Interfaces\CheckInterface;
use OpsHealthDashboard\Interfaces\RedactionInterface;
use OpsHealthDashboard\Interfaces\StorageInterface;
use OpsHealthDashboard\Services\CheckRunner;
use PHPUnit\Framework\TestCase;

/**
 * Class CheckRunnerTest
 *
 * Unit test per CheckRunner.
 */
class CheckRunnerTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Setup per ogni test
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Teardown dopo ogni test
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Crea un mock RedactionInterface per i test
	 *
	 * @return \Mockery\MockInterface
	 */
	private function create_redaction_mock() {
		$redaction = Mockery::mock( RedactionInterface::class );
		$redaction->shouldReceive( 'redact' )
			->andReturnUsing( function ( $text ) {
				return $text;
			} );
		return $redaction;
	}

	/**
	 * Testa che CheckRunner può essere istanziato con dipendenze
	 */
	public function test_check_runner_can_be_instantiated() {
		$storage   = Mockery::mock( StorageInterface::class );
		$redaction = $this->create_redaction_mock();
		$runner    = new CheckRunner( $storage, $redaction );

		$this->assertInstanceOf( CheckRunner::class, $runner );
	}

	/**
	 * Testa che add_check() aggiunge un check e viene eseguito in run_all()
	 */
	public function test_add_check_adds_check() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'set' )->andReturn( true );
		$redaction = $this->create_redaction_mock();

		$check = Mockery::mock( CheckInterface::class );
		$check->shouldReceive( 'is_enabled' )->andReturn( true );
		$check->shouldReceive( 'get_id' )->andReturn( 'test_check' );
		$check->shouldReceive( 'get_name' )->andReturn( 'Test Check' );
		$check->shouldReceive( 'run' )->once()->andReturn( [
			'status'   => 'ok',
			'message'  => 'OK',
			'details'  => [],
			'duration' => 0.1,
		] );

		$runner = new CheckRunner( $storage, $redaction );
		$runner->add_check( $check );

		$results = $runner->run_all();
		$this->assertArrayHasKey( 'test_check', $results );
	}

	/**
	 * Testa che run_all() esegue tutti i check abilitati
	 */
	public function test_run_all_executes_enabled_checks() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'set' )
			->once()
			->with( 'latest_results', Mockery::type( 'array' ) )
			->andReturn( true );
		$redaction = $this->create_redaction_mock();

		$check1 = Mockery::mock( CheckInterface::class );
		$check1->shouldReceive( 'is_enabled' )->andReturn( true );
		$check1->shouldReceive( 'get_id' )->andReturn( 'check1' );
		$check1->shouldReceive( 'get_name' )->andReturn( 'Check 1' );
		$check1->shouldReceive( 'run' )->once()->andReturn( [
			'status'   => 'ok',
			'message'  => 'Check 1 OK',
			'details'  => [],
			'duration' => 0.1,
		] );

		$runner = new CheckRunner( $storage, $redaction );
		$runner->add_check( $check1 );

		$results = $runner->run_all();

		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'check1', $results );
		$this->assertEquals( 'ok', $results['check1']['status'] );
	}

	/**
	 * Testa che run_all() salta i check disabilitati
	 */
	public function test_run_all_skips_disabled_checks() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'set' )->andReturn( true );
		$redaction = $this->create_redaction_mock();

		$check1 = Mockery::mock( CheckInterface::class );
		$check1->shouldReceive( 'is_enabled' )->andReturn( false );
		$check1->shouldReceive( 'get_id' )->andReturn( 'check1' );
		$check1->shouldReceive( 'run' )->never();

		$runner = new CheckRunner( $storage, $redaction );
		$runner->add_check( $check1 );

		$results = $runner->run_all();

		$this->assertIsArray( $results );
		$this->assertEmpty( $results );
	}

	/**
	 * Testa che run_all() gestisce multiple checks
	 */
	public function test_run_all_handles_multiple_checks() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'set' )->andReturn( true );
		$redaction = $this->create_redaction_mock();

		$check1 = Mockery::mock( CheckInterface::class );
		$check1->shouldReceive( 'is_enabled' )->andReturn( true );
		$check1->shouldReceive( 'get_id' )->andReturn( 'check1' );
		$check1->shouldReceive( 'get_name' )->andReturn( 'Check 1' );
		$check1->shouldReceive( 'run' )->once()->andReturn( [
			'status'   => 'ok',
			'message'  => 'Check 1 OK',
			'details'  => [],
			'duration' => 0.1,
		] );

		$check2 = Mockery::mock( CheckInterface::class );
		$check2->shouldReceive( 'is_enabled' )->andReturn( true );
		$check2->shouldReceive( 'get_id' )->andReturn( 'check2' );
		$check2->shouldReceive( 'get_name' )->andReturn( 'Check 2' );
		$check2->shouldReceive( 'run' )->once()->andReturn( [
			'status'   => 'warning',
			'message'  => 'Check 2 Warning',
			'details'  => [],
			'duration' => 0.2,
		] );

		$runner = new CheckRunner( $storage, $redaction );
		$runner->add_check( $check1 );
		$runner->add_check( $check2 );

		$results = $runner->run_all();

		$this->assertCount( 2, $results );
		$this->assertArrayHasKey( 'check1', $results );
		$this->assertArrayHasKey( 'check2', $results );
		$this->assertEquals( 'ok', $results['check1']['status'] );
		$this->assertEquals( 'warning', $results['check2']['status'] );
	}

	/**
	 * Testa che run_all() salva i risultati nello storage
	 */
	public function test_run_all_saves_results_to_storage() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'set' )
			->once()
			->with( 'latest_results', Mockery::on( function ( $arg ) {
				return is_array( $arg ) && isset( $arg['check1'] );
			} ) )
			->andReturn( true );
		$redaction = $this->create_redaction_mock();

		$check = Mockery::mock( CheckInterface::class );
		$check->shouldReceive( 'is_enabled' )->andReturn( true );
		$check->shouldReceive( 'get_id' )->andReturn( 'check1' );
		$check->shouldReceive( 'get_name' )->andReturn( 'Check 1' );
		$check->shouldReceive( 'run' )->andReturn( [
			'status'   => 'ok',
			'message'  => 'OK',
			'details'  => [],
			'duration' => 0.1,
		] );

		$runner = new CheckRunner( $storage, $redaction );
		$runner->add_check( $check );
		$results = $runner->run_all();

		// Verifica che il risultato sia stato salvato nello storage.
		$this->assertArrayHasKey( 'check1', $results );
	}

	/**
	 * Testa che get_latest_results() recupera i risultati dallo storage
	 */
	public function test_get_latest_results_retrieves_from_storage() {
		$stored_results = [
			'check1' => [
				'status'  => 'ok',
				'message' => 'Stored result',
			],
		];

		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )
			->once()
			->with( 'latest_results', [] )
			->andReturn( $stored_results );
		$redaction = $this->create_redaction_mock();

		$runner  = new CheckRunner( $storage, $redaction );
		$results = $runner->get_latest_results();

		$this->assertEquals( $stored_results, $results );
	}

	/**
	 * Testa che run_all() gestisce eccezioni nei check senza crash
	 */
	public function test_run_all_handles_check_exceptions() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'set' )->andReturn( true );

		$redaction = Mockery::mock( RedactionInterface::class );
		$redaction->shouldReceive( 'redact' )
			->once()
			->with( 'Test exception' )
			->andReturn( '[REDACTED]' );

		$check = Mockery::mock( CheckInterface::class );
		$check->shouldReceive( 'is_enabled' )->andReturn( true );
		$check->shouldReceive( 'get_id' )->andReturn( 'failing_check' );
		$check->shouldReceive( 'get_name' )->andReturn( 'Failing Check' );
		$check->shouldReceive( 'run' )->once()->andThrow( new \RuntimeException( 'Test exception' ) );

		$runner = new CheckRunner( $storage, $redaction );
		$runner->add_check( $check );

		$results = $runner->run_all();

		$this->assertArrayHasKey( 'failing_check', $results );
		$this->assertEquals( 'critical', $results['failing_check']['status'] );
		$this->assertStringContainsString( '[REDACTED]', $results['failing_check']['message'] );
		$this->assertEquals( 'Failing Check', $results['failing_check']['name'] );
	}

	/**
	 * Testa che get_latest_results() ritorna array vuoto se storage ritorna non-array
	 */
	public function test_get_latest_results_returns_empty_array_for_non_array() {
		$storage = Mockery::mock( StorageInterface::class );
		$storage->shouldReceive( 'get' )
			->once()
			->with( 'latest_results', [] )
			->andReturn( 'not_an_array' );
		$redaction = $this->create_redaction_mock();

		$runner  = new CheckRunner( $storage, $redaction );
		$results = $runner->get_latest_results();

		$this->assertIsArray( $results );
		$this->assertEmpty( $results );
	}

	/**
	 * Testa che la classe NON è final
	 */
	public function test_class_is_not_final() {
		$reflection = new \ReflectionClass( CheckRunner::class );
		$this->assertFalse( $reflection->isFinal(), 'CheckRunner should NOT be final' );
	}

	/**
	 * Testa che NON esistono metodi static
	 */
	public function test_no_static_methods() {
		$reflection = new \ReflectionClass( CheckRunner::class );
		$methods    = $reflection->getMethods( \ReflectionMethod::IS_STATIC );

		$static_methods = array_filter(
			$methods,
			function ( $method ) {
				return strpos( $method->getName(), '__' ) !== 0;
			}
		);

		$this->assertEmpty( $static_methods, 'CheckRunner should have NO static methods' );
	}

	/**
	 * Testa che NON esistono proprietà static
	 */
	public function test_no_static_properties() {
		$reflection = new \ReflectionClass( CheckRunner::class );
		$properties = $reflection->getProperties( \ReflectionProperty::IS_STATIC );

		$this->assertEmpty( $properties, 'CheckRunner should have NO static properties' );
	}
}
