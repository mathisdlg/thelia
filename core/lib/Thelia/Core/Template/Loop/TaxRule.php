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
use Thelia\Model\TaxRule as TaxRuleModel;
use Thelia\Model\TaxRuleQuery;
use Thelia\Type;
use Thelia\Type\TypeCollection;

/**
 * TaxRule loop.
 *
 * Class TaxRule
 * 
 * #doc-usage {loop type="tax_rule" name="the-loop-name" [argument="value"], [...]}
 * #doc-desc loop displaying taxes rules created.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]    getId()
 * @method int[]    getExclude()
 * @method string[] getOrder()
 */
class TaxRule extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name exclude
	 * #doc-arg-desc A single or list of tax rule ids to exclude
	 * #doc-arg-example exclude="2", exclude="1,4"
	 * 
	 * #doc-arg-name id
	 * #doc-arg-desc A single or list of tax rule ids.
	 * #doc-arg-example id="2", id="1,4"
	 * 
	 * #doc-arg-name order
	 * #doc-arg-desc A list of values see sorting possible values
	 * #doc-arg-default alpha
	 * #doc-arg-example order="random"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('exclude'),
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
        $search = TaxRuleQuery::create();

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

	 /**
	 * 
	 * #doc-out-name $DESCRIPTION
	 * #doc-out-desc Tax description
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the tax id
	 * 
	 * #doc-out-name $IS_DEFAULT
	 * #doc-out-desc check if it's the default tax rule
	 * 
	 * #doc-out-name $IS_TRANSLATED
	 * #doc-out-desc check if the tax rule is translated
	 * 
	 * #doc-out-name $LOCALE
	 * #doc-out-desc The locale used for this research
	 * 
	 * #doc-out-name $TITLE
	 * #doc-out-desc Tax title
	 */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var TaxRuleModel $taxRule */
        foreach ($loopResult->getResultDataCollection() as $taxRule) {
            $loopResultRow = new LoopResultRow($taxRule);

            $loopResultRow
                ->set('ID', $taxRule->getId())
                ->set('IS_TRANSLATED', $taxRule->getVirtualColumn('IS_TRANSLATED'))
                ->set('LOCALE', $this->locale)
                ->set('TITLE', $taxRule->getVirtualColumn('i18n_TITLE'))
                ->set('DESCRIPTION', $taxRule->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('IS_DEFAULT', $taxRule->getIsDefault() ? '1' : '0')
            ;
            $this->addOutputFields($loopResultRow, $taxRule);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
