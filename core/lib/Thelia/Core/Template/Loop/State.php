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
use Thelia\Model\StateQuery;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Country loop.
 *
 * Class Country
 * 
 * #doc-desc State loop lists states.
 *
 * @author Julien Chanséaume <julien@thelia.net>
 *
 * @method int[]       getId()
 * @method int[]       getCountry()
 * @method int[]       getExclude()
 * @method bool|string getVisible()
 * @method string[]    getOrder()
 */
class State extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $countable = true;
    protected $timestampable = false;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            // #doc-arg-desc A single or a list of state ids.
            Argument::createIntListTypeArgument('id'),
            // #doc-arg-desc A single or a list of country ids.
            Argument::createIntListTypeArgument('country'),
            // #doc-arg-desc A single or a list of state ids to exclude from the results.
            Argument::createIntListTypeArgument('exclude'),
            // #doc-arg-desc A boolean value to return visible or not visible states (possible values : yes, no or
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            // #doc-arg-desc A list of values see sorting possible values
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(
                        [
                            'id',
                            'id_reverse',
                            'alpha',
                            'alpha_reverse',
                            'visible',
                            'visible_reverse',
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
        $search = StateQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search, ['TITLE']);

        $id = $this->getId();
        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $country = $this->getCountry();
        if (null !== $country) {
            $search->filterByCountryId($country, Criteria::IN);
        }

        $exclude = $this->getExclude();
        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
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

    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\State $state */
        foreach ($loopResult->getResultDataCollection() as $state) {
            $loopResultRow = new LoopResultRow($state);
            $loopResultRow
                // #doc-out-desc the state id
                ->set('ID', $state->getId())
                // #doc-out-desc the country the state belongs
                ->set('COUNTRY', $state->getCountryId())
                // #doc-out-desc true if the state is visible. False otherwise
                ->set('VISIBLE', $state->getVisible())
                // #doc-out-desc check if the state is translated
                ->set('IS_TRANSLATED', $state->getVirtualColumn('IS_TRANSLATED'))
                // #doc-out-desc The locale used for this research
                ->set('LOCALE', $this->locale)
                // #doc-out-desc the state title
                ->set('TITLE', $state->getVirtualColumn('i18n_TITLE'))
                // #doc-out-desc the state ISO code
                ->set('ISOCODE', $state->getIsocode())
            ;

            $this->addOutputFields($loopResultRow, $state);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
