<?php
/**
 * Shopware 4
 * Copyright © shopware AG
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

namespace Shopware\Plugins\SwagVatIdValidation\Components;

class VatIdInformation
{
    protected $vatId;
    protected $countryCode;
    protected $vatNumber;

    public function __construct($vatId)
    {
        $this->vatId = str_replace(array(' ', '.', '-', ',', ', '), '', trim($vatId));
        $this->countryCode = substr($this->vatId, 0, 2);
        $this->vatNumber = substr($this->vatId, 2);
    }

    public function getVatId()
    {
        return $this->vatId;
    }

    public function getCountryCode()
    {
        return $this->countryCode;
    }

    public function getVatNumber()
    {
        return $this->vatNumber;
    }
}