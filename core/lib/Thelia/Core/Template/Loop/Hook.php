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
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\HookQuery;
use Thelia\Type;
use Thelia\Type\TypeCollection;

/**
 * Class Hook.
 * 
 * #doc-desc Get data from the hook table.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 *
 * @method int[]       getId()
 * @method string[]    getCode()
 * @method string[]    getHook_type()
 * @method bool|string getActive()
 * @method int[]       getExclude()
 * @method string[]    getOrder()
 */
class Hook extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            // #doc-arg-desc A single or a list of hook ids.
            Argument::createIntListTypeArgument('id'),
            // #doc-arg-desc The hook code
            new Argument(
                'code',
                new Type\TypeCollection(
                    new Type\AlphaNumStringListType()
                )
            ),
            // #doc-arg-desc The type of hook
            new Argument(
                'hook_type',
                new Type\TypeCollection(
                    new Type\EnumListType([
                        TemplateDefinition::FRONT_OFFICE,
                        TemplateDefinition::BACK_OFFICE,
                        TemplateDefinition::EMAIL,
                        TemplateDefinition::PDF,
                    ])
                )
            ),
            // #doc-arg-desc See Order possible values
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(['id', 'id_reverse', 'code', 'code_reverse', 'alpha', 'alpha_reverse',
                        'manual', 'manual_reverse', 'enabled', 'enabled_reverse', 'native', 'native_reverse', ])
                ),
                'id'
            ),
		    // #doc-arg-desc A single or a list of hook ids to exclude
            Argument::createIntListTypeArgument('exclude'),
		    // #doc-arg-desc If the hook is active or not
            Argument::createBooleanOrBothTypeArgument('active', Type\BooleanOrBothType::ANY)
        );
    }

    public function buildModelCriteria()
    {
        $search = HookQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search, ['TITLE', 'CHAPO', 'DESCRIPTION']);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $code = $this->getCode();
        if (null !== $code) {
            $search->filterByCode($code, Criteria::IN);
        }

        $hookType = $this->getHook_type();
        if (null !== $hookType) {
            $search->filterByType($hookType, Criteria::IN);
        }

        $exclude = $this->getExclude();
        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $active = $this->getActive();
        if ($active !== Type\BooleanOrBothType::ANY) {
            $search->filterByActivate($active ? 1 : 0, Criteria::EQUAL);
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
                case 'code':
                    $search->orderByCode(Criteria::ASC);
                    break;
                case 'code_reverse':
                    $search->orderByCode(Criteria::DESC);
                    break;
                case 'manual':
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case 'manual_reverse':
                    $search->orderByPosition(Criteria::DESC);
                    break;
                case 'enabled':
                    $search->orderByActivate(Criteria::ASC);
                    break;
                case 'enabled_reverse':
                    $search->orderByActivate(Criteria::DESC);
                    break;
                case 'native':
                    $search->orderByNative(Criteria::ASC);
                    break;
                case 'native_reverse':
                    $search->orderByNative(Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\Hook $hook */
        foreach ($loopResult->getResultDataCollection() as $hook) {
            if ($this->getBackendContext()) {
                $loopResultRow = new LoopResultRow($hook);

                $loopResultRow
		            // #doc-out-desc The hook ID
                    ->set('ID', $hook->getId())
		            // #doc-out-desc Check if the hook is translated
                    ->set('IS_TRANSLATED', $hook->getVirtualColumn('IS_TRANSLATED'))
		            // #doc-out-desc The locale user for this loop
                    ->set('LOCALE', $this->locale)
		            // #doc-out-desc Title
                    ->set('TITLE', $hook->getVirtualColumn('i18n_TITLE'))
		            // #doc-out-desc Chapo
                    ->set('CHAPO', $hook->getVirtualColumn('i18n_CHAPO'))
		            // #doc-out-desc The hook description
                    ->set('DESCRIPTION', $hook->getVirtualColumn('i18n_DESCRIPTION'))
		            // #doc-out-desc The hook code
                    ->set('CODE', $hook->getCode())
		            // #doc-out-desc Type
                    ->set('TYPE', $hook->getType())
		            // #doc-out-desc Naive
                    ->set('NATIVE', $hook->getNative())
		            // #doc-out-desc If the hook is active or not
                    ->set('ACTIVE', $hook->getActivate())
		            // #doc-out-desc The value of the bt module field
                    ->set('BY_MODULE', $hook->getByModule())
		            // #doc-out-desc The [block] column value
                    ->set('BLOCK', $hook->getBlock())
		            // #doc-out-desc The hook position
                    ->set('POSITION', $hook->getPosition())
                ;

                $this->addOutputFields($loopResultRow, $hook);
                $loopResult->addRow($loopResultRow);
            }
        }

        return $loopResult;
    }
}
