<?php

namespace Connect2Form\Integrations\Core\Registry;

use Connect2Form\Integrations\Core\Interfaces\IntegrationInterface;

/**
 * Integration Registry
 *
 * Manages registration and retrieval of integrations
 *
 * @package Connect2Form
 * @since    2.0.0
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Integration Registry Class
 *
 * Manages registration and retrieval of integrations
 *
 * @since    2.0.0
 */
class IntegrationRegistry {

	/**
	 * Registered integrations.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      array    $integrations    Registered integrations.
	 */
	private $integrations = array();

	/**
	 * Register an integration
	 *
	 * @since    2.0.0
	 * @param    IntegrationInterface $integration Integration to register.
	 */
	public function register( IntegrationInterface $integration ): void {
		$this->integrations[ $integration->getId() ] = $integration;
	}

	/**
	 * Get integration by ID
	 *
	 * @since    2.0.0
	 * @param    string $id Integration ID.
	 * @return   IntegrationInterface|null
	 */
	public function get( string $id ): ?IntegrationInterface {
		return $this->integrations[ $id ] ?? null;
	}

	/**
	 * Get all registered integrations
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function getAll(): array {
		return $this->integrations;
	}

	/**
	 * Get all configured integrations
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function getConfigured(): array {
		return array_filter( $this->integrations, function( $integration ) {
			return $integration->isConfigured();
		} );
	}

	/**
	 * Check if integration is registered
	 *
	 * @since    2.0.0
	 * @param    string $id Integration ID.
	 * @return   bool
	 */
	public function has( string $id ): bool {
		return isset( $this->integrations[ $id ] );
	}

	/**
	 * Get integration count
	 *
	 * @since    2.0.0
	 * @return   int
	 */
	public function count(): int {
		return count( $this->integrations );
	}

	/**
	 * Get integrations as array for JSON
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	public function toArray(): array {
		$data = array();

		foreach ( $this->integrations as $integration ) {
			$data[ $integration->getId() ] = array(
				'id'          => $integration->getId(),
				'name'        => $integration->getName(),
				'description' => $integration->getDescription(),
				'version'     => $integration->getVersion(),
				'icon'        => $integration->getIcon(),
				'color'       => $integration->getColor(),
				'configured'  => $integration->isConfigured(),
				'actions'     => $integration->getAvailableActions(),
			);
		}

		return $data;
	}
} 
