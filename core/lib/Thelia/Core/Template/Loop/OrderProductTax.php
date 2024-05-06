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
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            // #doc-arg-desc A single order product id.
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

    public function parseResults(LoopResult $loopResult)
    {
        /** @var OrderProductTaxModel $orderProductTax */
        foreach ($loopResult->getResultDataCollection() as $orderProductTax) {
            $loopResultRow = new LoopResultRow($orderProductTax);
		    // #doc-out-desc Tax id
            $loopResultRow->set('ID', $orderProductTax->getId())
		        // #doc-out-desc Tax title
                ->set('TITLE', $orderProductTax->getTitle())
		        // #doc-out-desc Tax description
                ->set('DESCRIPTION', $orderProductTax->getDescription())
		        // #doc-out-desc Tax amount
                ->set('AMOUNT', $orderProductTax->getAmount())
		        // #doc-out-desc Tax amount of the promo price
                ->set('PROMO_AMOUNT', $orderProductTax->getPromoAmount())
            ;
            $this->addOutputFields($loopResultRow, $orderProductTax);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
