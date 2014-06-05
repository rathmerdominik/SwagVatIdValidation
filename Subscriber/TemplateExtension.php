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

namespace Shopware\Plugins\SwagVatIdValidation\Subscriber;

use Shopware\Plugins\SwagVatIdValidation\Components\VatIdValidationStatus;

use Enlight\Event\SubscriberInterface;

/**
 * This example is going to show how to test your methods without global shopware state
 *
 * Class Account
 * @package Shopware\Plugins\SwagScdExample\Subscriber
 */
abstract class TemplateExtension implements SubscriberInterface
{
    /** @var  \Enlight_Config */
    private $config;

    /** @var  string */
    private $path;

    /** @var \Shopware\CustomModels\SwagVatIdValidation\Repository */
    private $vatIdCheckRepository;

    public function __construct($config, $path)
    {
        $this->config = $config;
        $this->path = $path;
    }

    /**
     * Helper function to get the VatIdCheckRepository
     * @return \Shopware\CustomModels\SwagVatIdValidation\Repository
     */
    protected function getVatIdCheckRepository()
    {
        if (!$this->vatIdCheckRepository) {
            $this->vatIdCheckRepository = Shopware()->Models()->getRepository(
                '\Shopware\CustomModels\SwagVatIdValidation\VatIdCheck'
            );
        }

        return $this->vatIdCheckRepository;
    }

    /**
     * @param \Enlight_Controller_Action $controller
     * @param array $actions
     */
    public function postDispatchFrontendController(\Enlight_Controller_Action $controller, $actions)
    {
        /** @var $request \Zend_Controller_Request_Http */
        $request = $controller->Request();

        /** @var $response \Zend_Controller_Response_Http */
        $response = $controller->Response();

        /**
         * @var $view \Enlight_View_Default
         */
        $view = $controller->View();

        //Check if there is a template and if an exception has occurred
        if (!in_array($request->getActionName(), $actions)) {
            return;
        }

        $vatIdCheck = $this->getVatIdCheckRepository()->getVatIdCheckByCustomerId(Shopware()->Session()->sUserId);

        if (!$vatIdCheck) {
            return;
        }

        //Add our plugin template directory to load our slogan extension.
        $view->addTemplateDir($this->path . 'Views/');
        $view->extendsTemplate('frontend/plugins/swag_vat_id_validation/index.tpl');

        $status = new VatIdValidationStatus($vatIdCheck->getStatus());
        $errors = $this->getErrors($status);
        $view->assign('vatIdCheck', array(
                'vatId' => $vatIdCheck->getVatId(),
                'errors' => $errors,
                'success' => $status->isVatIdValid()
            )
        );

        if ($status->isValid()) {
            Shopware()->Models()->remove($vatIdCheck);
            Shopware()->Models()->flush($vatIdCheck);
        }
    }

    /**
     * Helper function sets the ErrorMessages and ErrorFlags by the VatIdValidationStatus
     * @param VatIdValidationStatus $status
     * @return array
     */
    private function getErrors(VatIdValidationStatus $status)
    {
        $errors = array(
            'messages' => array(),
            'flags' => array()
        );

        if ($status->isValid()) {
            return $errors;
        }

        $snippets = Shopware()->Snippets()->getNamespace('frontend/swag_vat_id_validation/main');

        if ($status->serviceNotAvailable()) {
            $errors['messages'][] = $snippets->get('messages/checkNotAvailable');

            if ($this->config->get('customerEmailNotification')) {
                $errors['messages'][] = $snippets->get('messages/emailNotification');
            }

            return $errors;
        }

        if (!$status->isVatIdValid()) {
            $errors['messages'][] = $snippets->get('validator/error/vatId');
            $errors['flags']['ustid'] = true;
            return $errors;
        }

        if (!$status->isCompanyValid()) {
            $errors['messages'][] = $snippets->get('validator/extended/error/company');
            $errors['flags']['company'] = true;
        }

        if (!$status->isStreetValid()) {
            $errors['messages'][] = $snippets->get('validator/extended/error/street');
            $errors['flags']['street'] = true;
            $errors['flags']['streetnumber'] = true;
        }

        if (!$status->isZipCodeValid()) {
            $errors['messages'][] = $snippets->get('validator/extended/error/zipCode');
            $errors['flags']['zipcode'] = true;
        }

        if (!$status->isCityValid()) {
            $errors['messages'][] = $snippets->get('validator/extended/error/city');
            $errors['flags']['city'] = true;
        }

        return $errors;
    }
}