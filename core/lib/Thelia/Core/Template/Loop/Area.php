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
use Thelia\Model\Area as AreaModel;
use Thelia\Model\AreaQuery;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Class Area.
 * 
 * #doc-usage {loop type="area" name="the-loop-name" [argument="value"], [...]}
 * #doc-desc Area loop returns shipping zones information.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 *
 * @method int[]       getId()
 * @method int[]       getCountry()
 * @method int         getWithZone()
 * @method int         getWithoutZone()
 * @method bool|string getUnassigned()
 * @method bool|string getGroupByCountryArea()
 * @method int[]       getModuleId()
 * @method string[]    getOrder()
 */
class Area extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name country
	 * #doc-arg-desc A list of country IDs. Only zones including these countries will be returned.
	 * #doc-arg-example 
	 * 
	 * #doc-arg-name GroupByCountryArea
	 * #doc-arg-desc A boolean that specifies whether the results should be grouped by country and geographic area.
	 * #doc-arg-example 
	 * 
	 * #doc-arg-name id
	 * #doc-arg-desc A single or a list of shipping zones ids.
	 * #doc-arg-example id="2", id="1,4,7"
	 * 
	 * #doc-arg-name module_id
	 * #doc-arg-desc A comma separated list of module IDs. If not empty, only zones for the specified modules are returned.
	 * #doc-arg-example 
	 * 
	 * #doc-arg-name order
	 * #doc-arg-desc A list of values <br/> Expected values
	 * #doc-arg-default name
	 * #doc-arg-example order="alpha"
	 * 
	 * #doc-arg-name unassigned
	 * #doc-arg-desc If true, returns shipping zones not assigned to any delivery module.
	 * #doc-arg-example 
	 * 
	 * #doc-arg-name with_zone
	 * #doc-arg-desc A module ID. Returns shipping zones which are assigned to this module ID
	 * #doc-arg-example 
	 * 
	 * #doc-arg-name without_zone
	 * #doc-arg-desc A module ID. Returns shipping zones which are not assigned to this module ID
	 * #doc-arg-example 
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('country'),
            Argument::createIntTypeArgument('with_zone'),
            Argument::createIntTypeArgument('without_zone'),
            Argument::createBooleanOrBothTypeArgument('unassigned'),
            Argument::createBooleanOrBothTypeArgument('group_by_country_area'),
            Argument::createIntListTypeArgument('module_id'),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType([
                        'id', 'id_reverse',
                        'alpha', 'name', 'name_reverse',
                    ])
                ),
                'name'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = AreaQuery::create();

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $withZone = $this->getWithZone();

        if ($withZone) {
            $search->joinAreaDeliveryModule('with_zone')
                ->where('`with_zone`.delivery_module_id '.Criteria::EQUAL.' ?', $withZone, \PDO::PARAM_INT);
        }

        $withoutZone = $this->getWithoutZone();

        if ($withoutZone) {
            $search->joinAreaDeliveryModule('without_zone', Criteria::LEFT_JOIN)
                ->addJoinCondition('without_zone', 'delivery_module_id '.Criteria::EQUAL.' ?', $withoutZone, null, \PDO::PARAM_INT)
                ->where('`without_zone`.delivery_module_id '.Criteria::ISNULL);
        }

        $notAssigned = $this->getUnassigned();

        if ($notAssigned) {
            $search
                ->joinAreaDeliveryModule('unassigned', Criteria::LEFT_JOIN)
                ->where('`unassigned`.delivery_module_id '.Criteria::ISNULL);
        }

        $modules = $this->getModuleId();

        if (null !== $modules) {
            $search
                ->useAreaDeliveryModuleQuery()
                ->filterByDeliveryModuleId($modules, Criteria::IN)
                ->endUse();
        }

        $countries = $this->getCountry();

        if (null !== $countries) {
            $search
                ->useCountryAreaQuery()
                ->filterByCountryId($countries, Criteria::IN)
                ->endUse();
        }

        $groupByCountryArea = $this->getGroupByCountryArea();

        if ($groupByCountryArea) {
            $search
                ->useCountryAreaQuery()
                ->groupByAreaId()
                ->endUse();
        }

        $orders = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'id':
                    $search->orderById(Criteria::ASC);
                    break;
                case 'id_reverse':
                    $search->orderById(Criteria::DESC);
                    break;
                case 'alpha':
                case 'name':
                    $search->orderByName(Criteria::ASC);
                    break;
                case 'name_reverse':
                    $search->orderByName(Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

	 /**
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the shipping zone id
	 * 
	 * #doc-out-name $NAME
	 * #doc-out-desc the accessory name
	 * 
	 * #doc-out-name $POSTAGE
	 * #doc-out-desc shipping costs associated with each geographic are
	 */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var AreaModel $area */
        foreach ($loopResult->getResultDataCollection() as $area) {
            $loopResultRow = new LoopResultRow($area);

            $loopResultRow
                ->set('ID', $area->getId())
                ->set('NAME', $area->getName())
                ->set('POSTAGE', $area->getPostage())
            ;
            $this->addOutputFields($loopResultRow, $area);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
