<?php

namespace Endereco\Oxid7Client\Component;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ShopConfigurationDaoBridgeInterface;

class EnderecoService
{
    private $apiKey;
    private $endpoint;
    private $moduleVer;

    // TODO: Extract cache into an injectable dependency for better testability.
    // For now the static coupling is acceptable — it avoids extra DB reads
    // when multiple EnderecoService instances query the same country per request.
    private static $subdivisionCache = [];

    /**
     * Checks if a country has subdivisions with ISO 3166-2 codes configured.
     *
     * This is a simple DB lookup with no dependency on instance state.
     * We keep it as an instance method (rather than static) for consistency
     * with the rest of this class and to allow injecting the DB dependency
     * in tests later without changing the call signature.
     *
     * @param string $countryId OXID internal country ID.
     * @return bool
     */
    public function countryHasSubdivisions($countryId): bool
    {
        if (array_key_exists($countryId, self::$subdivisionCache)) {
            return self::$subdivisionCache[$countryId];
        }


        $sql = "SELECT COUNT(*) FROM oxstates "
            . "WHERE OXCOUNTRYID = ? AND MOJOISO31662 <> '' AND MOJOISO31662 IS NOT NULL";
        $count = \OxidEsales\Eshop\Core\DatabaseProvider::getDb()->getOne(
            $sql,
            [$countryId]
        );

        // We naively assume, that if there are some states in the database for that country,
        // then the list is complete and 1-to-1 matches the states our API can recognize and correct.
        $countryHasSelectableStates = $count > 0;
        self::$subdivisionCache[$countryId] = $countryHasSelectableStates;

        return self::$subdivisionCache[$countryId];
    }

    /**
     * Maps an ISO 3166-2 subdivision code back to the OXID internal state ID.
     *
     * @param string $isoCode ISO 3166-2 code (e.g. "DE-BY").
     * @return string OXID state ID, or empty string if not found.
     */
    public function resolveSubdivisionToStateId($isoCode): string
    {
        $result = \OxidEsales\Eshop\Core\DatabaseProvider::getDb()->getOne(
            "SELECT OXID FROM oxstates WHERE UPPER(MOJOISO31662) = UPPER(?)",
            [$isoCode]
        );
        return $result ?: '';
    }


    public function __construct()
    {

        $shopConfiguration = ContainerFacade::get(ShopConfigurationDaoBridgeInterface::class)->get();
        $aModuleConfiguration = $shopConfiguration->getModuleConfiguration('endereco-oxid7-client');

        $this->apiKey = $aModuleConfiguration->getModuleSetting('sAPIKEY')->getValue();
        $this->endpoint = $aModuleConfiguration->getModuleSetting('sSERVICEURL')->getValue();
        $this->moduleVer = "Endereco Oxid7 Client v" . $aModuleConfiguration->getVersion();
    }

    /**
     * This functions inspects POST and tries to find open sessions in it.
     * In case something is found, doaccountings are sent.
     *
     * This should happen before any validation
     * @see https://github.com/Endereco/endereco-oxid7-client/issues/9
     */
    public function findAndCloseEnderecoSessions()
    {
        $shopConfiguration = ContainerFacade::get(ShopConfigurationDaoBridgeInterface::class)->get();
        $aModuleConfiguration = $shopConfiguration->getModuleConfiguration('endereco-oxid7-client');

        $sApiKy = $aModuleConfiguration->getModuleSetting('sAPIKEY')->getValue();
        $sEndpoint = $aModuleConfiguration->getModuleSetting('sSERVICEURL')->getValue();
        $sAgentInfo = "Endereco Oxid7 Client v" . $aModuleConfiguration->getVersion();

        $bAnyDoAccounting = false;

        if ($_POST) {
            foreach ($_POST as $sVarName => $sVarValue) {
                if ((strpos($sVarName, '_session_counter') !== false) && 0 < intval($sVarValue)) {
                    $sSessionIdName = str_replace('_session_counter', '', $sVarName) . '_session_id';
                    $sSessionId = $_POST[$sSessionIdName];
                    try {
                        $message = [
                            'jsonrpc' => '2.0',
                            'id' => 1,
                            'method' => 'doAccounting',
                            'params' => [
                                'sessionId' => $sSessionId
                            ]
                        ];
                        $client = new Client(['timeout' => 5.0]);

                        $newHeaders = [
                            'Content-Type' => 'application/json',
                            'X-Auth-Key' => $sApiKy,
                            'X-Transaction-Id' => $sSessionId,
                            'X-Transaction-Referer' => $_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : __FILE__,
                            'X-Agent' => $sAgentInfo,
                        ];
                        $request = new Request('POST', $sEndpoint, $newHeaders, json_encode($message));
                        $client->send($request);
                        $bAnyDoAccounting = true;
                    } catch (\Exception $e) {
                        // Do nothing.
                    }
                }
            }
        }

        if ($bAnyDoAccounting) {
            try {
                $message = [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'doConversion',
                    'params' => []
                ];
                $client = new Client(['timeout' => 5.0]);
                $newHeaders = [
                    'Content-Type' => 'application/json',
                    'X-Auth-Key' => $sApiKy,
                    'X-Transaction-Id' => 'not_required',
                    'X-Transaction-Referer' => $_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : __FILE__,
                    'X-Agent' => $sAgentInfo,
                ];
                $request = new Request('POST', $sEndpoint, $newHeaders, json_encode($message));
                $client->send($request);
            } catch (\Exception $e) {
                // Do nothing.
            }
        }
    }

    public function checkAddress($address)
    {
        try {
            $params = [
                'country' => $address['countryCode'],
                'language' => $address['__language'],
                'postCode' => $address['postalCode'],
                'cityName' => $address['locality'],
                'street' => $address['streetName'],
                'houseNumber' => $address['buildingNumber'],
                'additionalInfo' => $address['additionalInfo']
            ];

            if (array_key_exists('subdivisionCode', $address)) {
                $params['subdivisionCode'] = $address['subdivisionCode'];
            }

            $message = [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'addressCheck',
                'params' => $params

            ];

            $client = new Client(['timeout' => 6.0]);
            $sSessionId = $this->generateSessionId();
            $newHeaders = [
                'Content-Type' => 'application/json',
                'X-Auth-Key' => $this->apiKey,
                'X-Transaction-Id' => $sSessionId,
                'X-Transaction-Referer' => $_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : __FILE__,
                'X-Agent' => $this->moduleVer,
            ];

            $request = new Request('POST', $this->endpoint, $newHeaders, json_encode($message));
            $response = $client->send($request);
            $responseJson = $response->getBody()->getContents();
            $reponseArray = json_decode($responseJson, true);
            if (array_key_exists('result', $reponseArray)) {
                $result = $reponseArray['result'];

                $predictions = array();
                $maxPredictions = 6;
                $counter = 0;
                foreach ($result['predictions'] as $prediction) {
                    $tempAddress = array(
                        'countryCode' => $prediction['countryCode']
                            ?: $address['countryCode'],
                        'postalCode' => $prediction['postCode'],
                        'locality' => $prediction['cityName'],
                        'streetName' => $prediction['street'],
                        'buildingNumber' => $prediction['houseNumber']
                    );
                    if (array_key_exists('additionalInfo', $prediction)) {
                        $tempAddress['additionalInfo'] = $prediction['additionalInfo'];
                    }
                    if (array_key_exists('subdivisionCode', $prediction)) {
                        $tempAddress['subdivisionCode'] = $prediction['subdivisionCode'];
                    }

                    $predictions[] = $tempAddress;
                    $counter++;
                    if ($counter >= $maxPredictions) {
                        break;
                    }
                }

                $address['__predictions'] = json_encode($predictions);
                $address['__timestamp'] = time();
                $address['__status'] = implode(',', $this->normalizeStatusCodes($result['status']));
            }
        } catch (\Exception $e) {
            // Do nothing.
        }

        // If checked, send doAccounting.
        $bAnyDoAccounting = false;
        try {
            $message = [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'doAccounting',
                'params' => [
                    'sessionId' => $sSessionId
                ]
            ];
            $client = new Client(['timeout' => 5.0]);

            $newHeaders = [
                'Content-Type' => 'application/json',
                'X-Auth-Key' => $this->apiKey,
                'X-Transaction-Id' => $sSessionId,
                'X-Transaction-Referer' => $_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : __FILE__,
                'X-Agent' => $this->moduleVer,
            ];
            $request = new Request('POST', $this->endpoint, $newHeaders, json_encode($message));
            $client->send($request);
            $bAnyDoAccounting = true;
        } catch (\Exception $e) {
            // Do nothing.
        }

        if ($bAnyDoAccounting) {
            try {
                $message = [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'doConversion',
                    'params' => []
                ];
                $client = new Client(['timeout' => 5.0]);
                $newHeaders = [
                    'Content-Type' => 'application/json',
                    'X-Auth-Key' => $this->apiKey,
                    'X-Transaction-Id' => 'not_required',
                    'X-Transaction-Referer' => $_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : __FILE__,
                    'X-Agent' => $this->moduleVer,
                ];
                $request = new Request('POST', $this->endpoint, $newHeaders, json_encode($message));
                $client->send($request);
            } catch (\Exception $e) {
                // Do nothing.
            }
        }

        return $address;
    }

    public function generateSessionId()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    public function normalizeStatusCodes($statusCodes)
    {
        // Create an array of statuses.
        if (
            in_array('A1000', $statusCodes) &&
            !in_array('A1100', $statusCodes)
        ) {
            $statusCodes[] = 'address_correct';
            if (($key = array_search('A1000', $statusCodes)) !== false) {
                unset($statusCodes[$key]);
            }
        }
        if (
            in_array('A1000', $statusCodes) &&
            in_array('A1100', $statusCodes)
        ) {
            $statusCodes[] = 'address_needs_correction';
            if (($key = array_search('A1000', $statusCodes)) !== false) {
                unset($statusCodes[$key]);
            }
            if (($key = array_search('A1100', $statusCodes)) !== false) {
                unset($statusCodes[$key]);
            }
        }
        if (
            in_array('A2000', $statusCodes)
        ) {
            $statusCodes[] = 'address_multiple_variants';
            if (($key = array_search('A2000', $statusCodes)) !== false) {
                unset($statusCodes[$key]);
            }
        }
        if (
            in_array('A3000', $statusCodes)
        ) {
            $statusCodes[] = 'address_not_found';
            if (($key = array_search('A3000', $statusCodes)) !== false) {
                unset($statusCodes[$key]);
            }
        }
        if (
            in_array('A3100', $statusCodes)
        ) {
            $statusCodes[] = 'address_is_packstation';
            if (($key = array_search('A3100', $statusCodes)) !== false) {
                unset($statusCodes[$key]);
            }
        }

        $statusCodes = array_unique(array_values($statusCodes));

        return $statusCodes;
    }

    public function shouldBeChecked($statusCodes)
    {
        return !(
            in_array('address_not_found', $statusCodes) ||
            in_array('address_needs_correction', $statusCodes) ||
            in_array('address_correct', $statusCodes) ||
            in_array('address_multiple_variants', $statusCodes) ||
            in_array('address_of_not_supported_type', $statusCodes) ||
            in_array('address_selected_by_customer', $statusCodes)
        );
    }
}
