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
use Thelia\Model\Map\TaxTableMap;
use Thelia\Model\TaxRuleCountry as TaxRuleCountryModel;
use Thelia\Model\TaxRuleCountryQuery;
use Thelia\Type;
use Thelia\Type\TypeCollection;

/**
 * TaxRuleCountry loop.
 *
 * Two functions provided by this loop depending of the attribute `ask` :
 * - `country` : list all country/state having the same taxes configuration (same tax rule, same taxes, same order)
 * - `taxes` : list taxes for this tax rule and country/state
 *
 * Class TaxRuleCountry
 * 
 * #doc-usage {loop type="tax_rule_country" name="the-loop-name" [argument="value"], [...]}
 * #doc-desc Taxes by country loop.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int      getCountry()
 * @method int|null getState()
 * @method int      getTaxRule()
 * @method string   getAsk()
 */
class TaxRuleCountry extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $taxCountForOriginCountry;

    protected $timestampable = true;

    /**
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name ask
	 * #doc-arg-desc to choose the function provided by te loop
	 * #doc-arg-default taxes
	 * #doc-arg-example ask="countries"
	 * 
	 * #doc-arg-name country *
	 * #doc-arg-desc the country where the tax applies
	 * #doc-arg-default null
	 * #doc-arg-example country="14"
	 * 
	 * #doc-arg-name state
	 * #doc-arg-desc the state where the tax applies
	 * #doc-arg-example state="45"
	 * 
	 * #doc-arg-name tax_rule *
	 * #doc-arg-desc the tax rule
	 * #doc-arg-default null
	 * #doc-arg-example tax_rule="2"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('country', null, true),
            new Argument(
                'ask',
                new TypeCollection(
                    new Type\EnumType(['taxes', 'countries'])
                ),
                'taxes'
            ),
            Argument::createIntTypeArgument('tax_rule', null, true)
        );
    }

    public function buildModelCriteria()
    {
        $ask = $this->getAsk();

        if ($ask === 'countries') {
            return null;
        }

        $country = $this->getCountry();
        $state = $this->getState();
        $taxRule = $this->getTaxRule();

        $search = TaxRuleCountryQuery::create();

        $search->filterByCountryId($country);
        $search->filterByStateId($state);
        $search->filterByTaxRuleId($taxRule);

        /* manage tax translation */
        $this->configureI18nProcessing(
            $search,
            ['TITLE', 'DESCRIPTION'],
            TaxTableMap::TABLE_NAME,
            'TAX_ID'
        );

        $search->orderByPosition(Criteria::ASC);

        return $search;
    }

	 /**
	 * 
	 * #doc-out-name $COUNTRY
	 * #doc-out-desc the country
	 * 
	 * #doc-out-name $POSITION
	 * #doc-out-desc the tax rule position
	 * 
	 * #doc-out-name $STATE
	 * #doc-out-desc the state
	 * 
	 * #doc-out-name $TAX
	 * #doc-out-desc the tax id
	 * 
	 * #doc-out-name $TAX_DESCRIPTION
	 * #doc-out-desc the description of the tax
	 * 
	 * #doc-out-name $TAX_TITLE
	 * #doc-out-desc the title of the tax
	 * 
	 * #doc-out-name $TAX_RULE
	 * #doc-out-desc the tax rule
	 */
    public function parseResults(LoopResult $loopResult)
    {
        if ($this->getAsk() === 'countries') {
            return $loopResult;
        }

        /** @var TaxRuleCountryModel $taxRuleCountry */
        foreach ($loopResult->getResultDataCollection() as $taxRuleCountry) {
            $loopResultRow = new LoopResultRow($taxRuleCountry);
            $loopResultRow
                ->set('TAX_RULE', $taxRuleCountry->getTaxRuleId())
                ->set('COUNTRY', $taxRuleCountry->getCountryId())
                ->set('STATE', $taxRuleCountry->getStateId())
                ->set('TAX', $taxRuleCountry->getTaxId())
                ->set('POSITION', $taxRuleCountry->getPosition())
                ->set('TAX_TITLE', $taxRuleCountry->getVirtualColumn(TaxTableMap::TABLE_NAME.'_i18n_TITLE'))
                ->set(
                    'TAX_DESCRIPTION',
                    $taxRuleCountry->getVirtualColumn(TaxTableMap::TABLE_NAME.'_i18n_DESCRIPTION')
                )
            ;

            $this->addOutputFields($loopResultRow, $taxRuleCountry);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
