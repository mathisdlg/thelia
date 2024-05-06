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
use Thelia\Model\Message as MessageModel;
use Thelia\Model\MessageQuery;
use Thelia\Type\BooleanOrBothType;

/**
 * Message loop, to access messageuration variables.
 *
 * - id is the message id
 * - name is the message name
 * - hidden filters by hidden status (yes, no, both)
 * - secured filters by secured status (yes, no, both)
 * - exclude is a comma separated list of message IDs that will be excluded from output
 * 
 * #doc-desc Message loop lists all defined messages.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * @method int         getId()
 * @method int[]       getExclude()
 * @method string      getVariable()
 * @method bool|string getHidden()
 * @method bool|string getSecured()
 */
class Message extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
		    // #doc-arg-desc The message ID
            Argument::createIntTypeArgument('id'),
		    // #doc-arg-desc A list of message IDs to exclude
            Argument::createIntListTypeArgument('exclude'),
		    // #doc-arg-desc variable
            Argument::createAnyTypeArgument('variable'),
		    // #doc-arg-desc A boolean to show or hide hidden message
            Argument::createBooleanOrBothTypeArgument('hidden'),
		    // #doc-arg-desc Boolean to show or hide secured messages
            Argument::createBooleanOrBothTypeArgument('secured')
        );
    }

    public function buildModelCriteria()
    {
        $id = $this->getId();
        $name = $this->getVariable();
        $secured = $this->getSecured();
        $exclude = $this->getExclude();

        $search = MessageQuery::create();

        $this->configureI18nProcessing(
            $search,
            [
                'TITLE',
                'SUBJECT',
                'TEXT_MESSAGE',
                'HTML_MESSAGE',
            ]
        );

        if (null !== $id) {
            $search->filterById($id);
        }

        if (null !== $name) {
            $search->filterByName($name);
        }

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        if (null !== $secured && $secured != BooleanOrBothType::ANY) {
            $search->filterBySecured($secured ? 1 : 0);
        }

        $search->orderByName(Criteria::ASC);

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var MessageModel $result */
        foreach ($loopResult->getResultDataCollection() as $result) {
            $loopResultRow = new LoopResultRow($result);

            $loopResultRow
                // #doc-out-desc The message ID
                ->set('ID', $result->getId())
                // #doc-out-desc The message name
                ->set('NAME', $result->getName())
                // #doc-out-desc Check if the message is translated
                ->set('IS_TRANSLATED', $result->getVirtualColumn('IS_TRANSLATED'))
                // #doc-out-desc The locale used for this research
                ->set('LOCALE', $this->locale)
                // #doc-out-desc The message title
                ->set('TITLE', $result->getVirtualColumn('i18n_TITLE'))
                // #doc-out-desc The message subject
                ->set('SUBJECT', $result->getVirtualColumn('i18n_SUBJECT'))
                // #doc-out-desc The text message
                ->set('TEXT_MESSAGE', $result->getVirtualColumn('i18n_TEXT_MESSAGE'))
                // #doc-out-desc The html message
                ->set('HTML_MESSAGE', $result->getVirtualColumn('i18n_HTML_MESSAGE'))
                // #doc-out-desc Check if the message is secured
                ->set('SECURED', $result->getSecured())
            ;
            $this->addOutputFields($loopResultRow, $result);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
