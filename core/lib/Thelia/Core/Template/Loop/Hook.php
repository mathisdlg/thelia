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

	 /**
	 * 
	 * #doc-arg-name active
	 * #doc-arg-desc If the hook is active or not
	 * #doc-arg-example 
	 * 
	 * #doc-arg-name code
	 * #doc-arg-desc The hook code
	 * #doc-arg-example 
	 * 
	 * #doc-arg-name exclude
	 * #doc-arg-desc A single or a list of hook ids to exclude
	 * #doc-arg-example 
	 * 
	 * #doc-arg-name hook_type
	 * #doc-arg-desc The type of hook
	 * #doc-arg-example 
	 * 
	 * #doc-arg-name order
	 * #doc-arg-desc See Order possible values
	 * #doc-arg-example order='code'
	 */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            new Argument(
                'code',
                new Type\TypeCollection(
                    new Type\AlphaNumStringListType()
                )
            ),
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
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(['id', 'id_reverse', 'code', 'code_reverse', 'alpha', 'alpha_reverse',
                        'manual', 'manual_reverse', 'enabled', 'enabled_reverse', 'native', 'native_reverse', ])
                ),
                'id'
            ),
            Argument::createIntListTypeArgument('exclude'),
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

	 /**
	 * 
	 * #doc-out-name $ACTIVE
	 * #doc-out-desc If the hook is active or not
	 * 
	 * #doc-out-name $BLOCK
	 * #doc-out-desc The [block] column value
	 * 
	 * #doc-out-name $BY_MODULE
	 * #doc-out-desc The value of the bt module field
	 * 
	 * #doc-out-name $CHAPO
	 * #doc-out-desc Chapo
	 * 
	 * #doc-out-name $CODE
	 * #doc-out-desc The hook code
	 * 
	 * #doc-out-name $DESCRIPTION
	 * #doc-out-desc The hook description
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc The hook ID
	 * 
	 * #doc-out-name $IS_TRANSLATED
	 * #doc-out-desc Check if the hook is translated
	 * 
	 * #doc-out-name $LOCALE
	 * #doc-out-desc The locale user for this loop
	 * 
	 * #doc-out-name $NATIVE
	 * #doc-out-desc Naive
	 * 
	 * #doc-out-name $POSITION
	 * #doc-out-desc The hook position
	 * 
	 * #doc-out-name $TITLE
	 * #doc-out-desc Title
	 * 
	 * #doc-out-name $TYPE
	 * #doc-out-desc Type
	 */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\Hook $hook */
        foreach ($loopResult->getResultDataCollection() as $hook) {
            if ($this->getBackendContext()) {
                $loopResultRow = new LoopResultRow($hook);

                $loopResultRow
                    ->set('ID', $hook->getId())
                    ->set('IS_TRANSLATED', $hook->getVirtualColumn('IS_TRANSLATED'))
                    ->set('LOCALE', $this->locale)
                    ->set('TITLE', $hook->getVirtualColumn('i18n_TITLE'))
                    ->set('CHAPO', $hook->getVirtualColumn('i18n_CHAPO'))
                    ->set('DESCRIPTION', $hook->getVirtualColumn('i18n_DESCRIPTION'))
                    ->set('CODE', $hook->getCode())
                    ->set('TYPE', $hook->getType())
                    ->set('NATIVE', $hook->getNative())
                    ->set('ACTIVE', $hook->getActivate())
                    ->set('BY_MODULE', $hook->getByModule())
                    ->set('BLOCK', $hook->getBlock())
                    ->set('POSITION', $hook->getPosition())
                ;

                $this->addOutputFields($loopResultRow, $hook);
                $loopResult->addRow($loopResultRow);
            }
        }

        return $loopResult;
    }
}
