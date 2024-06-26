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
use Thelia\Model\FeatureAv;
use Thelia\Model\FeatureAvQuery;
use Thelia\Type;
use Thelia\Type\TypeCollection;

/**
 * FeatureAvailability loop.
 *
 * Class FeatureAvailability
 * 
 * #doc-desc Feature availability loop lists feature availabilities.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]    getId()
 * @method int[]    getFeature()
 * @method int[]    getExclude()
 * @method string[] getOrder()
 */
class FeatureAvailability extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            // #doc-arg-desc A single or a list of feature availability ids.
            Argument::createIntListTypeArgument('id'),
            // #doc-arg-desc A single or a list of feature ids.
            Argument::createIntListTypeArgument('feature'),
            // #doc-arg-desc A single or a list of feature availability ids to exclude.
            Argument::createIntListTypeArgument('exclude'),
            // #doc-arg-desc A list of values
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(['id', 'id_reverse', 'alpha', 'alpha-reverse', 'alpha_reverse', 'manual', 'manual_reverse'])
                ),
                'manual'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = FeatureAvQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $feature = $this->getFeature();

        if (null !== $feature) {
            $search->filterByFeatureId($feature, Criteria::IN);
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
                case 'alpha-reverse':
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case 'manual':
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case 'manual_reverse':
                    $search->orderByPosition(Criteria::DESC);
                    break;
            }
        }

        // Search only non-freetext feature values.
        $search
            ->useFeatureProductQuery()
                ->filterByIsFreeText(false)
                ->_or()
                ->filterByIsFreeText(null) // does not belong to any product
            ->endUse()
        ;

        // Joining with FeatureProduct may result in multiple occurences of the same FeatureAv. Juste get one.
        $search->distinct();

        return $search;
    }

    
    public function parseResults(LoopResult $loopResult)
    {
        /** @var FeatureAv $featureAv */
        foreach ($loopResult->getResultDataCollection() as $featureAv) {
            $loopResultRow = new LoopResultRow($featureAv);
            // #doc-out-desc the feature availability id
            $loopResultRow->set('ID', $featureAv->getId())
                // #doc-out-desc check if the feature availability is translated
                ->set('IS_TRANSLATED', $featureAv->getVirtualColumn('IS_TRANSLATED'))
                // #doc-out-desc The locale used for this research
                ->set('LOCALE', $this->locale)
                // #doc-out-desc The ID ot the related feature
                ->set('FEATURE_ID', $featureAv->getFeatureId())
                // #doc-out-desc the feature availability title
                ->set('TITLE', $featureAv->getVirtualColumn('i18n_TITLE'))
                // #doc-out-desc the feature availability chapo
                ->set('CHAPO', $featureAv->getVirtualColumn('i18n_CHAPO'))
                // #doc-out-desc the feature availability description
                ->set('DESCRIPTION', $featureAv->getVirtualColumn('i18n_DESCRIPTION'))
                // #doc-out-desc the feature availability postscriptum
                ->set('POSTSCRIPTUM', $featureAv->getVirtualColumn('i18n_POSTSCRIPTUM'))
                // #doc-out-desc the feature availability position
                ->set('POSITION', $featureAv->getPosition());
            $this->addOutputFields($loopResultRow, $featureAv);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
