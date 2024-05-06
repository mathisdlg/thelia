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
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            // #doc-arg-desc A single order product id.
            Argument::createIntTypeArgument('order_product', null, true),
            // #doc-arg-desc See Order possible values
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

    public function parseResults(LoopResult $loopResult)
    {
        /** @var OrderProductAttributeCombinationModel $orderAttributeCombination */
        foreach ($loopResult->getResultDataCollection() as $orderAttributeCombination) {
            $loopResultRow = new LoopResultRow($orderAttributeCombination);

            $loopResultRow
		        // #doc-out-desc the order product attribute combination ID
                ->set('ID', $orderAttributeCombination->getId())
		        // #doc-out-desc the related order product ID
                ->set('ORDER_PRODUCT_ID', $orderAttributeCombination->getOrderProductId())
		        // #doc-out-desc the order product attribute title
                ->set('ATTRIBUTE_TITLE', $orderAttributeCombination->getAttributeTitle())
		        // #doc-out-desc the order product attribute chapo
                ->set('ATTRIBUTE_CHAPO', $orderAttributeCombination->getAttributeChapo())
		        // #doc-out-desc the order product attribute description
                ->set('ATTRIBUTE_DESCRIPTION', $orderAttributeCombination->getAttributeDescription())
		        // #doc-out-desc the order product attribute postscriptum
                ->set('ATTRIBUTE_POSTSCRIPTUM', $orderAttributeCombination->getAttributePostscriptum())
		        // #doc-out-desc the order product attribute availability title
                ->set('ATTRIBUTE_AVAILABILITY_TITLE', $orderAttributeCombination->getAttributeAvTitle())
		        // #doc-out-desc the order product attribute availability chapo
                ->set('ATTRIBUTE_AVAILABILITY_CHAPO', $orderAttributeCombination->getAttributeAvChapo())
		        // #doc-out-desc the order product attribute availability description
                ->set('ATTRIBUTE_AVAILABILITY_DESCRIPTION', $orderAttributeCombination->getAttributeAvDescription())
		        // #doc-out-desc the order product attribute availability postscriptum
                ->set('ATTRIBUTE_AVAILABILITY_POSTSCRIPTUM', $orderAttributeCombination->getAttributeAvPostscriptum())
            ;
            $this->addOutputFields($loopResultRow, $orderAttributeCombination);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
