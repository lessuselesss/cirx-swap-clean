<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Configuration Controller
 * 
 * Provides frontend with backend configuration for network synchronization
 */
class ConfigController
{
    /**
     * Get Circular network configuration for frontend synchronization
     * 
     * @OA\Get(
     *     path="/api/v1/config/circular-network",
     *     summary="Get Circular network configuration",
     *     tags={"Configuration"},
     *     @OA\Response(
     *         response=200,
     *         description="Circular network configuration",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="network", type="string", example="testnet"),
     *             @OA\Property(property="blockchain_id", type="string", example="8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2"),
     *             @OA\Property(property="nag_url", type="string", example="https://nag.circularlabs.io/NAG.php?cep="),
     *             @OA\Property(property="environment", type="string", example="development"),
     *             @OA\Property(property="chain_name", type="string", example="Circular SandBox")
     *         )
     *     )
     * )
     */
    public function getCircularNetworkConfig(Request $request, Response $response): Response
    {
        $config = $this->getCircularConfiguration();
        
        $response->getBody()->write(json_encode([
            'network' => $config['network'],
            'blockchain_id' => $config['blockchain_id'],
            'nag_url' => $config['nag_url'],
            'environment' => $config['environment'],
            'chain_name' => $config['chain_name'],
            'version' => '1.0.8',
            'timestamp' => date('c')
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get blockchain environment configuration
     * Same logic as backend blockchain clients
     */
    private function getCircularConfiguration(): array
    {
        $environment = $_ENV['APP_ENV'] ?? 'development';
        
        switch ($environment) {
            case 'production':
                return [
                    'network' => 'mainnet',
                    'environment' => 'production',
                    'blockchain_id' => '714d2ac07a826b66ac56752eebd7c77b58d2ee842e523d913fd0ef06e6bdfcae',
                    'nag_url' => $_ENV['CIRX_NAG_URL_MAINNET'] ?? 'https://nag.circularlabs.io/NAG_Mainnet.php?cep=',
                    'chain_name' => 'Circular Main Public'
                ];
                
            case 'staging':
                return [
                    'network' => 'testnet',
                    'environment' => 'staging', 
                    'blockchain_id' => 'acb8a9b79f3c663aa01be852cd42725f9e0e497fd849b436df51c5e074ebeb28',
                    'nag_url' => $_ENV['CIRX_NAG_URL_TESTNET'] ?? 'https://nag.circularlabs.io/NAG.php?cep=',
                    'chain_name' => 'Circular Secondary Public'
                ];
                
            case 'development':
            case 'testing':
            default:
                return [
                    'network' => 'testnet',
                    'environment' => 'development',
                    'blockchain_id' => '8a20baa40c45dc5055aeb26197c203e576ef389d9acb171bd62da11dc5ad72b2',
                    'nag_url' => $_ENV['CIRX_NAG_URL_TESTNET'] ?? 'https://nag.circularlabs.io/NAG.php?cep=',
                    'chain_name' => 'Circular SandBox'
                ];
        }
    }
}