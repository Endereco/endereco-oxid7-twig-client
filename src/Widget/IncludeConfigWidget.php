<?php

namespace Endereco\Oxid7Client\Widget;

use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Request;
use OxidEsales\Eshop\Core\Theme;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ModuleConfigurationDaoBridgeInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Facade\ModuleSettingServiceInterface;

class IncludeConfigWidget extends \OxidEsales\Eshop\Application\Component\Widget\WidgetController
{
    /**
     * @var string Widget template
     */
    protected $sThisTemplate = '@endereco-oxid7-client/enderecoconfig_default';

    /**
     * Getter for protected property.
     *
     * @return string
     */
    public function getThisTemplate()
    {
        return $this->sThisTemplate;
    }

    public function extendConfigMapping($configMapping = [])
    {
        return $configMapping;
    }

    /**
     * Render
     *
     * @return string Template name.
     */
    public function render()
    {
        parent::render();

        $oConfig = Registry::getConfig();

        $sOxId = Registry::get(Request::class)->getRequestEscapedParameter('oxid');
        if (!$sOxId) {
            $sOxId = $oConfig->getShopId();
        }

        $languageId = (int)Registry::getLang()->getBaseLanguage();

        $this->_aViewData['enderecoclient'] = [];


        $moduleConfiguration = ContainerFactory::getInstance()
            ->getContainer()
            ->get(ModuleConfigurationDaoBridgeInterface::class)
            ->get("endereco-oxid7-client");

        foreach ($moduleConfiguration->getModuleSettings() as $moduleSetting) {
            $this->_aViewData['enderecoclient'][$moduleSetting->getName()] = $moduleSetting->getValue();
            if ($moduleSetting->getName() == 'sAllowedControllerClasses') {
                $this->_aViewData['enderecoclient']['aAllowedControllerClasses'] =
                    explode(',', $moduleSetting->getValue());
            }
        }

        if (property_exists($this, '_aViewParams')) {
            $this->_aViewData['enderecoclient']['sControllerClass'] = $this->_aViewParams['curClass'];
        } else {
            $this->_aViewData['enderecoclient']['sControllerClass'] = '';
        }

        $this->_aViewData['enderecoclient']['sModuleVersion'] = $moduleConfiguration->getVersion();

        $oTheme = oxNew(Theme::class);
        $oTheme->load($oTheme->getActiveThemeId());
        $sThemeId = $oTheme->getId();

        $this->_aViewData['enderecoclient']['sThemeName'] = $sThemeId;

        $viewNameGenerator = \OxidEsales\Eshop\Core\Registry::get(\OxidEsales\Eshop\Core\TableViewNameGenerator::class);

        $sCountryTable = $viewNameGenerator->getViewName('oxcountry', $languageId, $sOxId);
        $sql = "SELECT `OXISOALPHA2`, `OXTITLE`, `OXID` FROM {$sCountryTable} WHERE `OXISOALPHA2` <> ''";
        $resultSet = \OxidEsales\Eshop\Core\DatabaseProvider::getDb()->getAll(
            $sql,
            []
        );
        $aCountries = [];
        $aCountryMapping = [];
        $aCountryMappingReverse = [];

        foreach ($resultSet as $result) {
            $aCountries[$result[0]] = $result[1];
            $aCountryMapping[strtoupper($result[0])] = $result[2];
            $aCountryMappingReverse[$result[2]] = strtoupper($result[0]);
        }
        $this->_aViewData['enderecoclient']['sCountries'] = json_encode($aCountries);
        $this->_aViewData['enderecoclient']['oCountryMapping'] = json_encode($aCountryMapping);
        $this->_aViewData['enderecoclient']['oCountryMappingReverse'] = json_encode($aCountryMappingReverse);

        $sStatesTable = $viewNameGenerator->getViewName('oxstates', $languageId, $sOxId);
        $sql =
            "SELECT c.`OXISOALPHA2`, s.`OXTITLE`, s.`OXID`, s.`MOJOISO31662`
                FROM {$sStatesTable} s
                JOIN oxcountry c ON s.`OXCOUNTRYID` = c.`OXID`
                WHERE s.`MOJOISO31662` <> '' AND s.`MOJOISO31662` IS NOT NULL";
        ;
        $resultSet = \OxidEsales\Eshop\Core\DatabaseProvider::getDb()->getAll(
            $sql,
            []
        );
        $aStates = [];
        $aStatesMapping = [];
        $aStatesMappingReverse = [];

        foreach ($resultSet as $result) {
            $countryCode = $result[0]; // e.g. "DE"
            $fullStateCode = $result[3]; // e.g. "DE-BW"
            $stateName = $result[1]; // e.g. "Baden-Würtemberg"
            $stateId = $result[2]; // any char(32) e.g. "1c34c5132c23c45.c5234c"

            $aStates[$fullStateCode] = $stateName;
            $aStatesMapping[mb_strtoupper($fullStateCode)] = $stateId;

            if (!isset($aStatesMappingReverse[$countryCode])) {
                $aStatesMappingReverse[$countryCode] = [];
            }
            $aStatesMappingReverse[$countryCode][$stateId] = mb_strtoupper($fullStateCode);
        }

        $this->_aViewData['enderecoclient']['oSubdivisions'] = json_encode($aStates);
        $this->_aViewData['enderecoclient']['oSubdivisionMapping'] = json_encode($aStatesMapping);
        $this->_aViewData['enderecoclient']['oSubdivisionMappingReverse'] = json_encode($aStatesMappingReverse);
        $this->_aViewData['enderecoclient']['sIntegratorConfigPayload'] = $this->buildIntegratorConfigPayload(
            $this->_aViewData['enderecoclient'],
            $oConfig
        );

        return $this->getThisTemplate();
    }

    /**
     * Builds the base64 encoded integrator config payload.
     *
     * The API key is intentionally not exposed to the frontend; RPC requests
     * are forwarded server-side through the ProxyController (cl=enderecoproxy).
     *
     * @param array<string, mixed> $settings
     * @param \OxidEsales\Eshop\Core\Config $config
     *
     * @return string
     */
    private function buildIntegratorConfigPayload($settings, $config)
    {
        $shopUrl = (string) $config->getCurrentShopUrl(false);
        $stoken = Registry::getSession()->getSessionChallengeToken();
        $apiUrl = $shopUrl . 'index.php?cl=enderecoproxy&stoken=' . $stoken;
        $integratorConfig = [
            'apiUrl' => $apiUrl,
            'showDebugInfo' => $this->toBool($settings['bShowDebug'] ?? false),
            'trigger' => [
                'onblur' => $this->toBool($settings['sAMSBLURTRIGGER'] ?? false),
                'onsubmit' => $this->toBool($settings['sAMSSubmitTrigger'] ?? false),
            ],
            'ux' => [
                'resumeSubmit' => $this->toBool($settings['sAMSResumeSubmit'] ?? false),
                'smartFill' => $this->toBool($settings['sSMARTFILL'] ?? false),
                'checkExisting' => $this->toBool($settings['sCHECKALL'] ?? false),
                'changeFieldsOrder' => $this->toBool($settings['bChangeFieldsOrder'] ?? false),
                'showEmailStatus' => $this->toBool($settings['bShowEmailserviceErrors'] ?? false),
                'useStandardCss' => $this->toBool($settings['bUseCss'] ?? false),
                'allowCloseModal' => $this->toBool($settings['bAllowCloseModal'] ?? false),
                'confirmWithCheckbox' => $this->toBool($settings['bConfirmWithCheckbox'] ?? false),
                'correctTranspositionedNames' => $this->toBool($settings['bCorrectTranspositionedNames'] ?? false),
            ],
            'templates' => [
                'primaryButtonClasses' => 'btn btn-primary',
                'secondaryButtonClasses' => 'btn btn-secondary',
            ],
        ];

        return base64_encode(json_encode($integratorConfig));
    }

    /**
     * Normalizes a config value to a boolean.
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function toBool($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(
            strtolower((string) $value),
            ['1', 'true', 'yes', 'on'],
            true
        );
    }
}
