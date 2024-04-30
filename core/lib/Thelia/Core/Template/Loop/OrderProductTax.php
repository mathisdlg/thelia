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
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\OrderProductTax as OrderProductTaxModel;
use Thelia\Model\OrderProductTaxQuery;

/**
 * OrderProductTax loop.
 *
 * Class OrderProductTax
 * 
 * #doc-usage {loop type="order_product_tax" name="the-loop-name" [argument="value"], [...]}
 * #doc-desc Order product tax loop displays taxes available.
 *
 * @author Zzuutt
 *
 * @method int getOrderProduct()
 */
class OrderProductTax extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name order_product *
	 * #doc-arg-desc A single order product id.
	 * #doc-arg-default null
	 * #doc-arg-example order_product="2"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('order_product', null, true)
        );
    }

    public function buildModelCriteria()
    {
        $search = OrderProductTaxQuery::create();

        $orderProduct = $this->getOrderProduct();

        $search->filterByOrderProductId($orderProduct, Criteria::EQUAL);

        return $search;
    }

	 /**
	 * 
	 * #doc-out-name $AMOUNT
	 * #doc-out-desc Tax amount
	 * 
	 * #doc-out-name $DESCRIPTION
	 * #doc-out-desc Tax description
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc Tax id
	 * 
	 * #doc-out-name $PROMO_AMOUNT
	 * #doc-out-desc Tax amount of the promo price
	 * 
	 * #doc-out-name $TITLE
	 * #doc-out-desc Tax title
	 */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var OrderProductTaxModel $orderProductTax */
        foreach ($loopResult->getResultDataCollection() as $orderProductTax) {
            $loopResultRow = new LoopResultRow($orderProductTax);
            $loopResultRow->set('ID', $orderProductTax->getId())
                ->set('TITLE', $orderProductTax->getTitle())
                ->set('DESCRIPTION', $orderProductTax->getDescription())
                ->set('AMOUNT', $orderProductTax->getAmount())
                ->set('PROMO_AMOUNT', $orderProductTax->getPromoAmount())
            ;
            $this->addOutputFields($loopResultRow, $orderProductTax);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
