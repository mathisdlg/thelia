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

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\OrderStatus as OrderStatusModel;
use Thelia\Model\OrderStatusQuery;

/**
 * OrderStatus loop.
 *
 * Class OrderStatus
 * 
 * #doc-desc Order status loop displays order status information.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 * @author Gilles Bourgeat <gbourgeat@gmail.com>
 *
 * @method int[]    getId()
 * @method string   getCode()
 * @method string[] getOrder()
 */
class OrderStatus extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
		    // #doc-arg-desc A single or a list of order status ids
            Argument::createIntListTypeArgument('id'),
		    // #doc-arg-desc Status code
            Argument::createAnyTypeArgument('code'),
            // #doc-arg-desc A list of values see Expected values
            Argument::createEnumListTypeArgument(
                'order',
                [
                    'alpha',
                    'alpha_reverse',
                    'manual',
                    'manual_reverse',
                ],
                'manual'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = OrderStatusQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search);

        if (null !== $id = $this->getId()) {
            $search->filterById($id, Criteria::IN);
        }

        if (null !== $code = $this->getCode()) {
            $search->filterByCode($code, Criteria::EQUAL);
        }

        $orders = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'alpha':
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case 'alpha_reverse':
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case 'manual':
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case 'manual_reverse':
                    $search->orderByPosition(Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var OrderStatusModel $orderStatus */
        foreach ($loopResult->getResultDataCollection() as $orderStatus) {
            $loopResultRow = new LoopResultRow($orderStatus);
		    // #doc-out-desc the order status id
            $loopResultRow->set('ID', $orderStatus->getId())
		        // #doc-out-desc whatever the order status is translated or not
                ->set('IS_TRANSLATED', $orderStatus->getVirtualColumn('IS_TRANSLATED'))
		        // #doc-out-desc the order status locale
                ->set('LOCALE', $this->locale)
		        // #doc-out-desc the order status code
                ->set('CODE', $orderStatus->getCode())
		        // #doc-out-desc the order status hexadecimal color code
                ->set('COLOR', $orderStatus->getColor())
		        // #doc-out-desc the order status position
                ->set('POSITION', $orderStatus->getPosition())
		        // #doc-out-desc 1 if the order status is protected
                ->set('PROTECTED_STATUS', $orderStatus->getProtectedStatus())
		        // #doc-out-desc the order status title
                ->set('TITLE', $orderStatus->getVirtualColumn('i18n_TITLE'))
		        // #doc-out-desc the order status short description
                ->set('CHAPO', $orderStatus->getVirtualColumn('i18n_CHAPO'))
		        // #doc-out-desc the order status description
                ->set('DESCRIPTION', $orderStatus->getVirtualColumn('i18n_DESCRIPTION'))
		        // #doc-out-desc the order status postscriptum
                ->set('POSTSCRIPTUM', $orderStatus->getVirtualColumn('i18n_POSTSCRIPTUM'))
            ;
            $this->addOutputFields($loopResultRow, $orderStatus);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
