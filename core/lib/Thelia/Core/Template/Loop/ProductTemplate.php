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
use Thelia\Model\Template as TemplateModel;
use Thelia\Model\TemplateQuery;

/**
 * Template loop.
 *
 * Class Template
 * 
 * #doc-desc Product template loop to display product templates.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[] getId()
 * @method int[] getExclude()
 */
class ProductTemplate extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name exclude
	 * #doc-arg-desc A single or a list of sale ids to excluded from results.
	 * #doc-arg-example exclude="2", exclude="1,4,7"
	 * 
	 * #doc-arg-name id
	 * #doc-arg-desc A single or a list of sale ids.
	 * #doc-arg-example id="2", id="1,4,7"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('exclude')
        );
    }

    public function buildModelCriteria()
    {
        $search = TemplateQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search, ['NAME']);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        return $search;
    }

	 /**
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the content id
	 * 
	 * #doc-out-name $IS_TRANSLATED
	 * #doc-out-desc check if the content is translated
	 * 
	 * #doc-out-name $LOCALE
	 * #doc-out-desc the locale (e.g. fr_FR) of the returned data
	 * 
	 * #doc-out-name $NAME
	 * #doc-out-desc the template name
	 */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var TemplateModel $template */
        foreach ($loopResult->getResultDataCollection() as $template) {
            $loopResultRow = new LoopResultRow($template);

            $loopResultRow
                ->set('ID', $template->getId())
                ->set('IS_TRANSLATED', $template->getVirtualColumn('IS_TRANSLATED'))
                ->set('LOCALE', $this->locale)
                ->set('NAME', $template->getVirtualColumn('i18n_NAME'))
            ;
            $this->addOutputFields($loopResultRow, $template);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
