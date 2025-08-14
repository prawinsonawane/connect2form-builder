<?php

namespace MavlersCF\Integrations\Core\Registry;

use MavlersCF\Integrations\Core\Interfaces\IntegrationInterface;

/**
 * Integration Registry
 * 
 * Manages registration and retrieval of integrations
 */
class IntegrationRegistry {

	/**
	 * Registered integrations
	 *
	 * @var array
	 */
	private $integrations = array();

	/**
	 * Register an integration
	 *
	 * @param IntegrationInterface $integration Integration instance.
	 */
	public function register( IntegrationInterface $integration ): void {
		$this->integrations[ $integration->getId() ] = $integration;
	}

	/**
	 * Get integration by ID
	 *
	 * @param string $id Integration ID.
	 * @return IntegrationInterface|null
	 */
	public function get( string $id ): ?IntegrationInterface {
		return $this->integrations[ $id ] ?? null;
	}

	/**
	 * Get all registered integrations
	 *
	 * @return array
	 */
	public function getAll(): array {
		return $this->integrations;
	}

	/**
	 * Get all configured integrations
	 *
	 * @return array
	 */
	public function getConfigured(): array {
		return array_filter( $this->integrations, function( $integration ) {
			return $integration->isConfigured();
		} );
	}

	/**
	 * Check if integration is registered
	 *
	 * @param string $id Integration ID.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->integrations[ $id ] );
	}

	/**
	 * Get integration count
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->integrations );
	}

	/**
	 * Get integrations as array for JSON
	 *
	 * @return array
	 */
	public function toArray(): array {
		$data = array();
		
		foreach ( $this->integrations as $integration ) {
			$data[ $integration->getId() ] = array(
				'id' => $integration->getId(),
				'name' => $integration->getName(),
				'description' => $integration->getDescription(),
				'version' => $integration->getVersion(),
				'icon' => $integration->getIcon(),
				'color' => $integration->getColor(),
				'configured' => $integration->isConfigured(),
				'actions' => $integration->getAvailableActions(),
			);
		}

		return $data;
	}
} 