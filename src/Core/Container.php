<?php
/**
 * Container Dependency Injection
 *
 * Container DI lightweight senza singleton pattern.
 * Usa istanze condivise gestite dal container, NON singleton statici.
 *
 * @package OpsHealthDashboard\Core
 */

namespace OpsHealthDashboard\Core;

/**
 * Class Container
 *
 * Container per dependency injection semplice.
 * NO singleton pattern, NO metodi static, NO modificatore final.
 */
class Container {

	/**
	 * Bindings registrati
	 *
	 * @var array
	 */
	private $bindings = [];

	/**
	 * Istanze condivise (non singleton - gestite dal container)
	 *
	 * @var array
	 */
	private $shared = [];

	/**
	 * Istanze registrate
	 *
	 * @var array
	 */
	private $instances = [];

	/**
	 * Associa un abstract a un'implementazione concreta
	 *
	 * Crea una nuova istanza ad ogni chiamata a make().
	 *
	 * @param string   $abstract Identificatore abstract.
	 * @param callable $concrete Closure che restituisce l'istanza.
	 * @return void
	 */
	public function bind( string $abstract, callable $concrete ): void {
		$this->bindings[ $abstract ] = $concrete;
	}

	/**
	 * Registra un binding condiviso
	 *
	 * Crea l'istanza una volta, la riusa nelle chiamate successive a make().
	 * NON è un singleton pattern - gestito dal container, non auto-gestito.
	 *
	 * @param string   $abstract Identificatore abstract.
	 * @param callable $concrete Closure che restituisce l'istanza.
	 * @return void
	 */
	public function share( string $abstract, callable $concrete ): void {
		$this->shared[ $abstract ] = $concrete;
	}

	/**
	 * Registra un'istanza esistente
	 *
	 * @param string $abstract Identificatore abstract.
	 * @param mixed  $instance Istanza da registrare.
	 * @return void
	 */
	public function instance( string $abstract, $instance ): void {
		$this->instances[ $abstract ] = $instance;
	}

	/**
	 * Risolve un abstract dal container
	 *
	 * @param string $abstract Identificatore abstract.
	 * @return mixed Istanza risolta.
	 * @throws \Exception Se nessun binding trovato.
	 */
	public function make( string $abstract ) {
		if ( isset( $this->instances[ $abstract ] ) ) {
			return $this->instances[ $abstract ];
		}

		if ( isset( $this->shared[ $abstract ] ) ) {
			if ( ! isset( $this->instances[ $abstract ] ) ) {
				$this->instances[ $abstract ] = $this->shared[ $abstract ]( $this );
			}
			return $this->instances[ $abstract ];
		}

		if ( isset( $this->bindings[ $abstract ] ) ) {
			return $this->bindings[ $abstract ]( $this );
		}

		throw new \Exception( "No binding found for [{$abstract}]" );
	}
}
