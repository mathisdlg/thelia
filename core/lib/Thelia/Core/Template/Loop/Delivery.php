<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Template\Loop;

use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Model\AddressQuery;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Cart as CartModel;
use Thelia\Model\CountryQuery;
use Thelia\Model\Module;
use Thelia\Model\StateQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\DeliveryModuleInterface;
use Thelia\Module\DeliveryModuleWithStateInterface;
use Thelia\Module\Exception\DeliveryException;

/**
 * Class Delivery.
 * 
 * #doc-desc delivery loop displays delivery modules information.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 * @author Etienne Roudeix <eroudeix@gmail.com>
 *
 * @method int getAddress()
 * @method int getCountry()
 * @method int getState()
 */
class Delivery extends BaseSpecificModule
{
    public function getArgDefinitions()
    {
        $collection = parent::getArgDefinitions();

        $collection
            // #doc-arg-desc An address id.
            ->addArgument(Argument::createIntTypeArgument('address'))
            // #doc-arg-desc A country id.
            ->addArgument(Argument::createIntTypeArgument('country'))
            // #doc-arg-desc A state id.
            ->addArgument(Argument::createIntTypeArgument('state'))
        ;

        return $collection;
    }

    public function parseResults(LoopResult $loopResult)
    {
        $cart = $this->getCurrentRequest()->getSession()->getSessionCart($this->dispatcher);
        $address = $this->getDeliveryAddress();

        $country = $this->getCurrentCountry();
        if (null === $country) {
            if ($address !== null) {
                $country = $address->getCountry();
            } else {
                $country = CountryQuery::create()->findOneByByDefault(true);
            }
        }

        $state = $this->getCurrentState();
        if (null === $state) {
            if ($address !== null) {
                $state = $address->getState();
            }
        }

        $virtual = $cart->isVirtual();

        /** @var Module $deliveryModule */
        foreach ($loopResult->getResultDataCollection() as $deliveryModule) {
            $areaDeliveryModule = AreaDeliveryModuleQuery::create()
                ->findByCountryAndModule($country, $deliveryModule, $state)
            ;

            if (null === $areaDeliveryModule && false === $virtual) {
                continue;
            }

            /** @var DeliveryModuleInterface|DeliveryModuleWithStateInterface $moduleInstance */
            $moduleInstance = $deliveryModule->getDeliveryModuleInstance($this->container);

            if (true === $virtual
                && false === $moduleInstance->handleVirtualProductDelivery()
                && false === $this->getBackendContext()
            ) {
                continue;
            }

            $loopResultRow = new LoopResultRow($deliveryModule);

            try {
                // Check if module is valid, by calling isValidDelivery(),
                // or catching a DeliveryException.
                /* @var CartModel $cart */
                $cart->getAddressDeliveryId();
                $deliveryPostageEvent = new DeliveryPostageEvent($moduleInstance, $cart, $address, $country, $state);
                $this->dispatcher->dispatch(
                    $deliveryPostageEvent,
                    TheliaEvents::MODULE_DELIVERY_GET_POSTAGE
                );

                if ($deliveryPostageEvent->isValidModule()) {
                    $postage = $deliveryPostageEvent->getPostage();

                    $loopResultRow
                        // #doc-out-desc the delivery module id
                        ->set('ID', $deliveryModule->getId())
                        // #doc-out-desc the module code
                        ->set('CODE', $deliveryModule->getCode())
                        // #doc-out-desc the delivery module title
                        ->set('TITLE', $deliveryModule->getVirtualColumn('i18n_TITLE'))
                        // #doc-out-desc the delivery module short description
                        ->set('CHAPO', $deliveryModule->getVirtualColumn('i18n_CHAPO'))
                        // #doc-out-desc the delivery module description
                        ->set('DESCRIPTION', $deliveryModule->getVirtualColumn('i18n_DESCRIPTION'))
                        // #doc-out-desc the delivery module postscriptum
                        ->set('POSTSCRIPTUM', $deliveryModule->getVirtualColumn('i18n_POSTSCRIPTUM'))
                        // #doc-out-desc the delivery price with taxes, expressed in the current currency
                        ->set('POSTAGE', $postage->getAmount())
                        // #doc-out-desc The delivery price tax amount, expressed in the current currency
                        ->set('POSTAGE_TAX', $postage->getAmountTax())
                        // #doc-out-desc the delivery price without taxes, expressed in the current currency
                        ->set('POSTAGE_UNTAXED', $postage->getAmount() - $postage->getAmountTax())
                        // #doc-out-desc The tax rule title used to get delivery price tax
                        ->set('POSTAGE_TAX_RULE_TITLE', $postage->getTaxRuleTitle())
                        // #doc-out-desc the expected delivery date. This output could be empty.
                        ->set('DELIVERY_DATE', $deliveryPostageEvent->getDeliveryDate())
                    ;

                    // add additional data if it exists
                    if ($deliveryPostageEvent->hasAdditionalData()) {
                        foreach ($deliveryPostageEvent->getAdditionalData() as $key => $value) {
                            $loopResultRow->set($key, $value);
                        }
                    }

                    $this->addOutputFields($loopResultRow, $deliveryModule);

                    $loopResult->addRow($loopResultRow);
                }
            } catch (DeliveryException $ex) {
                // Module is not available
            }
        }

        return $loopResult;
    }

    protected function getModuleType()
    {
        return BaseModule::DELIVERY_MODULE_TYPE;
    }

    /**
     * @return array|mixed|\Thelia\Model\Country
     */
    protected function getCurrentCountry()
    {
        $countryId = $this->getCountry();
        if (null !== $countryId) {
            $country = CountryQuery::create()->findPk($countryId);
            if (null === $country) {
                throw new \InvalidArgumentException('Cannot found country id: `'.$countryId.'` in delivery loop');
            }

            return $country;
        }

        return null;
    }

    /**
     * @return array|mixed|\Thelia\Model\State
     */
    protected function getCurrentState()
    {
        $stateId = $this->getState();
        if (null !== $stateId) {
            $state = StateQuery::create()->findPk($stateId);
            if (null === $state) {
                throw new \InvalidArgumentException('Cannot found state id: `'.$stateId.'` in delivery loop');
            }

            return $state;
        }

        return null;
    }

    /**
     * @return array|mixed|\Thelia\Model\Address
     */
    protected function getDeliveryAddress()
    {
        $address = null;

        $addressId = $this->getAddress();
        if (empty($addressId)) {
            $addressId = $this->getCurrentRequest()->getSession()->getOrder()->getChoosenDeliveryAddress();
        }

        if (!empty($addressId)) {
            $address = AddressQuery::create()->findPk($addressId);
        }

        return $address;
    }
}
