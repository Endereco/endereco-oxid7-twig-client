<?php

namespace Endereco\Oxid7Client\Controller\Admin;

use OxidEsales\Eshop\Application\Controller\Admin\AdminController;
use OxidEsales\Eshop\Application\Model\Order;

class Addresscheck extends AdminController
{
    // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore -- OXID core property, underscore prefix required
    protected $_sThisTemplate = '@endereco-oxid7-client/admin/addresscheck';

    private $predictions = [
        'countryCode',
        'postalCode',
        'locality',
        'streetName',
        'buildingNumber',
        'subdivisionCode',
        'additionalInfo'
    ];

    public function render()
    {

        parent::render();

        $soxId = $this->getEditObjectId();
        $oOrder = oxNew(Order::class);
        if ($oOrder->load($soxId)) {
            $this->_aViewData['mojoamsstatus'] = explode(",", $oOrder->oxorder__mojoamsstatus->rawValue);
            $this->_aViewData['mojoamsts'] = date("d-m-Y H:i:s", intval($oOrder->oxorder__mojoamsts->rawValue));
            $this->_aViewData['mojoamspredictions'] =
                $this->getPredictions($oOrder->oxorder__mojoamspredictions->rawValue);
            $this->_aViewData['mojonamescore'] = $oOrder->oxorder__mojonamescore->rawValue;
        }

        return $this->_sThisTemplate;
    }

    protected function getPredictions($predictionsJSON)
    {
        $predictions = [];
        if ($predictionsJSON) {
            $predictions_array = json_decode($predictionsJSON);
            foreach ($predictions_array as $prediction) {
                $predictions[] = $this->getPredictionHtml($prediction);
            }
        }

        return $predictions;
    }

    protected function getPredictionHtml($prediction)
    {
        $predictionHTML = "";
        foreach ($this->predictions as $predictionKey) {
            $predictionHTML .= "<div class='prediction'>"
                . $predictionKey . ": " . ($prediction->$predictionKey ?? "") . "</div>";
        }

        return $predictionHTML;
    }
}
