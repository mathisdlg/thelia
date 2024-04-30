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
use Thelia\Model\LangQuery;
use Thelia\Type;

/**
 * Language loop, to get a list of available languages.
 *
 * - id is the language id
 * - exclude is a comma separated list of lang IDs that will be excluded from output
 * - default if 1, the loop return only default lang. If 0, return all but the default language
 * 
 * #doc-usage {loop type="lang" name="the-loop-name" [argument="value"], [...]}
 * #doc-desc Lang loop.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * @method int[]    getId()
 * @method string[] getCode()
 * @method string[] getLocale()
 * @method int[]    getExclude()
 * @method bool     getActive()
 * @method bool     getVisible()
 * @method bool     getDefaultOnly()
 * @method bool     getExcludeDefault()
 * @method string[] getOrder()
 */
class Lang extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name acive
	 * #doc-arg-desc returns only active languages
	 * #doc-arg-default true
	 * #doc-arg-example active="false"
	 * 
	 * #doc-arg-name code
	 * #doc-arg-desc A single or list of lang code.
	 * #doc-arg-example code="fr", code="fr,en"
	 * 
	 * #doc-arg-name default_only
	 * #doc-arg-desc returns only the default language
	 * #doc-arg-default false
	 * #doc-arg-example default_only="true"
	 * 
	 * #doc-arg-name exclude
	 * #doc-arg-desc A single or list of lang ids.
	 * #doc-arg-example exclude="2", exclude="1,3"
	 * 
	 * #doc-arg-name exclude_default
	 * #doc-arg-desc Exclude the default language from results
	 * #doc-arg-default false
	 * #doc-arg-example exclude_default="true"
	 * 
	 * #doc-arg-name id
	 * #doc-arg-desc A single or list of lang ids.
	 * #doc-arg-example id="2"
	 * 
	 * #doc-arg-name locale
	 * #doc-arg-desc A single or list of lang locale.
	 * #doc-arg-example code="fr_FR", code="fr_FR,fr_CA"
	 * 
	 * #doc-arg-name order
	 * #doc-arg-desc A list of values see sorting possible values
	 * #doc-arg-default position
	 * #doc-arg-example order="alpha_reverse"
	 * 
	 * #doc-arg-name visible
	 * #doc-arg-desc returns only visible languages
	 * #doc-arg-default true
	 * #doc-arg-example visible="false"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createAnyListTypeArgument('code'),
            Argument::createAnyListTypeArgument('locale'),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createBooleanOrBothTypeArgument('active', true),
            Argument::createBooleanOrBothTypeArgument('visible', true),
            Argument::createBooleanTypeArgument('default_only', false),
            Argument::createBooleanTypeArgument('exclude_default', false),
            Argument::createEnumListTypeArgument(
                'order',
                [
                    'id', 'id_reverse',
                    'alpha', 'alpha_reverse',
                    'position', 'position_reverse',
                ],
                'position'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = LangQuery::create();

        if (null !== $id = $this->getId()) {
            $search->filterById($id, Criteria::IN);
        }

        if (null !== $code = $this->getCode()) {
            $search->filterByCode($code, Criteria::IN);
        }

        if (null !== $locale = $this->getLocale()) {
            $search->filterByLocale($locale, Criteria::IN);
        }

        if (!$this->getBackendContext() && Type\BooleanOrBothType::ANY !== $visible = $this->getVisible()) {
            $search->filterByVisible($visible);
        }

        if (Type\BooleanOrBothType::ANY !== $active = $this->getActive()) {
            $search->filterByActive($active);
        }

        if ($this->getDefaultOnly()) {
            $search->filterByByDefault(true);
        }

        if ($this->getExcludeDefault()) {
            $search->filterByByDefault(false);
        }

        if (null !== $exclude = $this->getExclude()) {
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
                    $search->orderByTitle(Criteria::ASC);
                    break;
                case 'alpha_reverse':
                    $search->orderByTitle(Criteria::DESC);
                    break;
                case 'position':
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case 'position_reverse':
                    $search->orderByPosition(Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

	 /**
	 * 
	 * #doc-out-name $ACTIVE
	 * #doc-out-desc check if the lang is active or not
	 * 
	 * #doc-out-name $CODE
	 * #doc-out-desc lang code, example : fr
	 * 
	 * #doc-out-name $DATE_FORMAT
	 * #doc-out-desc the lang date format
	 * 
	 * #doc-out-name $DECIMAL_COUNT
	 * #doc-out-desc the number of digits after the decimal separator
	 * 
	 * #doc-out-name $DECIMAL_SEPARATOR
	 * #doc-out-desc the lang decimal separator, such as , or .
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the order id
	 * 
	 * #doc-out-name $IS_DEFAULT
	 * #doc-out-desc check if the current result is the default one
	 * 
	 * #doc-out-name $LOCALE
	 * #doc-out-desc lang locale, example : fr_FR
	 * 
	 * #doc-out-name $POSITION
	 * #doc-out-desc lang position
	 * 
	 * #doc-out-name $THOUSANDS_SEPARATOR
	 * #doc-out-desc the lang thousangs separator
	 * 
	 * #doc-out-name $TIME_FORMAT
	 * #doc-out-desc the lang time format
	 * 
	 * #doc-out-name $TITLE
	 * #doc-out-desc lang title
	 * 
	 * #doc-out-name $URL
	 * #doc-out-desc the lang URL, only if a specific URL is defined for each lang
	 * 
	 * #doc-out-name $VISIBLE
	 * #doc-out-desc check if the lang is visible or not
	 */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\Lang $result */
        foreach ($loopResult->getResultDataCollection() as $result) {
            $loopResultRow = new LoopResultRow($result);

            $loopResultRow
                ->set('ID', $result->getId())
                ->set('TITLE', $result->getTitle())
                ->set('CODE', $result->getCode())
                ->set('LOCALE', $result->getLocale())
                ->set('URL', $result->getUrl())
                ->set('ACTIVE', $result->getActive())
                ->set('VISIBLE', $result->getVisible())
                ->set('IS_DEFAULT', $result->getByDefault())
                ->set('DATE_FORMAT', $result->getDateFormat())
                ->set('TIME_FORMAT', $result->getTimeFormat())
                ->set('DECIMAL_SEPARATOR', $result->getDecimalSeparator())
                ->set('THOUSANDS_SEPARATOR', $result->getThousandsSeparator())
                ->set('DECIMAL_COUNT', $result->getDecimals())
                ->set('POSITION', $result->getPosition())
            ;

            $this->addOutputFields($loopResultRow, $result);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
