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
use Thelia\Model\Profile as ProfileModel;
use Thelia\Model\ProfileQuery;

/**
 * Profile loop.
 *
 * Class Profile
 * 
 * #doc-desc Profile loop lists profiles.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[] getId()
 */
class Profile extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
		    // #doc-arg-desc A single or a list of sale ids.
            Argument::createIntListTypeArgument('id')
        );
    }

    public function buildModelCriteria()
    {
        $search = ProfileQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $search->orderById(Criteria::ASC);

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var ProfileModel $profile */
        foreach ($loopResult->getResultDataCollection() as $profile) {
            $loopResultRow = new LoopResultRow($profile);
		    // #doc-out-desc the content id
            $loopResultRow->set('ID', $profile->getId())
		        // #doc-out-desc check if the content is translated
                ->set('IS_TRANSLATED', $profile->getVirtualColumn('IS_TRANSLATED'))
		        // #doc-out-desc the locale (e.g. fr_FR) of the returned data
                ->set('LOCALE', $this->locale)
		        // #doc-out-desc the content code
                ->set('CODE', $profile->getCode())
		        // #doc-out-desc the title
                ->set('TITLE', $profile->getVirtualColumn('i18n_TITLE'))
		        // #doc-out-desc the chapo
                ->set('CHAPO', $profile->getVirtualColumn('i18n_CHAPO'))
		        // #doc-out-desc the content description
                ->set('DESCRIPTION', $profile->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('POSTSCRIPTUM', $profile->getVirtualColumn('i18n_POSTSCRIPTUM'))
            ;
            $this->addOutputFields($loopResultRow, $profile);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
