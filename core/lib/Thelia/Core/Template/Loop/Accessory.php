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
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\AccessoryQuery;

/**
 * Accessory loop.
 *
 * Class Accessory
 * 
 * #doc-desc The accessory loop lists products accessories. As an accessory is itself a product, this loop behaves like a product loop. Therefore you can use all product loop arguments and outputs.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]    getProduct()
 * @method string[] getOrder()
 *
 * @see http://doc.thelia.net/en/documentation/loop/accessory.html
 */
class Accessory extends Product
{
    protected $accessoryId;
    protected $accessoryPosition;

    /**
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name all product loop arguments
	 * #doc-arg-desc
	 * #doc-arg-example order="min_price", max_price="100"
	 * 
	 * #doc-arg-name order
	 * #doc-arg-desc A list of values <br/> Expected values
	 * #doc-arg-example order="accessory,max_price"
	 * 
	 * #doc-arg-name product \*
	 * #doc-arg-desc A single product id.
	 * #doc-arg-example product="2"
     */
    protected function getArgDefinitions()
    {
        $argumentCollection = parent::getArgDefinitions();

        $argumentCollection->addArgument(
            Argument::createIntListTypeArgument('product', null, true)
        );

        $argumentCollection->get('order')->default = 'accessory';

        $argumentCollection->get('order')->type->getKey(0)->addValue('accessory');
        $argumentCollection->get('order')->type->getKey(0)->addValue('accessory_reverse');

        return $argumentCollection;
    }

    public function buildModelCriteria()
    {
        $search = AccessoryQuery::create();

        $product = $this->getProduct();

        $search->filterByProductId($product, Criteria::IN);

        $order = $this->getOrder();
        $orderByAccessory = array_search('accessory', $order);
        $orderByAccessoryReverse = array_search('accessory_reverse', $order);
        if ($orderByAccessory !== false) {
            $search->orderByPosition(Criteria::ASC);
            $order[$orderByAccessory] = 'given_id';
            $this->args->get('order')->setValue(implode(',', $order));
        }
        if ($orderByAccessoryReverse !== false) {
            $search->orderByPosition(Criteria::DESC);
            $order[$orderByAccessoryReverse] = 'given_id';
            $this->args->get('order')->setValue(implode(',', $order));
        }

        $accessories = $this->search($search);

        $accessoryIdList = [0];
        $this->accessoryPosition = $this->accessoryId = [];

        foreach ($accessories as $accessory) {
            $accessoryProductId = $accessory->getAccessory();

            $accessoryIdList[] = $accessoryProductId;

            $this->accessoryPosition[$accessoryProductId] = $accessory->getPosition();
            $this->accessoryId[$accessoryProductId] = $accessory->getId();
        }

        $receivedIdList = $this->getId();

        /* if an Id list is receive, loop will only match accessories from this list */
        if ($receivedIdList === null) {
            $this->args->get('id')->setValue(implode(',', $accessoryIdList));
        } else {
            $this->args->get('id')->setValue(implode(',', array_intersect($receivedIdList, $accessoryIdList)));
        }

        return parent::buildModelCriteria();
    }

	 /**
	 * 
	 * #doc-out-name $ACCESSORY_ID
	 * #doc-out-desc The product ID of the accessory
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc The accessory ID
	 * 
	 * #doc-out-name $POSITION
	 * #doc-out-desc The position of the accessory in the list
	 * 
	 * #doc-out-name all product loop outputs, except ID, which is the accessory ID
	 * #doc-out-desc 
	 */
    public function parseResults(LoopResult $results)
    {
        $results = parent::parseResults($results);

        foreach ($results as $loopResultRow) {
            $accessoryProductId = $loopResultRow->get('ID');

            $loopResultRow
                ->set('ID', $this->accessoryId[$accessoryProductId])
                ->set('POSITION', $this->accessoryPosition[$accessoryProductId])
                ->set('ACCESSORY_ID', $accessoryProductId)
            ;
        }

        return $results;
    }
}
