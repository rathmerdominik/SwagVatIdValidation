<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace SwagVatIdValidation\Components\Validators;

use SwagVatIdValidation\Components\EUStates;
use SwagVatIdValidation\Components\VatIdCustomerInformation;
use SwagVatIdValidation\Components\VatIdInformation;
use SwagVatIdValidation\Components\VatIdValidatorResult;

/**
 * Dummy validation
 * The dummy validator checks if the VAT ID could be valid. Empty VAT IDs are also okay.
 * The validator fails when:
 * - VAT ID is shorter than 4 or longer than 14 chars
 * - Country Code includes non-alphabetical chars
 * - VAT Number includes non-alphanumerical chars
 * - VAT Number only has alphabetical chars
 */
class DummyVatIdValidator implements VatIdValidatorInterface
{
    /**
     * @var VatIdValidatorResult
     */
    private $result;

    /**
     * @var \Shopware_Components_Config
     */
    private $config;

    /**
     * Constructor sets the snippet namespace
     *
     * @param \Shopware_Components_Snippet_Manager $snippetManager
     */
    public function __construct(\Shopware_Components_Snippet_Manager $snippetManager)
    {
        $this->result = new VatIdValidatorResult($snippetManager, 'dummyValidator');
        $this->config = Shopware()->Container()->get('config');
    }

    /**
     * {@inheritdoc}
     */
    public function check(VatIdCustomerInformation $customerInformation, VatIdInformation $shopInformation = null)
    {
        $exceptedNonEuISOs = $this->config->get('disabledCountryISOs');

        if (!is_array($exceptedNonEuISOs)) {
            $exceptedNonEuISOs = explode(',', $exceptedNonEuISOs);
        }
        $exceptedNonEuISOs = array_map('trim', $exceptedNonEuISOs);

        $isExcepted = in_array($customerInformation->getBillingCountryIso(), $exceptedNonEuISOs, true);

        //An empty VAT Id can't be valid
        if ($customerInformation->getVatId() === '') {
            //Set the error code to 1 to avoid vatIds with only a "."
            $this->result->setVatIdInvalid('1');

            return $this->result;
        }

        //If there is a VAT Id for a Non-EU-countries, its invalid
        if (!EUStates::isEUCountry($customerInformation->getBillingCountryIso()) && !$isExcepted) {
            $this->result->setVatIdInvalid('5');
            $this->result->setCountryInvalid();

            return $this->result;
        }

        //All VAT IDs have a length of 4 to 14 chars (romania has a min. length of 4 characters)
        if (strlen($customerInformation->getVatId()) < 4) {
            $this->result->setVatIdInvalid('1');
        } elseif (strlen($customerInformation->getVatId()) > 14) {
            $this->result->setVatIdInvalid('2');
        }

        $isExcepted = in_array($customerInformation->getCountryCode(), $exceptedNonEuISOs, true);

        //The country code has to be an EU prefix and has to match the billing country
        if (!EUStates::isEUCountry($customerInformation->getCountryCode()) && !$isExcepted) {
            $this->result->setVatIdInvalid('3');
        } elseif ($customerInformation->getCountryCode() !== $customerInformation->getBillingCountryIso()) {
            //The country greece has two different ISO codes. GR and EL
            //Since shopware does only know "GR" as "Greece", we have to manually check for "EL" here.
            if ($customerInformation->getCountryCode() !== 'EL' || $customerInformation->getBillingCountryIso() !== 'GR') {
                $this->result->setVatIdInvalid('6');
                $this->result->setCountryInvalid();
            }
        }

        //The VAT number always only consists of alphanumerical chars
        if (!ctype_alnum($customerInformation->getVatNumber())) {
            $this->result->setVatIdInvalid('4');
        }

        //If the VAT number only consists alphas its invalid
        if (ctype_alpha($customerInformation->getVatNumber())) {
            $this->result->setVatIdInvalid('4');
        }

        return $this->result;
    }
}
