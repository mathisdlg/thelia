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
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Model\ProductImage;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\EnumListType;
use Thelia\Type\EnumType;
use Thelia\Type\TypeCollection;

/**
 * The image loop.
 * 
 * #doc-usage {loop type="image" name="the-loop-name" [argument="value"], [...]}
 * #doc-desc The image loop process, cache and display images, categories, contents and folders images.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * @method int[]       getId()
 * @method bool|string getVisible()
 * @method int[]       getExclude()
 * @method int         getWidth()
 * @method int         getHeight()
 * @method int         getRotation()
 * @method string      getBackgroundColor()
 * @method int         getQuality()
 * @method string      getEffects()
 * @method int         getCategory()
 * @method int         getProduct()
 * @method int         getFolder()
 * @method int         getContent()
 * @method string      getSource()
 * @method int         getSourceId()
 * @method string      getQueryNamespace()
 * @method bool        getAllowZoom()
 * @method bool        getIgnoreProcessingErrors()
 * @method string      getResizeMode()
 * @method bool        getBase64()
 * @method bool        getWithPrevNextInfo()
 * @method string      getFormat()
 * @method string[]    getOrder()
 */
class Image extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $objectType;
    protected $objectId;

    protected $timestampable = true;

    /**
     * @var array Possible standard image sources
     */
    protected $possible_sources = ['category', 'product', 'folder', 'content', 'module', 'brand'];

    /**
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection
	 * 
	 * #doc-arg-name allow_zoom
	 * #doc-arg-desc If true, the loop il allowed to resize an image to match the required width and height, causing, in most cases, a quality loss. If false, the image will never be zoomed. Default is false.
	 * #doc-arg-example allow_zoom="true"
	 * 
	 * #doc-arg-name background_color
	 * #doc-arg-desc The color applied to empty image parts during processing. Use rgb or rrggbb color format
	 * #doc-arg-example background_color="cc8000" or background_color="fff"
	 * 
	 * #doc-arg-name base64
	 * #doc-arg-desc If true, the loop will have a new output with the image in base64
	 * #doc-arg-example base64="true"
	 * 
	 * #doc-arg-name brand **
	 * #doc-arg-desc a brand identifier. The loop will return this brand's images
	 * #doc-arg-example brand="2"
	 * 
	 * #doc-arg-name category **
	 * #doc-arg-desc a category identifier. The loop will return this category's images
	 * #doc-arg-example category="2"
	 * 
	 * #doc-arg-name content **
	 * #doc-arg-desc a content identifier. The loop will return this content's images
	 * #doc-arg-example content="2"
	 * 
	 * #doc-arg-name effects
	 * #doc-arg-desc One or more comma separated effects definitions, that will be applied to the image in the specified order. Please see below a detailed description of available effects <br/> Expected values
	 * #doc-arg-example effects="greyscale,gamma:0.7,vflip"
	 * 
	 * #doc-arg-name exclude
	 * #doc-arg-desc A single or a comma-separated list of image IDs to exclude from the list.
	 * #doc-arg-example exclude="456,123"
	 * 
	 * #doc-arg-name folder **
	 * #doc-arg-desc a folder identifier. The loop will return this folder's images
	 * #doc-arg-example folder="2"
	 * 
	 * #doc-arg-name format
	 * #doc-arg-desc The format of the image.
	 * #doc-arg-example format="png"
	 * 
	 * #doc-arg-name height
	 * #doc-arg-desc A height in pixels, for resizing image. If only the height is provided, the image ratio is preserved.
	 * #doc-arg-example height="200"
	 * 
	 * #doc-arg-name id
	 * #doc-arg-desc A single or a list of image ids.
	 * #doc-arg-example id="2", id="1,4,7"
	 * 
	 * #doc-arg-name ignore_processing_errors
	 * #doc-arg-desc If true, the loop will return a result even if the image processing fails, and set the PROCESSING_ERROR variable to true if an error occurs. If false, images for which the processing fails are not returned.
	 * #doc-arg-example ignore_processing_errors="false"
	 * 
	 * #doc-arg-name order
	 * #doc-arg-desc A list of values see sorting possible values
	 * #doc-arg-default manual
	 * #doc-arg-example order="alpha_reverse"
	 * 
	 * #doc-arg-name product **
	 * #doc-arg-desc a product identifier. The loop will return this product's images
	 * #doc-arg-example product="2"
	 * 
	 * #doc-arg-name quality
	 * #doc-arg-desc The generated image quality, from 0(!) to 100%. The default value is 75% (you can hange this in the Administration panel)
	 * #doc-arg-example quality="70"
	 * 
	 * #doc-arg-name query_namespace
	 * #doc-arg-desc The namespace
	 * #doc-arg-default Thelia\Model
	 * #doc-arg-example query_namespace="Thelia\Model"
	 * 
	 * #doc-arg-name resize_mode
	 * #doc-arg-desc If 'crop', the image will have the exact specified width and height, and will be cropped if required. If the source image is smaller than the required width and/or height, you have to set allow_zoom to true, otherwise the generated image will be smaller than required. If 'borders', the image will have the exact specified width and height, and some borders may be added. The border color is the one specified by 'background_color'. If 'none' or missing, the image ratio is preserved, and depending od this ratio, may not have the exact width and height required.
	 * #doc-arg-default none
	 * #doc-arg-example resize_mode="crop"
	 * 
	 * #doc-arg-name rotation
	 * #doc-arg-desc The rotation angle in degrees (positive or negative) applied to the image. The background color of the empty areas is the one specified by 'background_color'
	 * #doc-arg-example rotation="90"
	 * 
	 * #doc-arg-name source **
	 * #doc-arg-desc see Expected values
	 * #doc-arg-example source="category"
	 * 
	 * #doc-arg-name source_id
	 * #doc-arg-desc The identifier of the object provided in the "source" parameter. Only considered if the "source" argument is present
	 * #doc-arg-example source_id="2"
	 * 
	 * #doc-arg-name visible
	 * #doc-arg-desc A boolean value.
	 * #doc-arg-default yes
	 * #doc-arg-example visible="no"
	 * 
	 * #doc-arg-name width
	 * #doc-arg-desc A width in pixels, for resizing image. If only the width is provided, the image ratio is preserved.
	 * #doc-arg-example width="200"
	 * 
	 * #doc-arg-name with_prev_next_info
	 * #doc-arg-desc 
	 * #doc-arg-example 
     */
    protected function getArgDefinitions()
    {
        $collection = new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(['alpha', 'alpha-reverse', 'manual', 'manual-reverse', 'random'])
                ),
                'manual'
            ),
            Argument::createIntTypeArgument('width'),
            Argument::createIntTypeArgument('height'),
            Argument::createIntTypeArgument('rotation', 0),
            Argument::createAnyTypeArgument('background_color'),
            Argument::createIntTypeArgument('quality'),
            new Argument(
                'resize_mode',
                new TypeCollection(
                    new EnumType(['crop', 'borders', 'none'])
                ),
                'none'
            ),
            Argument::createAnyTypeArgument('effects'),
            Argument::createIntTypeArgument('category'),
            Argument::createIntTypeArgument('product'),
            Argument::createIntTypeArgument('folder'),
            Argument::createIntTypeArgument('content'),
            Argument::createAnyTypeArgument('source'),
            Argument::createIntTypeArgument('source_id'),
            Argument::createBooleanTypeArgument('force_return', true),
            Argument::createBooleanTypeArgument('ignore_processing_errors', true),
            Argument::createAnyTypeArgument('query_namespace', 'Thelia\\Model'),
            Argument::createBooleanTypeArgument('allow_zoom', false),
            Argument::createBooleanTypeArgument('base64', false),
            Argument::createBooleanTypeArgument('with_prev_next_info', false),
            Argument::createAnyTypeArgument('format')
        );

        // Add possible image sources
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

        $queryClass = sprintf('%s\\%sImageQuery', $ns, $object);
        $filterMethod = sprintf('filterBy%sId', $object);

        // xxxImageQuery::create()
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
     * @param string $objectType (returned) the a valid source identifier (@see $possible_sources)
     * @param string $objectId   (returned) the ID of the source object
     *
     * @return ModelCriteria the propel Query object
     */
    protected function getSearchQuery(&$objectType, &$objectId)
    {
        $search = null;

        // Check form source="product" source_id="123" style arguments
        $source = $this->getSource();

        if (null !== $source) {
            $sourceId = $this->getSourceId();
            $id = $this->getId();

            if (null === $sourceId && null === $id) {
                throw new \InvalidArgumentException(
                    "If 'source' argument is specified, 'id' or 'source_id' argument should be specified"
                );
            }

            $search = $this->createSearchQuery($source, $sourceId);

            $objectType = $source;
            $objectId = $sourceId;
        } else {
            // Check for product="id" folder="id", etc. style arguments
            foreach ($this->possible_sources as $source) {
                $argValue = $this->getArgValue($source);

                if (!empty($argValue)) {
                    $argValue = (int) $argValue;

                    $search = $this->createSearchQuery($source, $argValue);

                    $objectType = $source;
                    $objectId = $argValue;

                    break;
                }
            }
        }

        if ($search == null) {
            throw new \InvalidArgumentException(
                sprintf('Unable to find image source. Valid sources are %s', implode(',', $this->possible_sources))
            );
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

	 /**
	 * 
	 * #doc-out-name $CHAPO
	 * #doc-out-desc the image chapo
	 * 
	 * #doc-out-name $DESCRIPTION
	 * #doc-out-desc the image description
	 * 
	 * #doc-out-name $HAS_NEXT
	 * #doc-out-desc If the loop has a next image
	 * 
	 * #doc-out-name $HAS_PREVIOUS
	 * #doc-out-desc If the loop has a previous image
	 * 
	 * #doc-out-name $ID
	 * #doc-out-desc the image ID
	 * 
	 * #doc-out-name $IMAGE_BASE64
	 * #doc-out-desc only available if base64 param is set to true
	 * 
	 * #doc-out-name $IMAGE_HEIGHT
	 * #doc-out-desc the image height
	 * 
	 * #doc-out-name $IMAGE_PATH
	 * #doc-out-desc The absolute path to the generated image file
	 * 
	 * #doc-out-name $IMAGE_URL
	 * #doc-out-desc The absolute URL to the generated image.
	 * 
	 * #doc-out-name $IMAGE_WIDTH
	 * #doc-out-desc the image width
	 * 
	 * #doc-out-name $IS_SVG
	 * #doc-out-desc true if the image is an SVG image
	 * 
	 * #doc-out-name $LOCALE
	 * #doc-out-desc The locale used for this research
	 * 
	 * #doc-out-name $NEXT
	 * #doc-out-desc The next image ID
	 * 
	 * #doc-out-name $OBJECT_ID
	 * #doc-out-desc The object ID
	 * 
	 * #doc-out-name $OBJECT_TYPE
	 * #doc-out-desc The object type (e.g., produc, category, etc. see 'source' parameter for possible values)
	 * 
	 * #doc-out-name $ORIGINAL_IMAGE_PATH
	 * #doc-out-desc The absolute path to the original image file
	 * 
	 * #doc-out-name $ORIGINAL_IMAGE_URL
	 * #doc-out-desc The absolute URL to the original image
	 * 
	 * #doc-out-name $POSITION
	 * #doc-out-desc the position of this image in the object's image list
	 * 
	 * #doc-out-name $POSTSCRIPTUM
	 * #doc-out-desc the image postscriptum
	 * 
	 * #doc-out-name $PREVIOUS
	 * #doc-out-desc The previous image ID
	 * 
	 * #doc-out-name $PROCESSING_ERROR
	 * #doc-out-desc true if the image processing fails. In this case, $IMAGE_URL, $ORIGINAL_IMAGE_URL, and $IMAGE_PATH will be empty.
	 * 
	 * #doc-out-name $TITLE
	 * #doc-out-desc the image title
	 * 
	 * #doc-out-name $VISIBLE
	 * #doc-out-desc true if the image is visible. False otherwise
	 */
    public function parseResults(LoopResult $loopResult)
    {
        // Create image processing event
        $event = new ImageEvent();

        // Prepare tranformations
        $width = $this->getWidth();
        $height = $this->getHeight();
        $rotation = $this->getRotation();
        $background_color = $this->getBackgroundColor();
        $quality = $this->getQuality();
        $effects = $this->getEffects();
        $format = $this->getFormat();

        $event->setAllowZoom($this->getAllowZoom());

        if (null !== $effects) {
            $effects = explode(',', $effects);
        }

        switch ($this->getResizeMode()) {
            case 'crop':
                $resizeMode = \Thelia\Action\Image::EXACT_RATIO_WITH_CROP;
                break;
            case 'borders':
                $resizeMode = \Thelia\Action\Image::EXACT_RATIO_WITH_BORDERS;
                break;
            case 'none':
            default:
                $resizeMode = \Thelia\Action\Image::KEEP_IMAGE_RATIO;
        }

        $baseSourceFilePath = ConfigQuery::read('images_library_path');
        if ($baseSourceFilePath === null) {
            $baseSourceFilePath = THELIA_LOCAL_DIR.'media'.DS.'images';
        } else {
            $baseSourceFilePath = THELIA_ROOT.$baseSourceFilePath;
        }

        /** @var ProductImage $result */
        foreach ($loopResult->getResultDataCollection() as $result) {
            // Setup required transformations
            if (null !== $width) {
                $event->setWidth($width);
            }
            if (null !== $height) {
                $event->setHeight($height);
            }
            $event->setResizeMode($resizeMode);
            if (null !== $rotation) {
                $event->setRotation($rotation);
            }
            if (null !== $background_color) {
                $event->setBackgroundColor($background_color);
            }
            if (null !== $quality) {
                $event->setQuality($quality);
            }
            if (null !== $effects) {
                $event->setEffects($effects);
            }
            if (null !== $format) {
                $event->setFormat($format);
            }

            // Put source image file path
            $sourceFilePath = sprintf(
                '%s/%s/%s',
                $baseSourceFilePath,
                $this->objectType,
                $result->getFile()
            );

            $event->setSourceFilepath($sourceFilePath);
            $event->setCacheSubdirectory($this->objectType);

            $loopResultRow = new LoopResultRow($result);

            $loopResultRow
                ->set('ID', $result->getId())
                ->set('LOCALE', $this->locale)
                ->set('ORIGINAL_IMAGE_PATH', $sourceFilePath)
                ->set('TITLE', $result->getVirtualColumn('i18n_TITLE'))
                ->set('CHAPO', $result->getVirtualColumn('i18n_CHAPO'))
                ->set('DESCRIPTION', $result->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('POSTSCRIPTUM', $result->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set('VISIBLE', $result->getVisible())
                ->set('POSITION', $result->getPosition())
                ->set('OBJECT_TYPE', $this->objectType)
                ->set('OBJECT_ID', $this->objectId)
            ;

            $addRow = true;

            $returnErroredImages = $this->getBackendContext() || !$this->getIgnoreProcessingErrors();

            try {
                // Dispatch image processing event
                $this->dispatcher->dispatch($event, TheliaEvents::IMAGE_PROCESS);

                $imageExt = pathinfo($event->getSourceFilepath(), \PATHINFO_EXTENSION);

                $loopResultRow
                    ->set('IMAGE_URL', $event->getFileUrl())
                    ->set('ORIGINAL_IMAGE_URL', $event->getOriginalFileUrl())
                    ->set('IMAGE_PATH', $event->getCacheFilepath())
                    ->set('PROCESSING_ERROR', false)
                    ->set('IS_SVG', 'svg' === $imageExt)
                    ->set('IMAGE_HEIGHT', $event->getImageObject()->getSize()->getHeight())
                    ->set('IMAGE_WIDTH', $event->getImageObject()->getSize()->getWidth())
                ;
                if ($this->getBase64()) {
                    $loopResultRow->set('IMAGE_BASE64', $this->toBase64($event->getCacheFilepath()));
                }
            } catch (\Exception $ex) {
                // Ignore the result and log an error
                Tlog::getInstance()->addError(sprintf('Failed to process image in image loop: %s', $ex->getMessage()));

                if ($returnErroredImages) {
                    $loopResultRow
                        ->set('IMAGE_URL', '')
                        ->set('ORIGINAL_IMAGE_URL', '')
                        ->set('IMAGE_PATH', '')
                        ->set('PROCESSING_ERROR', true)
                        ->set('IMAGE_HEIGHT', '')
                        ->set('IMAGE_WIDTH', '')
                    ;
                } else {
                    $addRow = false;
                }
            }
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
                    ->set('HAS_PREVIOUS', $previous != null ? 1 : 0)
                    ->set('HAS_NEXT', $next != null ? 1 : 0)
                    ->set('PREVIOUS', $previous != null ? $previous->getId() : -1)
                    ->set('NEXT', $next != null ? $next->getId() : -1);
            }

            if ($addRow) {
                $this->addOutputFields($loopResultRow, $result);

                $loopResult->addRow($loopResultRow);
            }
        }

        return $loopResult;
    }

    private function toBase64($path)
    {
        $imgData = base64_encode(file_get_contents($path));

        return $src = 'data: '.mime_content_type($path).';base64,'.$imgData;
    }
}
