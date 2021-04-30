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

namespace SwagVatIdValidation\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs as EventArgs;
use Shopware\Bundle\AccountBundle\Form\Account\AddressFormType;
use Shopware_Controllers_Frontend_Register;
use SwagVatIdValidation\Bundle\AccountBundle\Constraints\AdvancedVatId;
use SwagVatIdValidation\Components\IsoServiceInterface;
use SwagVatIdValidation\Components\VatIdConfigReaderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;

class Forms implements SubscriberInterface
{
    /**
     * @var IsoServiceInterface
     */
    private $isoService;

    /**
     * @var VatIdConfigReaderInterface
     */
    private $configReader;

    public function __construct(
        IsoServiceInterface $isoService,
        VatIdConfigReaderInterface $configReader
    ) {
        $this->isoService = $isoService;
        $this->configReader = $configReader;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'Shopware_Form_Builder' => 'onFormBuild',
            'Enlight_Controller_Action_PreDispatch_Frontend_Register' => 'onRegister',
        ];
    }

    public function onFormBuild(EventArgs $args): void
    {
        $ref = $args->get('reference');
        if ($ref !== AddressFormType::class && $ref !== 'address') {
            return;
        }

        /** @var FormInterface $builder */
        $builder = $args->get('builder');

        $builder->add(
            'vatId',
            TextType::class,
            [
                'constraints' => [new AdvancedVatId()],
            ]
        );
    }

    public function onRegister(EventArgs $args): void
    {
        /** @var Shopware_Controllers_Frontend_Register $controller */
        $controller = $args->getSubject();

        $request = $controller->Request();

        if ($request->getActionName() !== 'index') {
            return;
        }

        $config = $this->configReader->getPluginConfig();

        $controller->View()->assign('vatIdIsRequired', \json_encode($config['vatId_is_required']));
        $controller->View()->assign(
            'countryIsoIdList',
            \json_encode(
                $this->isoService->getCountryIdsFromIsoList()
            )
        );
    }
}
