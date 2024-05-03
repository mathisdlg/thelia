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
use Thelia\Model\CountryAreaQuery;
use Thelia\Model\CountryQuery;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Country loop.
 *
 * Class Country
 * 
 * #doc-desc Country loop lists countries.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]       getId()
 * @method int[]       getArea()
 * @method int[]       getExcludeArea()
 * @method int[]       getExclude()
 * @method int[]       getWithArea()
 * @method bool|string getHasStates()
 * @method bool|string getVisible()
 * @method string[]    getOrder()
 */
class Country extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name area
	 * #doc-arg-desc A single or a list of area ids.
	 * #doc-arg-example area="10,9", area: "500"
	 * 
	 * #doc-arg-name exclude
	 * #doc-arg-desc A single or a list of country ids to exclude from the results.
	 * #doc-arg-example exclude="2", exclude="1,4,7"
	 * 
	 * #doc-arg-name exclude_area
	 * #doc-arg-desc A single or list of area IDs. Countries which belongs to these areas are excluded from the results
	 * #doc-arg-example exclude_area="7", exclude_area="3,102,14"
	 * 
	 * #doc-arg-name has_states
	 * #doc-arg-desc A boolean value to return countries that have states or not (possible values : yes, no or *).
	 * #doc-arg-example has_states="no"
	 * 
	 * #doc-arg-name id
	 * #doc-arg-desc A single or a list of country ids.
	 * #doc-arg-example id="2", id="1,4,7"
	 * 
	 * #doc-arg-name order
	 * #doc-arg-desc A list of values see sorting possible values
	 * #doc-arg-example order="alpha_reverse"
	 * 
	 * #doc-arg-name visible
	 * #doc-arg-desc A boolean value to return visible or not visible countries (possible values : yes, no or *).
	 * #doc-arg-example visible="no"
	 * 
	 * #doc-arg-name with_area
	 * #doc-arg-desc A boolean value to return either countries whose area is defined either all the others.
	 * #doc-arg-example with_area="true"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('area'),
            Argument::createIntListTypeArgument('exclude_area'),
            Argument::createBooleanTypeArgument('with_area'),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createBooleanOrBothTypeArgument('has_states', BooleanOrBothType::ANY),
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(
                        [
                            'id', 'id_reverse',
                            'alpha', 'alpha_reverse',
                            'visible', 'visible_reverse',
                            'random',
                        ]
                    )
                ),
                'id'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = CountryQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $area = $this->getArea();

        if (null !== $area) {
            $search
                ->useCountryAreaQuery('with_area')
                ->filterByAreaId($area, Criteria::IN)
                ->endUse();
        }

        $excludeArea = $this->getExcludeArea();

        if (null !== $excludeArea) {
            // FIXME : did not find a way to do this in a single request :(
            // select * from country where id not in (select country_id from country_area where area in (...))
            $countries = CountryAreaQuery::create()
                ->filterByAreaId($excludeArea, Criteria::IN)
                ->select(['country_id'])
                ->find()
            ;

            $search->filterById($countries->toArray(), Criteria::NOT_IN);
        }

        $withArea = $this->getWithArea();

        if (true === $withArea) {
            $search
                ->distinct()
                ->joinCountryArea('with_area', Criteria::LEFT_JOIN)
                ->where('`with_area`.country_id '.Criteria::ISNOTNULL);
        } elseif (false === $withArea) {
            $search
                ->joinCountryArea('with_area', Criteria::LEFT_JOIN)
                ->where('`with_area`.country_id '.Criteria::ISNULL);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $hasStates = $this->getHasStates();
        if ($hasStates !== BooleanOrBothType::ANY) {
            $search->filterByHasStates($hasStates ? 1 : 0);
        }

        $visible = $this->getVisible();
        if ($visible !== BooleanOrBothType::ANY) {
            $search->filterByVisible($visible ? 1 : 0);
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
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case 'alpha_reverse':
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case 'visible':
                    $search->orderByVisible(Criteria::ASC);
                    break;
                case 'visible_reverse':
                    $search->orderByVisible(Criteria::DESC);
                    break;
                case 'random':
                    $search->clearOrderByColumns();
                    $search->addAscendingOrderByColumn('RAND()');
                    break 2;
                    break;
            }
        }

        return $search;
    }

	 /**
	 * 
	 * #doc-out-name $CHAPO
	 * #doc-out-desc the country chapo
	 * 
	 * #doc-out-name $DESCRIPTION
	 * #doc-out-desc the country description
	 * 
	 * #doc-out-name $HAS_STATES
	 * #doc-out-desc 1 if the country has states, 0 otherwise
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the country id
	 * 
	 * #doc-out-name $ISOALPHA2
	 * #doc-out-desc the ISO 2 characters country code
	 * 
	 * #doc-out-name $ISOALPHA3
	 * #doc-out-desc the ISO 3 characters country code
	 * 
	 * #doc-out-name $ISOCODE
	 * #doc-out-desc the ISO numeric country code
	 * 
	 * #doc-out-name $IS_DEFAULT
	 * #doc-out-desc 1 if the country is the default one, 0 otherwise
	 * 
	 * #doc-out-name $IS_SHOP_COUNTRY
	 * #doc-out-desc 1 if the country is the shop country, 0 otherwise
	 * 
	 * #doc-out-name $IS_TRANSLATED
	 * #doc-out-desc check if the country is translated
	 * 
	 * #doc-out-name $LOCALE
	 * #doc-out-desc The locale used for this research
	 * 
	 * #doc-out-name $NEED_ZIP_CODE
	 * #doc-out-desc 1 if the country needs a zip code for address, 0 otherwise
	 * 
	 * #doc-out-name $POSTSCRIPTUM
	 * #doc-out-desc the country postscriptum
	 * 
	 * #doc-out-name $TITLE
	 * #doc-out-desc the country title
	 * 
	 * #doc-out-name $VISIBLE
	 * #doc-out-desc true if the country is visible. False otherwise
	 * 
	 * #doc-out-name $ZIP_CODE_FORMAT
	 * #doc-out-desc The format of the zip code for this country where N is a digit, L a letter and C a state ISO code.
	 */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\Country $country */
        foreach ($loopResult->getResultDataCollection() as $country) {
            $loopResultRow = new LoopResultRow($country);
            $loopResultRow
                ->set('ID', $country->getId())
                ->set('VISIBLE', $country->getVisible())
                ->set('IS_TRANSLATED', $country->getVirtualColumn('IS_TRANSLATED'))
                ->set('LOCALE', $this->locale)
                ->set('TITLE', $country->getVirtualColumn('i18n_TITLE'))
                ->set('CHAPO', $country->getVirtualColumn('i18n_CHAPO'))
                ->set('DESCRIPTION', $country->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('POSTSCRIPTUM', $country->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set('ISOCODE', sprintf('%03d', $country->getIsocode()))
                ->set('ISOALPHA2', $country->getIsoalpha2())
                ->set('ISOALPHA3', $country->getIsoalpha3())
                ->set('IS_DEFAULT', $country->getByDefault() ? '1' : '0')
                ->set('IS_SHOP_COUNTRY', $country->getShopCountry() ? '1' : '0')
                ->set('HAS_STATES', $country->getHasStates() ? '1' : '0')
                ->set('NEED_ZIP_CODE', $country->getNeedZipCode() ? '1' : '0')
                ->set('ZIP_CODE_FORMAT', $country->getZipCodeFormat())
            ;

            $this->addOutputFields($loopResultRow, $country);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
