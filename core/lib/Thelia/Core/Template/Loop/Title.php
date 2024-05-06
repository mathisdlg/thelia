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
use Thelia\Model\CustomerTitle as CustomerTitleModel;
use Thelia\Model\CustomerTitleQuery;

/**
 * Title loop.
 *
 * Class Title
 * 
 * #doc-desc Title loop lists titles.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[] getId()
 */
class Title extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            // #doc-arg-desc A single or a list of title ids.
            Argument::createIntListTypeArgument('id')
        );
    }

    public function buildModelCriteria()
    {
        $search = CustomerTitleQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search, ['SHORT', 'LONG']);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $search->orderByPosition();

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var CustomerTitleModel $title */
        foreach ($loopResult->getResultDataCollection() as $title) {
            $loopResultRow = new LoopResultRow($title);
            // #doc-out-desc the title id
            $loopResultRow->set('ID', $title->getId())
                // #doc-out-desc check if the content is translated
                ->set('IS_TRANSLATED', $title->getVirtualColumn('IS_TRANSLATED'))
                // #doc-out-desc the locale (e.g. fr_FR) of the returned data
                ->set('LOCALE', $this->locale)
                // #doc-out-desc return if the title is by default title
                ->set('DEFAULT', $title->getByDefault())
                // #doc-out-desc the short title
                ->set('SHORT', $title->getVirtualColumn('i18n_SHORT'))
                // #doc-out-desc the full title
                ->set('LONG', $title->getVirtualColumn('i18n_LONG'))
                // #doc-out-desc the title position
                ->set('POSITION', $title->getPosition());
            $this->addOutputFields($loopResultRow, $title);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
