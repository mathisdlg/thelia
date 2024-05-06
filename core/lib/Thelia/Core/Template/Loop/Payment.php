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
 * #doc-desc payment loop displays payment modules information.
 *
 * @author Etienne Roudeix <eroudeix@gmail.com>
 */
class Payment extends BaseSpecificModule implements PropelSearchLoopInterface
{
    public function getArgDefinitions()
    {
        $collection = parent::getArgDefinitions();

        return $collection;
    }

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
                // #doc-out-desc the payment module id
                ->set('ID', $paymentModule->getId())
                // #doc-out-desc the module code
                ->set('CODE', $paymentModule->getCode())
                // #doc-out-desc the payment module title
                ->set('TITLE', $paymentModule->getVirtualColumn('i18n_TITLE'))
                // #doc-out-desc the payment module short description
                ->set('CHAPO', $paymentModule->getVirtualColumn('i18n_CHAPO'))
                // #doc-out-desc the payment module description
                ->set('DESCRIPTION', $paymentModule->getVirtualColumn('i18n_DESCRIPTION'))
                // #doc-out-desc the payment module postscriptum
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
