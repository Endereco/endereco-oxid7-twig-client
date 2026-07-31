<?php

namespace Endereco\Oxid7Client\Controller\Admin;

class UserMain extends UserMain_parent
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

    /** @inheritdoc */
    public function render()
    {
        $tplName = parent::render();
        $oUser = $this->_aViewData["edit"];

        $this->_aViewData['mojoamsstatus'] = $oUser->oxuser__mojoamsstatus->rawValue
            ? explode(",", $oUser->oxuser__mojoamsstatus->rawValue)
            : "";
        $this->_aViewData['mojoamsts'] = $oUser->oxuser__mojoamsts->rawValue
            ? date("d-m-Y H:i:s", intval($oUser->oxuser__mojoamsts->rawValue))
            : 0;
        $this->_aViewData['mojoamspredictions'] =
            $this->getPredictions($oUser->oxuser__mojoamspredictions->rawValue);
        $this->_aViewData['mojonamescore'] = $oUser->oxuser__mojonamescore->rawValue;


        return $tplName;
    }

    /**
     * Saves main user parameters.
     *
     * @return mixed
     */
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
