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
	 * 
	 * #doc-arg-name code
	 * #doc-arg-desc Status code
	 * #doc-arg-example code="a_code"
	 * 
	 * #doc-arg-name id
	 * #doc-arg-desc A single or a list of order status ids
	 * #doc-arg-example id="2", id="1,4,7"
	 * 
	 * #doc-arg-name order
	 * #doc-arg-desc A list of values see Expected values
	 * #doc-arg-example order="random"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createAnyTypeArgument('code'),
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

	 /**
	 * 
	 * #doc-out-name $CHAPO
	 * #doc-out-desc the order status short description
	 * 
	 * #doc-out-name $CODE
	 * #doc-out-desc the order status code
	 * 
	 * #doc-out-name $COLOR
	 * #doc-out-desc the order status hexadecimal color code
	 * 
	 * #doc-out-name $DESCRIPTION
	 * #doc-out-desc the order status description
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the order status id
	 * 
	 * #doc-out-name $IS_TRANSLATED
	 * #doc-out-desc whatever the order status is translated or not
	 * 
	 * #doc-out-name $LOCALE
	 * #doc-out-desc the order status locale
	 * 
	 * #doc-out-name $POSITION
	 * #doc-out-desc the order status position
	 * 
	 * #doc-out-name $POSTSCRIPTUM
	 * #doc-out-desc the order status postscriptum
	 * 
	 * #doc-out-name $PROTECTED_STATUS
	 * #doc-out-desc 1 if the order status is protected
	 * 
	 * #doc-out-name $TITLE
	 * #doc-out-desc the order status title
	 */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var OrderStatusModel $orderStatus */
        foreach ($loopResult->getResultDataCollection() as $orderStatus) {
            $loopResultRow = new LoopResultRow($orderStatus);
            $loopResultRow->set('ID', $orderStatus->getId())
                ->set('IS_TRANSLATED', $orderStatus->getVirtualColumn('IS_TRANSLATED'))
                ->set('LOCALE', $this->locale)
                ->set('CODE', $orderStatus->getCode())
                ->set('COLOR', $orderStatus->getColor())
                ->set('POSITION', $orderStatus->getPosition())
                ->set('PROTECTED_STATUS', $orderStatus->getProtectedStatus())
                ->set('TITLE', $orderStatus->getVirtualColumn('i18n_TITLE'))
                ->set('CHAPO', $orderStatus->getVirtualColumn('i18n_CHAPO'))
                ->set('DESCRIPTION', $orderStatus->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('POSTSCRIPTUM', $orderStatus->getVirtualColumn('i18n_POSTSCRIPTUM'))
            ;
            $this->addOutputFields($loopResultRow, $orderStatus);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
