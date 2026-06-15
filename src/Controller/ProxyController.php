<?php

namespace Endereco\Oxid7Client\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ModuleConfigurationDaoBridgeInterface;

class ProxyController extends \OxidEsales\Eshop\Application\Controller\FrontendController
{
    /**
     * Module id used to resolve the module configuration.
     */
    private const MODULE_ID = 'endereco-oxid7-client';

    /**
     * Forwards Endereco RPC requests server-side.
     *
     * @return string
     */
    public function render()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            $this->respondJson(['error' => 'Only POST requests are allowed.']);
        }

        if (!Registry::getSession()->checkSessionChallenge()) {
            http_response_code(403);
            $this->respondJson(['error' => 'Invalid or missing session token.']);
        }

        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            http_response_code(400);
            $this->respondJson(['error' => 'Invalid JSON payload.']);
        }

        $moduleConfiguration = ContainerFactory::getInstance()
            ->getContainer()
            ->get(ModuleConfigurationDaoBridgeInterface::class)
            ->get(self::MODULE_ID);

        $settings = [];
        foreach ($moduleConfiguration->getModuleSettings() as $moduleSetting) {
            $settings[$moduleSetting->getName()] = $moduleSetting->getValue();
        }

        $apiKey = trim((string) ($settings['sAPIKEY'] ?? ''));
        $endpoint = trim((string) ($settings['sSERVICEURL'] ?? ''));
        $agentInfo = 'Endereco Oxid7 Client v' . (string) $moduleConfiguration->getVersion();

        if ('' === $apiKey || '' === $endpoint) {
            http_response_code(500);
            $this->respondJson(['error' => 'Endereco configuration is incomplete.']);
        }

        $transactionId = (string) ($payload['id'] ?? '');
        if ('' === $transactionId) {
            $transactionId = bin2hex(random_bytes(8));
        }

        $transactionReferer = isset($_SERVER['HTTP_REFERER'])
            ? (string) $_SERVER['HTTP_REFERER']
            : __FILE__;

        $client = new Client(['timeout' => 8.0]);
        $headers = [
            'Content-Type' => 'application/json',
            'X-Auth-Key' => $apiKey,
            'X-Transaction-Id' => $transactionId,
            'X-Transaction-Referer' => $transactionReferer,
            'X-Agent' => $agentInfo,
        ];

        try {
            $request = new Request('POST', $endpoint, $headers, (string) json_encode($payload));
            $response = $client->send($request);
            http_response_code($response->getStatusCode());
            $this->respondRaw((string) $response->getBody());
        } catch (\Throwable $exception) {
            http_response_code(502);
            $this->respondJson(['error' => 'Endereco backend request failed.']);
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return never
     */
    private function respondJson($payload)
    {
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit();
    }

    /**
     * @param string $body
     *
     * @return never
     */
    private function respondRaw($body)
    {
        header('Content-Type: application/json');
        echo $body;
        exit();
    }
}
