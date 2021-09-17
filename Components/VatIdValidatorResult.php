<?php
/**
 * Shopware Plugins
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this plugin can be used under
 * a proprietary license as set forth in our Terms and Conditions,
 * section 2.1.2.2 (Conditions of Usage).
 *
 * The text of our proprietary license additionally can be found at and
 * in the LICENSE file you have received along with this plugin.
 *
 * This plugin is distributed in the hope that it will be useful,
 * with LIMITED WARRANTY AND LIABILITY as set forth in our
 * Terms and Conditions, sections 9 (Warranty) and 10 (Liability).
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the plugin does not imply a trademark license.
 * Therefore any rights, title and interest in our trademarks
 * remain entirely with us.
 */

namespace SwagVatIdValidation\Components;

use Shopware_Components_Config;
use Shopware_Components_Snippet_Manager;

class VatIdValidatorResult implements \Serializable
{
    //Flags
    public const VAT_ID_OK = 1;
    public const COMPANY_OK = 2;
    public const STREET_OK = 4;
    public const ZIP_CODE_OK = 8;
    public const CITY_OK = 16;

    //States

    /**
     * Status -2 happens when
     * - the VAT ID is required, but empty (not set by a validator, but the login subscriber)
     */
    public const REQUIRED = -2;

    /**
     * Status -1 happens when
     * - validation service was unavailable
     */
    public const UNAVAILABLE = -1;

    /**
     * Status 0 happens when
     * - the VAT Id is invalid
     */
    public const INVALID = 0;

    /**
     * Status 31 happens when
     * - the check was executed and each was valid
     */
    public const VALID = 31;

    /**
     * @var int
     */
    private $status;

    /**
     * @var array
     */
    private $errors;

    /**
     * @var array
     */
    private $flags;

    /**
     * @var Shopware_Components_Snippet_Manager
     */
    private $snippetManager;

    /**
     * @var string
     */
    private $namespace;

    /**
     * @var \Enlight_Components_Snippet_Namespace
     */
    private $pluginSnippets;

    /**
     * @var \Enlight_Components_Snippet_Namespace
     */
    private $validatorSnippets;

    /**
     * @var Shopware_Components_Config
     */
    private $config;

    public function __construct(Shopware_Components_Snippet_Manager $snippetManager, string $namespace, Shopware_Components_Config $config)
    {
        $this->snippetManager = $snippetManager;
        $this->config = $config;
        $this->init($namespace);
    }

    /**
     * Sets the VAT ID to 'invalid' and sets the validator error message by $errorCode
     *
     * @param string $errorCode
     */
    public function setVatIdInvalid($errorCode)
    {
        $this->status = self::INVALID;
        $this->errors[$errorCode] = $this->validatorSnippets->get('error' . $errorCode);
        $this->flags['ustid'] = true;
    }

    /**
     * Sets the VAT ID required
     */
    public function setVatIdRequired()
    {
        $this->status = self::REQUIRED;
        $this->errors['required'] = $this->pluginSnippets->get('messages/vatIdRequired');
    }

    /**
     * Sets the result to the api service was not available
     */
    public function setServiceUnavailable()
    {
        $this->status = self::UNAVAILABLE;

        if (!$this->config->get(VatIdConfigReaderInterface::ALLOW_REGISTER_ON_API_ERROR)) {
            $this->status = self::UNAVAILABLE;
            $this->errors['serviceUnavailable'] = $this->pluginSnippets->get('messages/serviceUnavailable');
        }
    }

    /**
     * Sets the company to invalid
     */
    public function setCompanyInvalid()
    {
        $this->status &= ~self::COMPANY_OK;
        $this->errors['company'] = $this->pluginSnippets->get('validator/extended/error/company');
        $this->flags['company'] = true;
    }

    /**
     * Sets the street / streetnumber combination to invalid
     */
    public function setStreetInvalid()
    {
        $this->status &= ~self::STREET_OK;
        $this->errors['street'] = $this->pluginSnippets->get('validator/extended/error/street');
        $this->flags['street'] = true;
    }

    /**
     * Sets the zipcode to invalid
     */
    public function setZipCodeInvalid()
    {
        $this->status &= ~self::ZIP_CODE_OK;
        $this->errors['zipCode'] = $this->pluginSnippets->get('validator/extended/error/zipCode');
        $this->flags['zipcode'] = true;
    }

    /**
     * Sets the city to invalid
     */
    public function setCityInvalid()
    {
        $this->status &= ~self::CITY_OK;
        $this->errors['city'] = $this->pluginSnippets->get('validator/extended/error/city');
        $this->flags['city'] = true;
    }

    /**
     * Sets the country to invalid
     */
    public function setCountryInvalid()
    {
        $this->flags['country'] = true;
    }

    /**
     * Returns an error snippet
     *
     * @param string $key
     *
     * @return string|null
     */
    public function getErrorMessage($key)
    {
        return $this->pluginSnippets->get($key);
    }

    /**
     * Returns the error messages
     *
     * @return array
     */
    public function getErrorMessages()
    {
        return $this->errors;
    }

    /**
     * Returns the error flags
     *
     * @return array
     */
    public function getErrorFlags()
    {
        return $this->flags;
    }

    /**
     * Returns true if the VAT Id and its address data are valid
     *
     * @return bool
     */
    public function isValid()
    {
        return $this->status === self::VALID;
    }

    /**
     * Returns true if the validation api was not available
     *
     * @return bool
     */
    public function isApiUnavailable()
    {
        return $this->status === self::UNAVAILABLE;
    }

    /**
     * String representation of object
     *
     * @see http://php.net/manual/en/serializable.serialize.php
     *
     * @return string
     */
    public function serialize()
    {
        $serializeArray = [
            'namespace' => $this->namespace,
            'keys' => \array_keys($this->errors),
        ];

        return \serialize($serializeArray);
    }

    /**
     * Constructs the object
     *
     * @see http://php.net/manual/en/serializable.unserialize.php
     *
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        $serializeArray = \unserialize($serialized);

        $this->init($serializeArray['namespace']);

        foreach ($serializeArray['keys'] as $errorCode) {
            $this->addError($errorCode);
        }
    }

    /**
     * Helper function to init the result. Used in constructor and unserialize()
     *
     * @param string $namespace
     */
    private function init($namespace)
    {
        $this->status = self::VALID;
        $this->errors = [];
        $this->flags = [];

        $this->pluginSnippets = $this->snippetManager->getNamespace('frontend/swag_vat_id_validation/main');
        $this->namespace = $namespace;

        if (empty($namespace)) {
            return;
        }

        $this->validatorSnippets = $this->snippetManager->getNamespace('frontend/swag_vat_id_validation/' . $namespace);
    }

    /**
     * Helper function to add an error by its error code
     *
     * @param string $errorCode
     */
    private function addError($errorCode)
    {
        if ($errorCode === 'required') {
            $this->setVatIdRequired();

            return;
        }

        if ($errorCode === 'unavailable') {
            $this->setServiceUnavailable();

            return;
        }

        if ($errorCode === 'company') {
            $this->setCompanyInvalid();

            return;
        }

        if ($errorCode === 'street') {
            $this->setStreetInvalid();

            return;
        }

        if ($errorCode === 'zipCode') {
            $this->setZipCodeInvalid();

            return;
        }

        if ($errorCode === 'city') {
            $this->setCityInvalid();

            return;
        }

        $this->setVatIdInvalid($errorCode);
    }
}
