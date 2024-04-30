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

use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Module\BaseModule;

/**
 * Class Payment.
 * 
 * #doc-usage {loop type="payment" name="the-loop-name" [argument="value"], [...]}
 * #doc-desc payment loop displays payment modules information.
 *
 * @author Etienne Roudeix <eroudeix@gmail.com>
 */
class Payment extends BaseSpecificModule implements PropelSearchLoopInterface
{
	 /**
	 * 
	 * #doc-arg-name all base specific module arguments
	 * #doc-arg-desc 
	 * #doc-arg-example 
	 */
    public function getArgDefinitions()
    {
        $collection = parent::getArgDefinitions();

        return $collection;
    }

	 /**
	 * 
	 * #doc-out-name $CHAPO
	 * #doc-out-desc the payment module short description
	 * 
	 * #doc-out-name $CODE
	 * #doc-out-desc the module code
	 * 
	 * #doc-out-name $DESCRIPTION
	 * #doc-out-desc the payment module description
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the payment module id
	 * 
	 * #doc-out-name $POSTSCRIPTUM
	 * #doc-out-desc the payment module postscriptum
	 * 
	 * #doc-out-name $TITLE
	 * #doc-out-desc the payment module title
	 */
    public function parseResults(LoopResult $loopResult)
    {
        $cart = $this->getCurrentRequest()->getSession()->getSessionCart($this->dispatcher);

        /** @var \Thelia\Model\Module $paymentModule */
        foreach ($loopResult->getResultDataCollection() as $paymentModule) {
            $loopResultRow = new LoopResultRow($paymentModule);

            $moduleInstance = $paymentModule->getPaymentModuleInstance($this->container);

            $isValidPaymentEvent = new IsValidPaymentEvent($moduleInstance, $cart);
            $this->dispatcher->dispatch(
                $isValidPaymentEvent,
                TheliaEvents::MODULE_PAYMENT_IS_VALID
            );

            if (false === $isValidPaymentEvent->isValidModule()) {
                continue;
            }

            $loopResultRow
                ->set('ID', $paymentModule->getId())
                ->set('CODE', $paymentModule->getCode())
                ->set('TITLE', $paymentModule->getVirtualColumn('i18n_TITLE'))
                ->set('CHAPO', $paymentModule->getVirtualColumn('i18n_CHAPO'))
                ->set('DESCRIPTION', $paymentModule->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('POSTSCRIPTUM', $paymentModule->getVirtualColumn('i18n_POSTSCRIPTUM'))
            ;
            $this->addOutputFields($loopResultRow, $paymentModule);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    protected function getModuleType()
    {
        return BaseModule::PAYMENT_MODULE_TYPE;
    }
}
