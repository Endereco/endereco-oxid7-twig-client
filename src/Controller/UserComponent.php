<?php

namespace Endereco\Oxid7Client\Controller;

use OxidEsales\Eshop\Application\Model\Address;
use OxidEsales\Eshop\Core\Registry;
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
            $storedHash = $oUser->oxuser__mojoaddresshash->rawValue ?? '';
            if (
                $hash !== $storedHash
                && ($storedHash !== '' || $this->hasAmsDataToInvalidate($oUser, 'oxuser__mojo'))
            ) {
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
                $storedHash = $oSelectedAddress->oxaddress__mojoaddresshash->rawValue ?? '';
                if (
                    $hash !== $storedHash
                    && ($storedHash !== '' || $this->hasAmsDataToInvalidate($oSelectedAddress, 'oxaddress__mojo'))
                ) {
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

    // phpcs:disable
    public function changeuser_testvalues()
    {
        // phpcs:enable
        (new EnderecoService())->findAndCloseEnderecoSessions();

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

        $this->writeShippingAddressHash();

        return $return;
    }

    public function changeUser()
    {
        (new EnderecoService())->findAndCloseEnderecoSessions();
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

        $this->writeShippingAddressHash();

        return $return;
    }

    public function createUser()
    {
        (new EnderecoService())->findAndCloseEnderecoSessions();

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

        $this->writeShippingAddressHash();

        return $return;
    }

    /**
     * Writes shipping address hash to the database, using session
     * `deladrid` instead of the request's `oxaddressid`, which is
     * still empty on first-time address creation.
     */
    private function writeShippingAddressHash()
    {
        $shippingAmsWasInitiated = isset($_POST['shipping_ams_session_counter']);
        $shippingAmsWasUsed = intval($_POST['shipping_ams_session_counter'] ?? 0) > 0;
        if (!$shippingAmsWasInitiated || !$shippingAmsWasUsed) {
            return;
        }

        $sAddressId = Registry::getSession()->getVariable('deladrid');
        if (!$sAddressId) {
            return;
        }

        $oAddress = oxNew(Address::class);
        if (!$oAddress->load($sAddressId)) {
            return;
        }

        $hasSubdivisions = (new EnderecoService())->countryHasSubdivisions(
            $oAddress->oxaddress__oxcountryid->rawValue
        );
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
        $oAddress->save();
    }

    /**
     * Whether the object still holds AMS status, timestamp, or predictions
     * that a hash-mismatch invalidation in render() would need to clear.
     *
     * @param \OxidEsales\Eshop\Application\Model\User|\OxidEsales\Eshop\Application\Model\Address $oObject
     * @param string $sFieldPrefix e.g. 'oxuser__mojo' or 'oxaddress__mojo'
     * @return bool
     */
    private function hasAmsDataToInvalidate($oObject, $sFieldPrefix)
    {
        return ($oObject->{$sFieldPrefix . 'amsstatus'}->rawValue ?? '') !== ''
            || ($oObject->{$sFieldPrefix . 'amsts'}->rawValue ?? '') !== ''
            || ($oObject->{$sFieldPrefix . 'amspredictions'}->rawValue ?? '') !== '';
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
