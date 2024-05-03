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

use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\FolderQuery;
use Thelia\Type\BooleanOrBothType;

/**
 * Class FolderPath.
 * 
 * #doc-desc Folder path loop provides the path through the catalog to a given folder. For example if we have an "alpha" folder standing in an "alpha_father" folder which itseflf belong to "root" folder. Folder path loop for folder "alpha" will return "root" then "alpha_father" then "alpha".
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 *
 * @method int         getFolder()
 * @method bool|string getVisible()
 * @method int         getDepth()
 * @method string[]    getOrder()
 */
class FolderPath extends BaseI18nLoop implements ArraySearchLoopInterface
{
    /**
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection
	 * 
	 * #doc-arg-name depth
	 * #doc-arg-desc The max depth
	 * #doc-arg-example example : depth="5"
	 * 
	 * #doc-arg-name folder *
	 * #doc-arg-desc A single folder id.
	 * #doc-arg-example folder="2"
	 * 
	 * #doc-arg-name visible
	 * #doc-arg-desc Whatever we consider hidden folder or not.
	 * #doc-arg-example visible="false"
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('folder', null, true),
            Argument::createIntTypeArgument('depth', \PHP_INT_MAX),
            Argument::createBooleanOrBothTypeArgument('visible', true, false)
        );
    }

    public function buildArray()
    {
        $originalId = $currentId = $this->getFolder();
        $visible = $this->getVisible();
        $depth = $this->getDepth();

        $results = [];

        $ids = [];

        do {
            $search = FolderQuery::create();

            $this->configureI18nProcessing($search, ['TITLE']);

            $search->filterById($currentId);

            if ($visible !== BooleanOrBothType::ANY) {
                $search->filterByVisible($visible);
            }

            $folder = $search->findOne();

            if ($folder != null) {
                $results[] = [
                    'ID' => $folder->getId(),
                    'TITLE' => $folder->getVirtualColumn('i18n_TITLE'),
                    'URL' => $folder->getUrl($this->locale),
                    'LOCALE' => $this->locale,
                ];

                $currentId = $folder->getParent();

                if ($currentId > 0) {
                    // Prevent circular refererences
                    if (\in_array($currentId, $ids)) {
                        throw new \LogicException(
                            sprintf(
                                'Circular reference detected in folder ID=%d hierarchy (folder ID=%d appears more than one times in path)',
                                $originalId,
                                $currentId
                            )
                        );
                    }

                    $ids[] = $currentId;
                }
            }
        } while ($folder != null && $currentId > 0 && --$depth > 0);

        // Reverse list and build the final result
        return array_reverse($results);
    }

	 /**
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the folder id
	 * 
	 * #doc-out-name $LOCALE
	 * #doc-out-desc the locale
	 * 
	 * #doc-out-name $TITLE
	 * #doc-out-desc the folder title
	 * 
	 * #doc-out-name $URL
	 * #doc-out-desc the folder URL
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
}
