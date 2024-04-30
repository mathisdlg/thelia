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
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\FolderQuery;
use Thelia\Type\BooleanOrBothType;

/**
 * Folder tree loop, to get a folder tree from a given folder to a given depth.
 *
 * - folder is the folder id
 * - depth is the maximum depth to go, default unlimited
 * - visible if true or missing, only visible categories will be displayed. If false, all categories (visible or not) are returned.
 * 
 * #doc-usage {loop type="folder_tree" name="the-loop-name" [argument="value"], [...]}
 * #doc-desc Folder tree loop, to get a folder tree from a given folder to a given depth.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * @method int         getFolder()
 * @method int         getDepth()
 * @method bool|string getVisible()
 * @method int[]       getExclude()
 */
class FolderTree extends BaseI18nLoop implements ArraySearchLoopInterface
{
    /**
     * @return ArgumentCollection
	 * 
	 * #doc-arg-name depth
	 * #doc-arg-desc The max depth
	 * #doc-arg-example example : depth="5"
	 * 
	 * #doc-arg-name exclude
	 * #doc-arg-desc A single or a list of folder ids to exclude for result.
	 * #doc-arg-example exclude="5,72"
	 * 
	 * #doc-arg-name folder *
	 * #doc-arg-desc A single folder id.
	 * #doc-arg-example folder="2"
	 * 
	 * #doc-arg-name visible
	 * #doc-arg-desc Whatever we consider hidden folder or not.
	 * #doc-arg-default true
	 * #doc-arg-example visible="false"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('folder', null, true),
            Argument::createIntTypeArgument('depth', \PHP_INT_MAX),
            Argument::createBooleanOrBothTypeArgument('visible', true, false),
            Argument::createIntListTypeArgument('exclude', [])
        );
    }

    // changement de rubrique
    protected function buildFolderTree($parent, $visible, $level, $maxLevel, $exclude, &$resultsList): void
    {
        if ($level > $maxLevel) {
            return;
        }

        $search = FolderQuery::create();

        $this->configureI18nProcessing($search, [
                    'TITLE',
                ]);

        $search->filterByParent($parent);

        if ($visible != BooleanOrBothType::ANY) {
            $search->filterByVisible($visible);
        }

        if ($exclude != null) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $search->orderByPosition(Criteria::ASC);

        $results = $search->find();

        foreach ($results as $result) {
            $resultsList[] = [
                'ID' => $result->getId(),
                'TITLE' => $result->getVirtualColumn('i18n_TITLE'),
                'PARENT' => $result->getParent(),
                'URL' => $this->getReturnUrl() ? $result->getUrl($this->locale) : null,
                'VISIBLE' => $result->getVisible() ? '1' : '0',
                'LEVEL' => $level,
                'CHILD_COUNT' => $result->countChild(),
            ];

            $this->buildFolderTree($result->getId(), $visible, 1 + $level, $maxLevel, $exclude, $resultsList);
        }
    }

	 /**
	 * 
	 * #doc-out-name $CHILD_COUNT
	 * #doc-out-desc the number of child folders
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the folder id
	 * 
	 * #doc-out-name $LEVEL
	 * #doc-out-desc the folder level
	 * 
	 * #doc-out-name $PARENT
	 * #doc-out-desc the parent folder
	 * 
	 * #doc-out-name $TITLE
	 * #doc-out-desc the folder title
	 * 
	 * #doc-out-name $URL
	 * #doc-out-desc the folder URL
	 * 
	 * #doc-out-name $VISIBLE
	 * #doc-out-desc whatever the folder is visible or not
	 */
    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $result) {
            $loopResultRow = new LoopResultRow($result);
            foreach ($result as $output => $outputValue) {
                $loopResultRow->set($output, $outputValue);
            }

            $this->addOutputFields($loopResultRow, $result);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    public function buildArray()
    {
        $id = $this->getFolder();
        $depth = $this->getDepth();
        $visible = $this->getVisible();
        $exclude = $this->getExclude();

        $resultsList = [];

        $this->buildFolderTree($id, $visible, 0, $depth, $exclude, $resultsList);

        return $resultsList;
    }
}
