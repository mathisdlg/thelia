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
use Thelia\Model\OrderProductAttributeCombination as OrderProductAttributeCombinationModel;
use Thelia\Model\OrderProductAttributeCombinationQuery;
use Thelia\Type;
use Thelia\Type\TypeCollection;

/**
 * OrderProductOrderProductAttributeCombination loop.
 *
 * Class OrderProductAttributeCombination
 * 
 * #doc-desc Order product attribute combination loop lists order product attribute combinations.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int      getOrderProduct()
 * @method string[] getOrder()
 * @method bool     getVirtual()
 */
class OrderProductAttributeCombination extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name order
	 * #doc-arg-desc See Order possible values
	 * #doc-arg-example order="alpha_reverse"
	 * 
	 * #doc-arg-name order_product *
	 * #doc-arg-desc A single order product id.
	 * #doc-arg-example order_product="2"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('order_product', null, true),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(['alpha', 'alpha_reverse'])
                ),
                'alpha'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = OrderProductAttributeCombinationQuery::create();

        $orderProduct = $this->getOrderProduct();

        $search->filterByOrderProductId($orderProduct, Criteria::EQUAL);

        $orders = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'alpha':
                    $search->orderByAttributeTitle(Criteria::ASC);
                    break;
                case 'alpha_reverse':
                    $search->orderByAttributeTitle(Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

	 /**
	 * 
	 * #doc-out-name $ATTRIBUTE_AVAILABILITY_CHAPO
	 * #doc-out-desc the order product attribute availability chapo
	 * 
	 * #doc-out-name $ATTRIBUTE_AVAILABILITY_DESCRIPTION
	 * #doc-out-desc the order product attribute availability description
	 * 
	 * #doc-out-name $ATTRIBUTE_AVAILABILITY_POSTSCRIPTUM
	 * #doc-out-desc the order product attribute availability postscriptum
	 * 
	 * #doc-out-name $ATTRIBUTE_AVAILABILITY_TITLE
	 * #doc-out-desc the order product attribute availability title
	 * 
	 * #doc-out-name $ATTRIBUTE_CHAPO
	 * #doc-out-desc the order product attribute chapo
	 * 
	 * #doc-out-name $ATTRIBUTE_DESCRIPTION
	 * #doc-out-desc the order product attribute description
	 * 
	 * #doc-out-name $ATTRIBUTE_POSTSCRIPTUM
	 * #doc-out-desc the order product attribute postscriptum
	 * 
	 * #doc-out-name $ATTRIBUTE_TITLE
	 * #doc-out-desc the order product attribute title
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the order product attribute combination ID
	 * 
	 * #doc-out-name $ORDER_PRODUCT_ID
	 * #doc-out-desc the related order product ID
	 */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var OrderProductAttributeCombinationModel $orderAttributeCombination */
        foreach ($loopResult->getResultDataCollection() as $orderAttributeCombination) {
            $loopResultRow = new LoopResultRow($orderAttributeCombination);

            $loopResultRow
                ->set('ID', $orderAttributeCombination->getId())
                ->set('ORDER_PRODUCT_ID', $orderAttributeCombination->getOrderProductId())
                ->set('ATTRIBUTE_TITLE', $orderAttributeCombination->getAttributeTitle())
                ->set('ATTRIBUTE_CHAPO', $orderAttributeCombination->getAttributeChapo())
                ->set('ATTRIBUTE_DESCRIPTION', $orderAttributeCombination->getAttributeDescription())
                ->set('ATTRIBUTE_POSTSCRIPTUM', $orderAttributeCombination->getAttributePostscriptum())
                ->set('ATTRIBUTE_AVAILABILITY_TITLE', $orderAttributeCombination->getAttributeAvTitle())
                ->set('ATTRIBUTE_AVAILABILITY_CHAPO', $orderAttributeCombination->getAttributeAvChapo())
                ->set('ATTRIBUTE_AVAILABILITY_DESCRIPTION', $orderAttributeCombination->getAttributeAvDescription())
                ->set('ATTRIBUTE_AVAILABILITY_POSTSCRIPTUM', $orderAttributeCombination->getAttributeAvPostscriptum())
            ;
            $this->addOutputFields($loopResultRow, $orderAttributeCombination);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
