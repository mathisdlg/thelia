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
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            // #doc-arg-desc A single or list of lang ids.
            Argument::createIntListTypeArgument('id'),
            // #doc-arg-desc A single or list of lang code.
            Argument::createAnyListTypeArgument('code'),
            // #doc-arg-desc A single or list of lang locale.
            Argument::createAnyListTypeArgument('locale'),
            // #doc-arg-desc A single or list of lang ids.
            Argument::createIntListTypeArgument('exclude'),
            // #doc-arg-desc returns only active languages
            Argument::createBooleanOrBothTypeArgument('active', true),
            // #doc-arg-desc returns only visible languages
            Argument::createBooleanOrBothTypeArgument('visible', true),
            // #doc-arg-desc returns only the default language
            Argument::createBooleanTypeArgument('default_only', false),
            // #doc-arg-desc Exclude the default language from results
            Argument::createBooleanTypeArgument('exclude_default', false),
            // #doc-arg-desc A list of values see sorting possible values
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

    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\Lang $result */
        foreach ($loopResult->getResultDataCollection() as $result) {
            $loopResultRow = new LoopResultRow($result);

            $loopResultRow
		        // #doc-out-desc the order id
                ->set('ID', $result->getId())
		        // #doc-out-desc lang title
                ->set('TITLE', $result->getTitle())
		        // #doc-out-desc lang code, example : fr
                ->set('CODE', $result->getCode())
		        // #doc-out-desc lang locale, example : fr_FR
                ->set('LOCALE', $result->getLocale())
		        // #doc-out-desc the lang URL, only if a specific URL is defined for each lang
                ->set('URL', $result->getUrl())
		        // #doc-out-desc check if the lang is active or not
                ->set('ACTIVE', $result->getActive())
		        // #doc-out-desc check if the lang is visible or not
                ->set('VISIBLE', $result->getVisible())
		        // #doc-out-desc check if the current result is the default one
                ->set('IS_DEFAULT', $result->getByDefault())
		        // #doc-out-desc the lang date format
                ->set('DATE_FORMAT', $result->getDateFormat())
		        // #doc-out-desc the lang time format
                ->set('TIME_FORMAT', $result->getTimeFormat())
		        // #doc-out-desc the lang decimal separator, such as , or .
                ->set('DECIMAL_SEPARATOR', $result->getDecimalSeparator())
		        // #doc-out-desc the lang thousangs separator
                ->set('THOUSANDS_SEPARATOR', $result->getThousandsSeparator())
		        // #doc-out-desc the number of digits after the decimal separator
                ->set('DECIMAL_COUNT', $result->getDecimals())
		        // #doc-out-desc lang position
                ->set('POSITION', $result->getPosition())
            ;

            $this->addOutputFields($loopResultRow, $result);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
