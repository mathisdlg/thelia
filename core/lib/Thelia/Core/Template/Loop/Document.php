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
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\Event\Document\DocumentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * The document loop.
 * 
 * #doc-desc The document loop process, cache and display products, categories, contents and folders documents.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * @method int[]       getId()
 * @method int[]       getExclude()
 * @method bool|string getVisible()
 * @method int         getLang()
 * @method int         getCategory()
 * @method int         getProduct()
 * @method int         getFolder()
 * @method int         getContent()
 * @method string      getSource()
 * @method int         getSourceId()
 * @method bool        getNewsletter()
 * @method string      getQueryNamespace()
 * @method bool        getWithPrevNextInfo()
 * @method string[]    getOrder()
 */
class Document extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $objectType;
    protected $objectId;

    protected $timestampable = true;

    /**
     * @var array Possible standard document sources
     */
    protected $possible_sources = ['category', 'product', 'folder', 'content', 'brand'];

    /**
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        $collection = new ArgumentCollection(
		    // #doc-arg-desc A single or a list of document ids.
            Argument::createIntListTypeArgument('id'),
		    // #doc-arg-desc A single or a comma-separated list of document IDs to exclude from the list.
            Argument::createIntListTypeArgument('exclude'),
		    // #doc-arg-desc A boolean value.
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            // #doc-arg-desc A list of values
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(['alpha', 'alpha-reverse', 'manual', 'manual-reverse', 'random'])
                ),
                'manual'
            ),
            // #doc-arg-desc The language
            Argument::createIntTypeArgument('lang'),
            // #doc-arg-desc a category identifier. The loop will return this category's documents
            Argument::createIntTypeArgument('category'),
            // #doc-arg-desc a product identifier. The loop will return this product's documents
            Argument::createIntTypeArgument('product'),
            // #doc-arg-desc a folder identifier. The loop will return this folder's documents
            Argument::createIntTypeArgument('folder'),
            // #doc-arg-desc a content identifier. The loop will return this content's documents
            Argument::createIntTypeArgument('content'),
            // #doc-arg-desc see Expected values
            Argument::createAnyTypeArgument('source'),
		    // #doc-arg-desc The identifier of the object provided in the "source" parameter. Only considered if the "source" argument is present
            Argument::createIntTypeArgument('source_id'),
		    // #doc-arg-desc a boolean to define if the return is forced
            Argument::createBooleanTypeArgument('force_return', true),
		    // #doc-arg-desc a namespace
            Argument::createAnyTypeArgument('query_namespace', 'Thelia\\Model'),
		    // #doc-arg-desc A boolean. If set to true, $HAS_PREVIOUS, $HAS_NEXT, $PREVIOUS, and $NEXT output variables are available.
            Argument::createBooleanTypeArgument('with_prev_next_info', false)
        );

        // Add possible document sources
        foreach ($this->possible_sources as $source) {
            $collection->addArgument(Argument::createIntTypeArgument($source));
        }

        return $collection;
    }

    /**
     * Dynamically create the search query, and set the proper filter and order.
     *
     * @param string $source    a valid source identifier (@see $possible_sources)
     * @param int    $object_id the source object ID
     *
     * @return ModelCriteria the propel Query object
     */
    protected function createSearchQuery($source, $object_id)
    {
        $object = ucfirst($source);

        $ns = $this->getQueryNamespace();

        if ('\\' !== $ns[0]) {
            $ns = '\\'.$ns;
        }

        $queryClass = sprintf('%s\\%sDocumentQuery', $ns, $object);
        $filterMethod = sprintf('filterBy%sId', $object);

        // xxxDocumentQuery::create()
        $method = new \ReflectionMethod($queryClass, 'create');
        $search = $method->invoke(null); // Static !

        // $query->filterByXXX(id)
        if (null !== $object_id) {
            $method = new \ReflectionMethod($queryClass, $filterMethod);
            $method->invoke($search, $object_id);
        }

        $orders = $this->getOrder();

        // Results ordering
        foreach ($orders as $order) {
            switch ($order) {
                case 'alpha':
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case 'alpha-reverse':
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case 'manual-reverse':
                    $search->orderByPosition(Criteria::DESC);
                    break;
                case 'manual':
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case 'random':
                    $search->clearOrderByColumns();
                    $search->addAscendingOrderByColumn('RAND()');
                    break 2;
                    break;
            }
        }

        return $search;
    }

    /**
     * Dynamically create the search query, and set the proper filter and order.
     *
     * @param string $object_type (returned) the a valid source identifier (@see $possible_sources)
     * @param string $object_id   (returned) the ID of the source object
     *
     * @return ModelCriteria the propel Query object
     */
    protected function getSearchQuery(&$object_type, &$object_id)
    {
        $search = null;

        // Check form source="product" source_id="123" style arguments
        $source = $this->getSource();

        if (null !== $source) {
            $source_id = $this->getSourceId();
            $id = $this->getId();

            if (null === $source_id && null === $id) {
                throw new \InvalidArgumentException("If 'source' argument is specified, 'id' or 'source_id' argument should be specified");
            }

            $search = $this->createSearchQuery($source, $source_id);

            $object_type = $source;
            $object_id = $source_id;
        } else {
            // Check for product="id" folder="id", etc. style arguments
            foreach ($this->possible_sources as $source) {
                $argValue = (int) $this->getArgValue($source);

                if ($argValue > 0) {
                    $search = $this->createSearchQuery($source, $argValue);

                    $object_type = $source;
                    $object_id = $argValue;

                    break;
                }
            }
        }

        if ($search == null) {
            throw new \InvalidArgumentException(sprintf('Unable to find document source. Valid sources are %s', implode(',', $this->possible_sources)));
        }

        return $search;
    }

    public function buildModelCriteria()
    {
        // Select the proper query to use, and get the object type
        $this->objectType = $this->objectId = null;

        /** @var ProductDocumentQuery $search */
        $search = $this->getSearchQuery($this->objectType, $this->objectId);

        /* manage translations */
        $this->configureI18nProcessing($search);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $exclude = $this->getExclude();
        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $visible = $this->getVisible();
        if ($visible !== BooleanOrBothType::ANY) {
            $search->filterByVisible($visible ? 1 : 0);
        }

        return $search;
    }


    public function parseResults(LoopResult $loopResult)
    {
        $baseSourceFilePath = ConfigQuery::read('documents_library_path');
        if ($baseSourceFilePath === null) {
            $baseSourceFilePath = THELIA_LOCAL_DIR.'media'.DS.'documents';
        } else {
            $baseSourceFilePath = THELIA_ROOT.$baseSourceFilePath;
        }

        /** @var ProductDocument $result */
        foreach ($loopResult->getResultDataCollection() as $result) {
            // Create document processing event
            $event = new DocumentEvent();

            // Put source document file path
            $sourceFilePath = sprintf(
                '%s/%s/%s',
                $baseSourceFilePath,
                $this->objectType,
                $result->getFile()
            );

            $event->setSourceFilepath($sourceFilePath);
            $event->setCacheSubdirectory($this->objectType);

            try {
                // Dispatch document processing event
                $this->dispatcher->dispatch($event, TheliaEvents::DOCUMENT_PROCESS);

                $loopResultRow = new LoopResultRow($result);

                $loopResultRow
		            // #doc-out-desc the document ID
                    ->set('ID', $result->getId())
		            // #doc-out-desc the locale
                    ->set('LOCALE', $this->locale)
		            // #doc-out-desc the document file
                    ->set('DOCUMENT_FILE', $result->getFile())
		            // #doc-out-desc The absolute URL to the generated document
                    ->set('DOCUMENT_URL', $event->getDocumentUrl())
		            // #doc-out-desc The absolute path to the generated document file
                    ->set('DOCUMENT_PATH', $event->getDocumentPath())
		            // #doc-out-desc The absolute path to the original document file
                    ->set('ORIGINAL_DOCUMENT_PATH', $sourceFilePath)
		            // #doc-out-desc the document title
                    ->set('TITLE', $result->getVirtualColumn('i18n_TITLE'))
		            // #doc-out-desc the document chapo
                    ->set('CHAPO', $result->getVirtualColumn('i18n_CHAPO'))
		            // #doc-out-desc the document description
                    ->set('DESCRIPTION', $result->getVirtualColumn('i18n_DESCRIPTION'))
		            // #doc-out-desc the document postscriptum
                    ->set('POSTSCRIPTUM', $result->getVirtualColumn('i18n_POSTSCRIPTUM'))
		            // #doc-out-desc true if the document is visible. False otherwise
                    ->set('VISIBLE', $result->getVisible())
		            // #doc-out-desc the position of this document in the object's document list
                    ->set('POSITION', $result->getPosition())
		            // #doc-out-desc The object type (e.g., produc, category, etc. see 'source' parameter for possible values)
                    ->set('OBJECT_TYPE', $this->objectType)
		            // #doc-out-desc The object ID
                    ->set('OBJECT_ID', $this->objectId)
                ;

                $isBackendContext = $this->getBackendContext();
                if ($this->getWithPrevNextInfo()) {
                    $previousQuery = $this->getSearchQuery($this->objectType, $this->objectId)
                        ->filterByPosition($result->getPosition(), Criteria::LESS_THAN);
                    if (!$isBackendContext) {
                        $previousQuery->filterByVisible(true);
                    }
                    $previous = $previousQuery
                        ->orderByPosition(Criteria::DESC)
                        ->findOne();
                    $nextQuery = $this->getSearchQuery($this->objectType, $this->objectId)
                        ->filterByPosition($result->getPosition(), Criteria::GREATER_THAN);
                    if (!$isBackendContext) {
                        $nextQuery->filterByVisible(true);
                    }
                    $next = $nextQuery
                        ->orderByPosition(Criteria::ASC)
                        ->findOne();
                    $loopResultRow
                        // #doc-out-desc Only available if <strong>with_prev_next_info</strong> parameter is set to true
                        ->set('HAS_PREVIOUS', $previous != null ? 1 : 0)
                        // #doc-out-desc Only available if <strong>with_prev_next_info</strong> parameter is set to true
                        ->set('HAS_NEXT', $next != null ? 1 : 0)
                        // #doc-out-desc Only available if <strong>with_prev_next_info</strong> parameter is set to true
                        ->set('PREVIOUS', $previous != null ? $previous->getId() : -1)
                        // #doc-out-desc Only available if <strong>with_prev_next_info</strong> parameter is set to true
                        ->set('NEXT', $next != null ? $next->getId() : -1);
                }

                $this->addOutputFields($loopResultRow, $result);

                $loopResult->addRow($loopResultRow);
            } catch (\Exception $ex) {
                // Ignore the result and log an error
                Tlog::getInstance()->addError(sprintf('Failed to process document in document loop: %s', $ex->getMessage()));
            }
        }

        return $loopResult;
    }
}
