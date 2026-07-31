<?php

namespace Endereco\Oxid7Client\Controller\Admin;

class UserAddress extends UserAddress_parent
{
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
        $tplName = parent::render();
        $oAddress = $this->_aViewData["edit"];

        $this->_aViewData['mojoamsstatus'] = $oAddress->oxaddress__mojoamsstatus->rawValue
            ? explode(",", $oAddress->oxaddress__mojoamsstatus->rawValue)
            : "";
        $this->_aViewData['mojoamsts'] = $oAddress->oxaddress__mojoamsts->rawValue
            ? date("d-m-Y H:i:s", intval($oAddress->oxaddress__mojoamsts->rawValue))
            : 0;
        $this->_aViewData['mojoamspredictions'] =
            $this->getPredictions($oAddress->oxaddress__mojoamspredictions->rawValue);
        $this->_aViewData['mojonamescore'] = $oAddress->oxaddress__mojonamescore->rawValue;

        return $tplName;
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
