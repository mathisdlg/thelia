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
use Thelia\Core\Template\Element\SearchLoopInterface;
use Thelia\Core\Template\Element\StandardI18nFieldsSearchTrait;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Type;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\TypeCollection;

/**
 * Class Folder.
 * 
 * #doc-desc Folder loop lists folders from your shop.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]       getId()
 * @method int         getParent()
 * @method int         getContent()
 * @method bool        getCurrent()
 * @method bool|string getVisible()
 * @method int[]       getExclude()
 * @method string      getTitle()
 * @method string[]    getOrder()
 * @method bool        getWithPrevNextInfo()
 * @method bool        getNeedCountChild()
 * @method bool        getNeedContentCount()
 * @method bool        getContentCountVisible()
 */
class Folder extends BaseI18nLoop implements PropelSearchLoopInterface, SearchLoopInterface
{
    use StandardI18nFieldsSearchTrait;

    protected $timestampable = true;
    protected $versionable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            // #doc-arg-desc A single or a list of folder ids.
            Argument::createIntListTypeArgument('id'),
            // #doc-arg-desc A single folder id.
            Argument::createIntTypeArgument('parent'),
            // #doc-arg-desc A single content id.
            Argument::createIntTypeArgument('content'),
            // #doc-arg-desc A boolean value which allows either to exclude current folder from results either to match only this folder
            Argument::createBooleanTypeArgument('current'),
            // #doc-arg-desc (**not implemented yet**) A boolean value. If true, only the folders which contains at leat a visible content (either directly or trough a subfolder) are returned
            Argument::createBooleanTypeArgument('not_empty', 0),
            // #doc-arg-desc A boolean value.
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            // #doc-arg-desc Title of the folder.
            Argument::createAnyTypeArgument('title'),
            // #doc-arg-desc A boolean. If set to true, the loop will return the number of sub-folders of each folder
            Argument::createBooleanTypeArgument('need_count_child', true),
            // #doc-arg-desc A boolean. If set to true, the loop will return the number of contents in each folder and its sub-folders
            Argument::createBooleanTypeArgument('need_content_count', true),
            // #doc-arg-desc A list of values
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(
                        [
                            'id', 'id_reverse',
                            'alpha', 'alpha_reverse',
                            'manual', 'manual_reverse',
                            'visible', 'visible_reverse',
                            'random',
                            'created', 'created_reverse',
                            'updated', 'updated_reverse',
                        ]
                    )
                ),
                'manual'
            ),
            // #doc-arg-desc A single or a list of folder ids.
            Argument::createIntListTypeArgument('exclude'),
            // #doc-arg-desc A boolean. If set to true, $PREVIOUS and $NEXT output arguments are available.
            Argument::createBooleanTypeArgument('with_prev_next_info', false),
            // #doc-arg-desc This parameter controls how content is counted. If 'yes' (the default) only visible contents are counted, 'no': only hidden contents are counted, '
            Argument::createBooleanOrBothTypeArgument('content_count_visible', true)
        );
    }

    /**
     * @return array of available field to search in
     */
    public function getSearchIn()
    {
        return $this->getStandardI18nSearchFields();
    }

    /**
     * @param FolderQuery $search
     * @param string      $searchTerm
     * @param array       $searchIn
     * @param string      $searchCriteria
     */
    public function doSearch(&$search, $searchTerm, $searchIn, $searchCriteria): void
    {
        $search->_and();
        $this->addStandardI18nSearch($search, $searchTerm, $searchCriteria, $searchIn);
    }

    public function buildModelCriteria()
    {
        $search = FolderQuery::create();

        /* manage translations */
        $this->configureI18nProcessing(
            $search,
            ['TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM', 'META_TITLE', 'META_DESCRIPTION', 'META_KEYWORDS']
        );

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $parent = $this->getParent();

        if (null !== $parent) {
            $search->filterByParent($parent);
        }

        $current = $this->getCurrent();

        if ($current === true) {
            $search->filterById($this->getCurrentRequest()->get('folder_id'));
        } elseif ($current === false) {
            $search->filterById($this->getCurrentRequest()->get('folder_id'), Criteria::NOT_IN);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $content = $this->getContent();

        if (null !== $content) {
            $obj = ContentQuery::create()->findPk($content);

            if ($obj) {
                $search->filterByContent($obj, Criteria::IN);
            }
        }

        $title = $this->getTitle();

        if (null !== $title) {
            $this->addSearchInI18nColumn($search, 'TITLE', Criteria::LIKE, '%'.$title.'%');
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
                case 'manual_reverse':
                    $search->orderByPosition(Criteria::DESC);
                    break;
                case 'manual':
                    $search->orderByPosition(Criteria::ASC);
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
                case 'created':
                    $search->addAscendingOrderByColumn('created_at');
                    break;
                case 'created_reverse':
                    $search->addDescendingOrderByColumn('created_at');
                    break;
                case 'updated':
                    $search->addAscendingOrderByColumn('updated_at');
                    break;
                case 'updated_reverse':
                    $search->addDescendingOrderByColumn('updated_at');
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        $needCountChild = $this->getNeedCountChild();
        $needContentCount = $this->getNeedContentCount();

        $contentCountVisiblility = $this->getContentCountVisible();

        if ($contentCountVisiblility !== BooleanOrBothType::ANY) {
            $contentCountVisiblility = $contentCountVisiblility ? 1 : 0;
        }

        /** @var \Thelia\Model\Folder $folder */
        foreach ($loopResult->getResultDataCollection() as $folder) {
            $loopResultRow = new LoopResultRow($folder);

            $loopResultRow
                // #doc-out-desc the folder id
                ->set('ID', $folder->getId())
                // #doc-out-desc check if the folder is translated
                ->set('IS_TRANSLATED', $folder->getVirtualColumn('IS_TRANSLATED'))
                // #doc-out-desc The locale used for this research
                ->set('LOCALE', $this->locale)
                // #doc-out-desc the folder title
                ->set('TITLE', $folder->getVirtualColumn('i18n_TITLE'))
                // #doc-out-desc the folder chapo
                ->set('CHAPO', $folder->getVirtualColumn('i18n_CHAPO'))
                // #doc-out-desc the folder description
                ->set('DESCRIPTION', $folder->getVirtualColumn('i18n_DESCRIPTION'))
                // #doc-out-desc the folder postscriptum
                ->set('POSTSCRIPTUM', $folder->getVirtualColumn('i18n_POSTSCRIPTUM'))
                // #doc-out-desc the parent folder
                ->set('PARENT', $folder->getParent())
                // #doc-out-desc Root of this folder
                ->set('ROOT', $folder->getRoot($folder->getId()))
                // #doc-out-desc the folder URL
                ->set('URL', $this->getReturnUrl() ? $folder->getUrl($this->locale) : null)
                // #doc-out-desc the folder meta title
                ->set('META_TITLE', $folder->getVirtualColumn('i18n_META_TITLE'))
                // #doc-out-desc the folder meta description
                ->set('META_DESCRIPTION', $folder->getVirtualColumn('i18n_META_DESCRIPTION'))
                // #doc-out-desc the folder meta keywords
                ->set('META_KEYWORDS', $folder->getVirtualColumn('i18n_META_KEYWORDS'))
                // #doc-out-desc the folder visibility
                ->set('VISIBLE', $folder->getVisible() ? '1' : '0')
                // #doc-out-desc the folder position
                ->set('POSITION', $folder->getPosition());

            if ($needCountChild) {
                // #doc-out-desc Number of subfolders contained by the current forlder.
                $loopResultRow->set('CHILD_COUNT', $folder->countChild());
            }

            if ($needContentCount) {
                // #doc-out-desc the number of visible contents for this folder.
                $loopResultRow->set('CONTENT_COUNT', $folder->countAllContents($contentCountVisiblility));
            }

            $isBackendContext = $this->getBackendContext();

            if ($this->getWithPrevNextInfo()) {
                // Find previous and next folder
                $previousQuery = FolderQuery::create()
                    ->filterByParent($folder->getParent())
                    ->filterByPosition($folder->getPosition(), Criteria::LESS_THAN);

                if (!$isBackendContext) {
                    $previousQuery->filterByVisible(true);
                }

                $previous = $previousQuery
                    ->orderByPosition(Criteria::DESC)
                    ->findOne();

                $nextQuery = FolderQuery::create()
                    ->filterByParent($folder->getParent())
                    ->filterByPosition($folder->getPosition(), Criteria::GREATER_THAN);

                if (!$isBackendContext) {
                    $nextQuery->filterByVisible(true);
                }

                $next = $nextQuery
                    ->orderByPosition(Criteria::ASC)
                    ->findOne();

                $loopResultRow
                    // #doc-out-desc true if a folder exists before this one in the current parent folder, following folders positions.
                    ->set('HAS_PREVIOUS', $previous != null ? 1 : 0)
                    // #doc-out-desc true if a folder exists after this one in the current parent folder, following folders positions.
                    ->set('HAS_NEXT', $next != null ? 1 : 0)
                    // #doc-out-desc The ID of folder before this one in the current parent folder, following folders positions, or null if none exists.
                    ->set('PREVIOUS', $previous != null ? $previous->getId() : -1)
                    // #doc-out-desc The ID of folder after this one in the current parent folder, following folders positions, or null if none exists.
                    ->set('NEXT', $next != null ? $next->getId() : -1);
            }

            $this->addOutputFields($loopResultRow, $folder);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
