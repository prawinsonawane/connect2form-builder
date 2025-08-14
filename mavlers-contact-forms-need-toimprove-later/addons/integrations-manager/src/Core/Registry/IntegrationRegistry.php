<?php

namespace MavlersCF\Integrations\Core\Registry;

use MavlersCF\Integrations\Core\Interfaces\IntegrationInterface;

/**
 * Integration Registry
 * 
 * Manages registration and retrieval of integrations
 */
class IntegrationRegistry {

    private $integrations = [];

    /**
     * Register an integration
     */
    public function register(IntegrationInterface $integration): void {
        $this->integrations[$integration->getId()] = $integration;
    }

    /**
     * Get integration by ID
     */
    public function get(string $id): ?IntegrationInterface {
        return $this->integrations[$id] ?? null;
    }

    /**
     * Get all registered integrations
     */
    public function getAll(): array {
        return $this->integrations;
    }

    /**
     * Get all configured integrations
     */
    public function getConfigured(): array {
        return array_filter($this->integrations, function($integration) {
            return $integration->isConfigured();
        });
    }

    /**
     * Check if integration is registered
     */
    public function has(string $id): bool {
        return isset($this->integrations[$id]);
    }

    /**
     * Get integration count
     */
    public function count(): int {
        return count($this->integrations);
    }

    /**
     * Get integrations as array for JSON
     */
    public function toArray(): array {
        $data = [];
        
        foreach ($this->integrations as $integration) {
            $data[$integration->getId()] = [
                'id' => $integration->getId(),
                'name' => $integration->getName(),
                'description' => $integration->getDescription(),
                'version' => $integration->getVersion(),
                'icon' => $integration->getIcon(),
                'color' => $integration->getColor(),
                'configured' => $integration->isConfigured(),
                'actions' => $integration->getAvailableActions()
            ];
        }

        return $data;
    }
} 