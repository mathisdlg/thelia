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
 * #doc-usage {loop type="delivery" name="the-loop-name" [argument="value"], [...]}
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
	 /**
	 * 
	 * #doc-arg-name all produspecific base module loop arguments
	 * #doc-arg-desc 
	 * #doc-arg-example 
	 * 
	 * #doc-arg-name address
	 * #doc-arg-desc An address id.
	 * #doc-arg-example address=21
	 * 
	 * #doc-arg-name code
	 * #doc-arg-desc A module code.
	 * #doc-arg-example code='Atos'
	 * 
	 * #doc-arg-name country
	 * #doc-arg-desc A country id.
	 * #doc-arg-example country=2
	 * 
	 * #doc-arg-name exclude
	 * #doc-arg-desc A list of module IDs to exclude from the results
	 * #doc-arg-example exclude="12, 21"
	 * 
	 * #doc-arg-name exclude_code
	 * #doc-arg-desc A list of module codes to exclude from the results
	 * #doc-arg-example exclude_code="Cheque,Atos"
	 * 
	 * #doc-arg-name id
	 * #doc-arg-desc A module id.
	 * #doc-arg-example module=4
	 * 
	 * #doc-arg-name order
	 * #doc-arg-desc A list of values see sorting possible values
	 * #doc-arg-default manual
	 * #doc-arg-example order="id_reverse"
	 * 
	 * #doc-arg-name state
	 * #doc-arg-desc A state id.
	 * #doc-arg-example state=12
	 */
    public function getArgDefinitions()
    {
        $collection = parent::getArgDefinitions();

        $collection
            ->addArgument(Argument::createIntTypeArgument('address'))
            ->addArgument(Argument::createIntTypeArgument('country'))
            ->addArgument(Argument::createIntTypeArgument('state'))
        ;

        return $collection;
    }

	 /**
	 * 
	 * #doc-out-name $CHAPO
	 * #doc-out-desc the delivery module short description
	 * 
	 * #doc-out-name $CODE
	 * #doc-out-desc the module code
	 * 
	 * #doc-out-name $DELIVERY_DATE
	 * #doc-out-desc the expected delivery date. This output could be empty.
	 * 
	 * #doc-out-name $DESCRIPTION
	 * #doc-out-desc the delivery module description
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the delivery module id
	 * 
	 * #doc-out-name $POSTAGE
	 * #doc-out-desc the delivery price with taxes, expressed in the current currency
	 * 
	 * #doc-out-name $POSTAGE_TAX
	 * #doc-out-desc The delivery price tax amount, expressed in the current currency
	 * 
	 * #doc-out-name $POSTAGE_TAX_RULE_TITLE
	 * #doc-out-desc The tax rule title used to get delivery price tax
	 * 
	 * #doc-out-name $POSTAGE_UNTAXED
	 * #doc-out-desc the delivery price without taxes, expressed in the current currency
	 * 
	 * #doc-out-name $POSTSCRIPTUM
	 * #doc-out-desc the delivery module postscriptum
	 * 
	 * #doc-out-name $TITLE
	 * #doc-out-desc the delivery module title
	 */
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
                        ->set('ID', $deliveryModule->getId())
                        ->set('CODE', $deliveryModule->getCode())
                        ->set('TITLE', $deliveryModule->getVirtualColumn('i18n_TITLE'))
                        ->set('CHAPO', $deliveryModule->getVirtualColumn('i18n_CHAPO'))
                        ->set('DESCRIPTION', $deliveryModule->getVirtualColumn('i18n_DESCRIPTION'))
                        ->set('POSTSCRIPTUM', $deliveryModule->getVirtualColumn('i18n_POSTSCRIPTUM'))
                        ->set('POSTAGE', $postage->getAmount())
                        ->set('POSTAGE_TAX', $postage->getAmountTax())
                        ->set('POSTAGE_UNTAXED', $postage->getAmount() - $postage->getAmountTax())
                        ->set('POSTAGE_TAX_RULE_TITLE', $postage->getTaxRuleTitle())
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
