<?php

namespace Endereco\Oxid7Client\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Core\Di\ContainerFacade;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ShopConfigurationDaoBridgeInterface;
use Endereco\Oxid7Client\Component\EnderecoService;

class UserComponent extends UserComponent_parent
{
    /**
     * Invalidates stale Endereco status codes on page load.
     *
     * Recalculates the address hash for billing and the selected shipping address.
     * If the stored hash doesn't match, the status codes are outdated (e.g. because
     * the address changed externally or the hash formula changed). In that case we
     * clear status, timestamp, and predictions so the frontend JS re-triggers validation.
     *
     * @return \OxidEsales\Eshop\Application\Model\User|false
     */
    public function render()
    {
        $return = parent::render();

        $oUser = $this->getUser();
        if ($oUser) {
            $enderecoService = new EnderecoService();

            // Billing address hash check.
            $hasSubdivisions = $enderecoService->countryHasSubdivisions(
                $oUser->oxuser__oxcountryid->rawValue
            );
            $hash = $this->calculateHash(
                $oUser->oxuser__oxcountryid->rawValue,
                $hasSubdivisions ? ($oUser->oxuser__oxstateid->rawValue ?? '') : null,
                $oUser->oxuser__oxzip->rawValue,
                $oUser->oxuser__oxcity->rawValue,
                $oUser->oxuser__oxstreet->rawValue,
                $oUser->oxuser__oxstreetnr->rawValue,
                $oUser->oxuser__oxaddinfo->rawValue
            );
            if ($hash !== ($oUser->oxuser__mojoaddresshash->rawValue ?? '')) {
                $oUser->oxuser__mojoamsstatus->rawValue = '';
                $oUser->oxuser__mojoamsts->rawValue = '';
                $oUser->oxuser__mojoamspredictions->rawValue = '';
                $oUser->oxuser__mojoaddresshash->rawValue = '';
                $oUser->save();
            }

            // Shipping address hash check.
            $oSelectedAddress = $oUser->getSelectedAddress();
            if ($oSelectedAddress) {
                $hasSubdivisions = $enderecoService->countryHasSubdivisions(
                    $oSelectedAddress->oxaddress__oxcountryid->rawValue
                );
                $hash = $this->calculateHash(
                    $oSelectedAddress->oxaddress__oxcountryid->rawValue,
                    $hasSubdivisions ? ($oSelectedAddress->oxaddress__oxstateid->rawValue ?? '') : null,
                    $oSelectedAddress->oxaddress__oxzip->rawValue,
                    $oSelectedAddress->oxaddress__oxcity->rawValue,
                    $oSelectedAddress->oxaddress__oxstreet->rawValue,
                    $oSelectedAddress->oxaddress__oxstreetnr->rawValue,
                    $oSelectedAddress->oxaddress__oxaddinfo->rawValue
                );
                if ($hash !== ($oSelectedAddress->oxaddress__mojoaddresshash->rawValue ?? '')) {
                    $oSelectedAddress->oxaddress__mojoamsstatus->rawValue = '';
                    $oSelectedAddress->oxaddress__mojoamsts->rawValue = '';
                    $oSelectedAddress->oxaddress__mojoamspredictions->rawValue = '';
                    $oSelectedAddress->oxaddress__mojoaddresshash->rawValue = '';
                    $oSelectedAddress->save();
                }
            }
        }

        return $return;
    }

    /**
     * This functions inspects POST and tries to find open sessions in it.
     * In case something is found, doaccountings are sent.
     *
     * This should happen before any validation
     * @see https://github.com/Endereco/endereco-oxid7-client/issues/9
     */
    private function findAndCloseEnderecoSessions()
    {

        $shopConfiguration = ContainerFacade::get(ShopConfigurationDaoBridgeInterface::class)->get();

        $aModuleConfiguration = $shopConfiguration->getModuleConfiguration('endereco-oxid7-client');

        $sApiKy = $aModuleConfiguration->getModuleSetting('sAPIKEY')->getValue();
        $sEndpoint = $aModuleConfiguration->getModuleSetting('sSERVICEURL')->getValue();

        $moduleVersion = $aModuleConfiguration->getVersion();

        $sAgentInfo = "Endereco Oxid7 Client v" . $moduleVersion;

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
                echo $e->getMessage();
            }
        }
    }

    // phpcs:disable
    public function changeuser_testvalues()
    {
        // phpcs:enable
        $this->findAndCloseEnderecoSessions();

        $return = parent::changeuser_testvalues();

        // Hash signature. We assume this logic is executed only from the frontend.
        $oUser = $this->getUser();
        $billingAmsWasInitiated = isset($_POST['billing_ams_session_counter']);
        $billingAmsWasUsed = intval($_POST['billing_ams_session_counter']) > 0;
        if ($oUser && $billingAmsWasInitiated && $billingAmsWasUsed) {
            $hasSubdivisions = (new EnderecoService())->countryHasSubdivisions(
                $oUser->oxuser__oxcountryid->rawValue
            );

            $hash = $this->calculateHash(
                $oUser->oxuser__oxcountryid->rawValue,
                $hasSubdivisions ? ($oUser->oxuser__oxstateid->rawValue ?? '') : null,
                $oUser->oxuser__oxzip->rawValue,
                $oUser->oxuser__oxcity->rawValue,
                $oUser->oxuser__oxstreet->rawValue,
                $oUser->oxuser__oxstreetnr->rawValue,
                $oUser->oxuser__oxaddinfo->rawValue
            );
            $oUser->oxuser__mojoaddresshash->rawValue = $hash;
            $oUser->save();
        }

        $aDelAddress = $this->getDelAddressData();
        $sAddressId = Registry::get(\OxidEsales\Eshop\Core\Request::class)->getRequestEscapedParameter('oxaddressid');
        $shippingAmsWasInitiated = isset($_POST['shipping_ams_session_counter']);
        $shippingAmsWasUsed = intval($_POST['shipping_ams_session_counter']) > 0;
        if ($aDelAddress && $sAddressId && $shippingAmsWasInitiated && $shippingAmsWasUsed) {
            $hasSubdivisions = (new EnderecoService())->countryHasSubdivisions(
                $aDelAddress['oxaddress__oxcountryid']
            );
            $hash = $this->calculateHash(
                $aDelAddress['oxaddress__oxcountryid'],
                $hasSubdivisions ? ($aDelAddress['oxaddress__oxstateid'] ?? '') : null,
                $aDelAddress['oxaddress__oxzip'],
                $aDelAddress['oxaddress__oxcity'],
                $aDelAddress['oxaddress__oxstreet'],
                $aDelAddress['oxaddress__oxstreetnr'],
                $aDelAddress['oxaddress__oxaddinfo']
            );

            $oAddress = oxNew(\OxidEsales\Eshop\Application\Model\Address::class);
            $oAddress->setId($sAddressId);
            $oAddress->load($sAddressId);

            $oAddress->oxaddress__mojoaddresshash->rawValue = $hash;
            $oAddress->save();
        }

        return $return;
    }

    public function changeUser()
    {
        $this->findAndCloseEnderecoSessions();
        $return = parent::changeUser();

        // Hash signature. We assume this logic is executed only from the frontend.
        $oUser = $this->getUser();
        $billingAmsWasInitiated = isset($_POST['billing_ams_session_counter']);
        $billingAmsWasUsed = intval($_POST['billing_ams_session_counter'] ?? 0) > 0;
        if ($oUser && $billingAmsWasInitiated && $billingAmsWasUsed) {
            $hasSubdivisions = (new EnderecoService())->countryHasSubdivisions(
                $oUser->oxuser__oxcountryid->rawValue
            );
            $hash = $this->calculateHash(
                $oUser->oxuser__oxcountryid->rawValue,
                $hasSubdivisions ? ($oUser->oxuser__oxstateid->rawValue ?? '') : null,
                $oUser->oxuser__oxzip->rawValue,
                $oUser->oxuser__oxcity->rawValue,
                $oUser->oxuser__oxstreet->rawValue,
                $oUser->oxuser__oxstreetnr->rawValue,
                $oUser->oxuser__oxaddinfo->rawValue
            );
            $oUser->oxuser__mojoaddresshash->rawValue = $hash;
            $oUser->save();
        }

        $aDelAddress = $this->getDelAddressData();
        $sAddressId = Registry::get(\OxidEsales\Eshop\Core\Request::class)->getRequestEscapedParameter('oxaddressid');
        $shippingAmsWasInitiated = isset($_POST['shipping_ams_session_counter']);
        $shippingAmsWasUsed = intval($_POST['shipping_ams_session_counter']) > 0;
        if ($aDelAddress && $sAddressId && $shippingAmsWasInitiated && $shippingAmsWasUsed) {
            $hasSubdivisions = (new EnderecoService())->countryHasSubdivisions(
                $aDelAddress['oxaddress__oxcountryid']
            );

            $hash = $this->calculateHash(
                $aDelAddress['oxaddress__oxcountryid'],
                $hasSubdivisions ? ($aDelAddress['oxaddress__oxstateid'] ?? '') : null,
                $aDelAddress['oxaddress__oxzip'],
                $aDelAddress['oxaddress__oxcity'],
                $aDelAddress['oxaddress__oxstreet'],
                $aDelAddress['oxaddress__oxstreetnr'],
                $aDelAddress['oxaddress__oxaddinfo']
            );

            $oAddress = oxNew(\OxidEsales\Eshop\Application\Model\Address::class);
            $oAddress->setId($sAddressId);
            $oAddress->load($sAddressId);

            $oAddress->oxaddress__mojoaddresshash->rawValue = $hash;
            $oAddress->save();
        }

        return $return;
    }

    public function createUser()
    {
        $this->findAndCloseEnderecoSessions();

        $return = parent::createUser();

        // Hash signature. We assume this logic is executed only from the frontend.
        $oUser = $this->getUser();
        $billingAmsWasInitiated = isset($_POST['billing_ams_session_counter']);
        $billingAmsWasUsed = intval($_POST['billing_ams_session_counter'] ?? 0) > 0;
        if ($oUser && $billingAmsWasInitiated && $billingAmsWasUsed) {
            $hasSubdivisions = (new EnderecoService())->countryHasSubdivisions(
                $oUser->oxuser__oxcountryid->rawValue
            );
            $hash = $this->calculateHash(
                $oUser->oxuser__oxcountryid->rawValue,
                $hasSubdivisions ? ($oUser->oxuser__oxstateid->rawValue ?? '') : null,
                $oUser->oxuser__oxzip->rawValue,
                $oUser->oxuser__oxcity->rawValue,
                $oUser->oxuser__oxstreet->rawValue,
                $oUser->oxuser__oxstreetnr->rawValue,
                $oUser->oxuser__oxaddinfo->rawValue
            );
            $oUser->oxuser__mojoaddresshash->rawValue = $hash;
            $oUser->save();
        }

        return $return;
    }

    /**
     * Calculates a hash based on the provided address components.
     * This is used to ensure the address integrity.
     *
     * TODO: Extract to a shared location — duplicated in OrderController and UserComponent.
     *
     * @param string $countryCode Country code of the address.
     * @param string|null $subdivisionCode ISO 3166-2 subdivision code, or null if not applicable.
     * @param string $postalCode Postal code of the address.
     * @param string $locality Locality (city) of the address.
     * @param string $streetName Street name of the address.
     * @param string $buildingNumber Building number of the address.
     * @param string $additionalInfo Additional information of the address.
     * @return string The calculated hash.
     */
    private function calculateHash(
        $countryCode,
        $subdivisionCode,
        $postalCode,
        $locality,
        $streetName,
        $buildingNumber,
        $additionalInfo
    ) {
        $hashBody = [
            'countryCode' => $countryCode,
            'postalCode' => $postalCode,
            'locality' => $locality,
            'streetName' => $streetName,
            'buildingNumber' => $buildingNumber,
            'additionalInfo' => $additionalInfo

        ];
        if ($subdivisionCode !== null) {
            $hashBody['subdivisionCode'] = $subdivisionCode;
        }
        return hash('sha256', json_encode($hashBody));
    }
}
