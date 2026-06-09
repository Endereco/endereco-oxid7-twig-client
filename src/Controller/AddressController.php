<?php

namespace Endereco\Oxid7Client\Controller;

use Override;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Application\Model\Address;
use  Endereco\Oxid7Client\Component\EnderecoService;

class AddressController extends \OxidEsales\Eshop\Application\Controller\FrontendController
{
    /**
     * @return string
     */
    public function render()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $addressChanged = 1;
        if ('editBillingAddress' == $data['method']) {
            // Save billing address.
            $oUser = oxNew(User::class);
            if ($oUser->load($data['params']['addressId'])) {
                $oUser->oxuser__oxzip->rawValue
                    = $data['params']['address']['postalCode']
                    ? $data['params']['address']['postalCode']
                    : $oUser->oxuser__oxzip->rawValue;

                $oUser->oxuser__oxcity->rawValue
                    = $data['params']['address']['locality']
                    ? $data['params']['address']['locality']
                    : $oUser->oxuser__oxcity->rawValue;

                $oUser->oxuser__oxstreet->rawValue
                    = $data['params']['address']['streetName']
                    ? $data['params']['address']['streetName']
                    : $oUser->oxuser__oxstreet->rawValue;

                $oUser->oxuser__oxstreetnr->rawValue
                    = $data['params']['address']['buildingNumber']
                    ? $data['params']['address']['buildingNumber']
                    : $oUser->oxuser__oxstreetnr->rawValue;

                $oUser->oxuser__oxaddinfo->rawValue
                    = $data['params']['address']['additionalInfo']
                    ? $data['params']['address']['additionalInfo']
                    : $oUser->oxuser__oxaddinfo->rawValue;

                $enderecoService = new EnderecoService();
                $hasSubdivisions = $enderecoService->countryHasSubdivisions(
                    $oUser->oxuser__oxcountryid->rawValue
                );
                if ($hasSubdivisions && !empty($data['params']['address']['subdivisionCode'])) {
                    $oUser->oxuser__oxstateid->rawValue = $enderecoService->resolveSubdivisionToStateId(
                        $data['params']['address']['subdivisionCode']
                    );
                }


                $status = implode(',', $data['params']['enderecometa']['status']);
                $oUser->oxuser__mojoamsstatus->rawValue = $status;
                $oUser->oxuser__mojoamsts->rawValue = $data['params']['enderecometa']['ts'];
                $predictions = json_encode($data['params']['enderecometa']['predictions']);
                $oUser->oxuser__mojoamspredictions->rawValue = $predictions;

                // When writing address through endereco modal, always recalculate hash.
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
                if ($oUser->save()) {
                    $addressChanged = 2;
                }
            }
        }

        if ('editShippingAddress' == $data['method']) {
            // Save shipping address.
            $oAddress = oxNew(Address::class);
            if ($oAddress->load($data['params']['addressId'])) {
                $oAddress->oxaddress__oxzip->rawValue
                    = $data['params']['address']['postalCode']
                    ? $data['params']['address']['postalCode']
                    : $oAddress->oxaddress__oxzip->rawValue;

                $oAddress->oxaddress__oxcity->rawValue
                    = $data['params']['address']['locality']
                    ? $data['params']['address']['locality']
                    : $oAddress->oxaddress__oxcity->rawValue;

                $oAddress->oxaddress__oxstreet->rawValue
                    = $data['params']['address']['streetName']
                    ? $data['params']['address']['streetName']
                    : $oAddress->oxaddress__oxstreet->rawValue;

                $oAddress->oxaddress__oxstreetnr->rawValue
                    = $data['params']['address']['buildingNumber']
                    ? $data['params']['address']['buildingNumber']
                    : $oAddress->oxaddress__oxstreetnr->rawValue;

                $oAddress->oxaddress__oxaddinfo->rawValue
                    = $data['params']['address']['additionalInfo']
                    ? $data['params']['address']['additionalInfo']
                    : $oAddress->oxaddress__oxaddinfo->rawValue;

                $enderecoService = new EnderecoService();
                $hasSubdivisions = $enderecoService->countryHasSubdivisions(
                    $oAddress->oxaddress__oxcountryid->rawValue
                );
                if ($hasSubdivisions && !empty($data['params']['address']['subdivisionCode'])) {
                    $oAddress->oxaddress__oxstateid->rawValue = $enderecoService->resolveSubdivisionToStateId(
                        $data['params']['address']['subdivisionCode']
                    );
                }

                $status = implode(',', $data['params']['enderecometa']['status']);
                $oAddress->oxaddress__mojoamsstatus->rawValue = $status;
                $oAddress->oxaddress__mojoamsts->rawValue = $data['params']['enderecometa']['ts'];
                $predictions = json_encode($data['params']['enderecometa']['predictions']);
                $oAddress->oxaddress__mojoamspredictions->rawValue = $predictions;

                // When writing address through endereco modal, always recalculate hash.
                $hash = $this->calculateHash(
                    $oAddress->oxaddress__oxcountryid->rawValue,
                    $hasSubdivisions ? ($oAddress->oxaddress__oxstateid->rawValue ?? '') : null,
                    $oAddress->oxaddress__oxzip->rawValue,
                    $oAddress->oxaddress__oxcity->rawValue,
                    $oAddress->oxaddress__oxstreet->rawValue,
                    $oAddress->oxaddress__oxstreetnr->rawValue,
                    $oAddress->oxaddress__oxaddinfo->rawValue
                );
                $oAddress->oxaddress__mojoaddresshash->rawValue = $hash;
                if ($oAddress->save()) {
                    $addressChanged = 2;
                }
            }
        }

        echo $addressChanged;
        exit;
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
