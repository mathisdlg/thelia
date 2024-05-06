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
use Thelia\Model\Tax as TaxModel;
use Thelia\Model\TaxQuery;
use Thelia\Model\TaxRuleCountryQuery;
use Thelia\Type;
use Thelia\Type\TypeCollection;

/**
 * Tax loop.
 *
 * Class Tax
 * 
 * #doc-desc loop displaying taxes available.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]    getId()
 * @method int[]    getExclude()
 * @method int[]    getTaxRule()
 * @method int[]    getExcludeTaxRule()
 * @method int      getCountry()
 * @method string[] getOrder()
 */
class Tax extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
		    // #doc-arg-desc A single or list of tax ids.
            Argument::createIntListTypeArgument('id'),
		    // #doc-arg-desc A single or list of tax ids to exclude
            Argument::createIntListTypeArgument('exclude'),
		    // #doc-arg-desc A single or list of tax_rule ids
            Argument::createIntListTypeArgument('tax_rule'),
		    // #doc-arg-desc A single or list of tax_rule ids to exclude
            Argument::createIntListTypeArgument('exclude_tax_rule'),
		    // #doc-arg-desc a country id
            Argument::createIntTypeArgument('country'),
            // #doc-arg-desc A list of values see sorting possible values
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(['id', 'id_reverse', 'alpha', 'alpha_reverse'])
                ),
                'alpha'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = TaxQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search, ['TITLE', 'DESCRIPTION']);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $country = $this->getCountry();

        $taxRule = $this->getTaxRule();
        if (null !== $taxRule && null !== $country) {
            $search->filterByTaxRuleCountry(
                TaxRuleCountryQuery::create()
                    ->filterByCountryId($country, Criteria::EQUAL)
                    ->filterByTaxRuleId($taxRule, Criteria::IN)
                    ->find(),
                Criteria::IN
            );
        }

        $excludeTaxRule = $this->getExcludeTaxRule();
        if (null !== $excludeTaxRule && null !== $country) {
            $excludedTaxes = TaxRuleCountryQuery::create()
                ->filterByCountryId($country, Criteria::EQUAL)
                ->filterByTaxRuleId($excludeTaxRule, Criteria::IN)
                ->find();
            /*DOES NOT WORK
             * $search->filterByTaxRuleCountry(
                $excludedTaxes,
                Criteria::NOT_IN
            );*/
            foreach ($excludedTaxes as $excludedTax) {
                $search->filterByTaxRuleCountry($excludedTax, Criteria::NOT_EQUAL);
            }
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
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var TaxModel $tax */
        foreach ($loopResult->getResultDataCollection() as $tax) {
            $loopResultRow = new LoopResultRow($tax);

            $loopResultRow
		        // #doc-out-desc the tax id
                ->set('ID', $tax->getId())
		        // #doc-out-desc The tax type
                ->set('TYPE', $tax->getType())
		        // #doc-out-desc Provides a form-and-javascript-safe version of the type, which is a fully qualified classname, with \
                ->set('ESCAPED_TYPE', TaxModel::escapeTypeName($tax->getType()))
		        // #doc-out-desc All requirements for this tax
                ->set('REQUIREMENTS', $tax->getRequirements())
		        // #doc-out-desc check if the tax is translated
                ->set('IS_TRANSLATED', $tax->getVirtualColumn('IS_TRANSLATED'))
		        // #doc-out-desc The locale used for this research
                ->set('LOCALE', $this->locale)
		        // #doc-out-desc Tax title
                ->set('TITLE', $tax->getVirtualColumn('i18n_TITLE'))
		        // #doc-out-desc Tax description
                ->set('DESCRIPTION', $tax->getVirtualColumn('i18n_DESCRIPTION'))
            ;
            $this->addOutputFields($loopResultRow, $tax);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
