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
use Thelia\Model\Content as ContentModel;
use Thelia\Model\ContentFolderQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\FolderQuery;
use Thelia\Model\Map\ContentTableMap;
use Thelia\Type;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\TypeCollection;

/**
 * Content loop.
 *
 * Class Content
 * 
 * #doc-desc Content loop lists contents from your shop.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]       getId()
 * @method int[]       getFolder()
 * @method int[]       getFolderDefault()
 * @method bool        getCurrent()
 * @method bool        getCurrentFolder()
 * @method bool        getWithPrevNextInfo()
 * @method int         getDepth()
 * @method bool|string getVisible()
 * @method string      getTitle()
 * @method string[]    getOrder()
 * @method int[]       getExclude()
 * @method int[]       getExcludeFolder()
 */
class Content extends BaseI18nLoop implements PropelSearchLoopInterface, SearchLoopInterface
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
		    // #doc-arg-desc A single or a list of content ids.
            Argument::createIntListTypeArgument('id'),
		    // #doc-arg-desc A single or a list of folder ids.
            Argument::createIntListTypeArgument('folder'),
		    // #doc-arg-desc A single or a list of default folder ids allowing to retrieve all content having this parameter as default folder.
            Argument::createIntListTypeArgument('folder_default'),
		    // #doc-arg-desc A boolean value which allows either to exclude current content from results either to match only this content
            Argument::createBooleanTypeArgument('current'),
		    // #doc-arg-desc A boolean value which allows either to exclude current folder contents from results either to match only current folder contents. If a content is in multiple folders whose one is current it will not be excluded if current_folder="false" but will be included if current_folder="yes"
            Argument::createBooleanTypeArgument('current_folder'),
		    // #doc-arg-desc A boolean. If set to true, $PREVIOUS and $NEXT output arguments are available.
            Argument::createBooleanTypeArgument('with_prev_next_info', false),
		    // #doc-arg-desc A positive integer value which precise how many subfolder levels will be browse. Will not be consider if folder parameter is not set.
            Argument::createIntTypeArgument('depth', 1),
		    // #doc-arg-desc A boolean value.
            Argument::createBooleanOrBothTypeArgument('visible', 1),
		    // #doc-arg-desc A title string
            Argument::createAnyTypeArgument('title'),
            // #doc-arg-desc A list of value
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(
                        [
                            'id', 'id_reverse',
                            'alpha', 'alpha-reverse', 'alpha_reverse',
                            'manual', 'manual_reverse',
                            'visible', 'visible_reverse',
                            'random',
                            'given_id',
                            'created', 'created_reverse',
                            'updated', 'updated_reverse',
                            'position', 'position_reverse',
                        ]
                    )
                ),
                'alpha'
            ),
		    // #doc-arg-desc A single or a list of content ids.
            Argument::createIntListTypeArgument('exclude'),
		    // #doc-arg-desc A single or a list of folder ids. If a content is in multiple folders which are not all excluded it will not be excluded.
            Argument::createIntListTypeArgument('exclude_folder')
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
     * @param ContentQuery $search
     * @param string       $searchTerm
     * @param array        $searchIn
     * @param string       $searchCriteria
     */
    public function doSearch(&$search, $searchTerm, $searchIn, $searchCriteria): void
    {
        $search->_and();

        $this->addStandardI18nSearch($search, $searchTerm, $searchCriteria, $searchIn);
    }

    public function buildModelCriteria()
    {
        $search = ContentQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search, ['TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM', 'META_TITLE', 'META_DESCRIPTION', 'META_KEYWORDS']);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $manualOrderAllowed = false;
        if (null !== $folderDefault = $this->getFolderDefault()) {
            // Select the contents which have $folderDefault as the default folder.
            $search
                ->useContentFolderQuery('FolderSelect')
                    ->filterByDefaultFolder(true)
                    ->filterByFolderId($folderDefault, Criteria::IN)
                ->endUse()
            ;

            // We can only sort by position if we have a single folder ID
            $manualOrderAllowed = (1 == \count($folderDefault));
        } elseif (null !== $folderIdList = $this->getFolder()) {
            // Select all content which have one of the required folders as the default one, or an associated one
            $depth = $this->getDepth();

            $allFolderIDs = FolderQuery::getFolderTreeIds($folderIdList, $depth);

            $search
                ->useContentFolderQuery('FolderSelect')
                    ->filterByFolderId($allFolderIDs, Criteria::IN)
                ->endUse()
            ;

            // We can only sort by position if we have a single folder ID, with a depth of 1
            $manualOrderAllowed = (1 == $depth && 1 == \count($folderIdList));
        } else {
            $search
                ->leftJoinContentFolder('FolderSelect')
                ->addJoinCondition('FolderSelect', '`FolderSelect`.DEFAULT_FOLDER = 1')
            ;
        }

        $search->withColumn(
            'CAST(CASE WHEN ISNULL(`FolderSelect`.POSITION) THEN \''.\PHP_INT_MAX.'\' ELSE `FolderSelect`.POSITION END AS SIGNED)',
            'position_delegate'
        );
        $search->withColumn('`FolderSelect`.FOLDER_ID', 'default_folder_id');
        $search->withColumn('`FolderSelect`.DEFAULT_FOLDER', 'is_default_folder');

        $current = $this->getCurrent();

        if ($current === true) {
            $search->filterById($this->getCurrentRequest()->get('content_id'));
        } elseif ($current === false) {
            $search->filterById($this->getCurrentRequest()->get('content_id'), Criteria::NOT_IN);
        }

        $current_folder = $this->getCurrentFolder();

        if ($current_folder === true) {
            $current = ContentQuery::create()->findPk($this->getCurrentRequest()->get('content_id'));

            $search->filterByFolder($current->getFolders(), Criteria::IN);
        } elseif ($current_folder === false) {
            $current = ContentQuery::create()->findPk($this->getCurrentRequest()->get('content_id'));

            $search->filterByFolder($current->getFolders(), Criteria::NOT_IN);
        }

        $visible = $this->getVisible();

        if ($visible !== BooleanOrBothType::ANY) {
            $search->filterByVisible($visible ? 1 : 0);
        }

        $title = $this->getTitle();

        if (null !== $title) {
            $this->addSearchInI18nColumn($search, 'TITLE', Criteria::LIKE, '%'.$title.'%');
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $exclude_folder = $this->getExcludeFolder();

        if (null !== $exclude_folder) {
            $search->filterByFolder(
                FolderQuery::create()->filterById($exclude_folder, Criteria::IN)->find(),
                Criteria::NOT_IN
            );
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
                case 'alpha-reverse':
                case 'alpha_reverse':
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case 'manual':
                    if (!$manualOrderAllowed) {
                        throw new \InvalidArgumentException('Manual order cannot be set without single folder argument');
                    }
                    $search->addAscendingOrderByColumn('position_delegate');
                    break;
                case 'manual_reverse':
                    if (!$manualOrderAllowed) {
                        throw new \InvalidArgumentException('Manual order cannot be set without single folder argument');
                    }
                    $search->addDescendingOrderByColumn('position_delegate');
                    break;
                case 'given_id':
                    if (null === $id) {
                        throw new \InvalidArgumentException('Given_id order cannot be set without `id` argument');
                    }
                    foreach ($id as $singleId) {
                        $givenIdMatched = 'given_id_matched_'.$singleId;
                        $search->withColumn(ContentTableMap::COL_ID."='$singleId'", $givenIdMatched);
                        $search->orderBy($givenIdMatched, Criteria::DESC);
                    }
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
                case 'position':
                    $search->addAscendingOrderByColumn('position_delegate');
                    break;
                case 'position_reverse':
                    $search->addDescendingOrderByColumn('position_delegate');
                    break;
            }
        }

        $search->groupById();

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var ContentModel $content */
        foreach ($loopResult->getResultDataCollection() as $content) {
            $loopResultRow = new LoopResultRow($content);

            if ((bool) $content->getVirtualColumn('is_default_folder')) {
                $defaultFolderId = $content->getVirtualColumn('default_folder_id');
            } else {
                $defaultFolderId = $content->getDefaultFolderId();
            }

		    // #doc-out-desc the content id
            $loopResultRow->set('ID', $content->getId())
		        // #doc-out-desc check if the content is translated
                ->set('IS_TRANSLATED', $content->getVirtualColumn('IS_TRANSLATED'))
		        // #doc-out-desc The locale used for this research
                ->set('LOCALE', $this->locale)
		        // #doc-out-desc the content title
                ->set('TITLE', $content->getVirtualColumn('i18n_TITLE'))
		        // #doc-out-desc the content chapo
                ->set('CHAPO', $content->getVirtualColumn('i18n_CHAPO'))
		        // #doc-out-desc the content description
                ->set('DESCRIPTION', $content->getVirtualColumn('i18n_DESCRIPTION'))
		        // #doc-out-desc the content postscriptum
                ->set('POSTSCRIPTUM', $content->getVirtualColumn('i18n_POSTSCRIPTUM'))
		        // #doc-out-desc the content URL
                ->set('URL', $this->getReturnUrl() ? $content->getUrl($this->locale) : null)
		        // #doc-out-desc the content meta title
                ->set('META_TITLE', $content->getVirtualColumn('i18n_META_TITLE'))
		        // #doc-out-desc the content meta description
                ->set('META_DESCRIPTION', $content->getVirtualColumn('i18n_META_DESCRIPTION'))
		        // #doc-out-desc the content meta keywords
                ->set('META_KEYWORDS', $content->getVirtualColumn('i18n_META_KEYWORDS'))
		        // #doc-out-desc the content position
                ->set('POSITION', $content->getVirtualColumn('position_delegate'))
		        // #doc-out-desc the default folder id for the current content
                ->set('DEFAULT_FOLDER', $defaultFolderId)
                ->set('VISIBLE', $content->getVisible());
            $this->addOutputFields($loopResultRow, $content);

            $this->findNextPrev($loopResultRow, $content, $defaultFolderId);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    /**
     * @param int $defaultFolderId
     */
    private function findNextPrev(LoopResultRow $loopResultRow, ContentModel $content, $defaultFolderId): void
    {
        if ($this->getWithPrevNextInfo()) {
            $contentFolder = ContentFolderQuery::create()
                ->filterByFolderId($defaultFolderId)
                ->filterByContentId($content->getId())
                ->findOne();

            $currentPosition = $contentFolder !== null ? $contentFolder->getPosition() : 0;

            // Find previous and next content
            $previousQuery = ContentFolderQuery::create()
                ->filterByFolderId($defaultFolderId)
                ->filterByPosition($currentPosition, Criteria::LESS_THAN);

            $nextQuery = ContentFolderQuery::create()
                ->filterByFolderId($defaultFolderId)
                ->filterByPosition($currentPosition, Criteria::GREATER_THAN);

            if (!$this->getBackendContext()) {
                $previousQuery->useContentQuery()
                    ->filterByVisible(true)
                    ->endUse();

                $previousQuery->useContentQuery()
                    ->filterByVisible(true)
                    ->endUse();
            }

            $previous = $previousQuery
                ->orderByPosition(Criteria::DESC)
                ->findOne();

            $next = $nextQuery
                ->orderByPosition(Criteria::ASC)
                ->findOne();

            $loopResultRow
		// #doc-out-desc true if a content exists before this one in the current folder, following contents positions.
                ->set('HAS_PREVIOUS', $previous != null ? 1 : 0)
		// #doc-out-desc true if a content exists after this one in the current folder, following contents positions.
                ->set('HAS_NEXT', $next != null ? 1 : 0)
		// #doc-out-desc The ID of content before this one in the current folder, following contents positions, or null if none exists.
                ->set('PREVIOUS', $previous != null ? $previous->getContentId() : -1)
		// #doc-out-desc The ID of content after this one in the current folder, following contents positions, or null if none exists.
                ->set('NEXT', $next != null ? $next->getContentId() : -1);
        }
    }
}
