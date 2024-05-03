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
use Thelia\Model\CountryAreaQuery;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Country Area loop.
 *
 * Class CountryArea
 * 
 * #doc-desc Country area loop lists.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]    getId()
 * @method int[]    getArea()
 * @method int[]    getCountry()
 * @method int[]    getState()
 * @method string[] getOrder()
 */
class CountryArea extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name area
	 * #doc-arg-desc A single or a list of area ids.
	 * #doc-arg-example area="10,9", area: "500"
	 * 
	 * #doc-arg-name country
	 * #doc-arg-desc A single or a list of country ids.
	 * #doc-arg-example country="2", country="1,4,7"
	 * 
	 * #doc-arg-name id
	 * #doc-arg-desc A single or a list of country ids.
	 * #doc-arg-example id="2", id="1,4,7"
	 * 
	 * #doc-arg-name order
	 * #doc-arg-desc A list of values <br/> Expected values
	 * #doc-arg-example order="alpha_reverse"
	 * 
	 * #doc-arg-name states
	 * #doc-arg-desc A boolean value to return countries that have states or not (possible values : yes, no or *)
	 * #doc-arg-example states="no"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('area'),
            Argument::createIntListTypeArgument('country'),
            Argument::createIntListTypeArgument('state'),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(
                        [
                            'id', 'id_reverse',
                            'area', 'area_reverse',
                            'country', 'country_reverse',
                            'state', 'state_reverse',
                        ]
                    )
                ),
                'id'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = CountryAreaQuery::create();

        if (null !== $id = $this->getId()) {
            $search->filterById($id, Criteria::IN);
        }

        if (null !== $areas = $this->getArea()) {
            $search->filterByAreaId($areas, Criteria::IN);
        }

        if (null !== $countries = $this->getCountry()) {
            $search->filterByCountryId($countries, Criteria::IN);
        }

        if (null !== $states = $this->getState()) {
            $search->filterByStateId($states, Criteria::IN);
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
                case 'area':
                    $search->orderByAreaId(Criteria::ASC);
                    break;
                case 'area_reverse':
                    $search->orderByAreaId(Criteria::DESC);
                    break;
                case 'country':
                    $search->orderByCountryId(Criteria::ASC);
                    break;
                case 'country_reverse':
                    $search->orderByCountryId(Criteria::DESC);
                    break;
                case 'state':
                    $search->orderByStateId(Criteria::ASC);
                    break;
                case 'state_reverse':
                    $search->orderByStateId(Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

	 /**
	 * 
	 * #doc-out-name $AREA_ID
	 * #doc-out-desc The ID of the area corresponding to the country.
	 * 
	 * #doc-out-name $COUNTRY_ID
	 * #doc-out-desc The ID of the country.
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc The ID of the country area.
	 * 
	 * #doc-out-name $STATE_ID
	 * #doc-out-desc The ID of the state corresponding to the country.
	 */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\CountryArea $countryArea */
        foreach ($loopResult->getResultDataCollection() as $countryArea) {
            $loopResultRow = new LoopResultRow($countryArea);
            $loopResultRow
                ->set('ID', $countryArea->getId())
                ->set('AREA_ID', $countryArea->getAreaId())
                ->set('COUNTRY_ID', $countryArea->getCountryId())
                ->set('STATE_ID', $countryArea->getStateId())
            ;

            $this->addOutputFields($loopResultRow, $countryArea);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
