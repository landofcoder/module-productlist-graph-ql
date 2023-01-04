<?php
declare(strict_types=1);

namespace Lof\ProductListGraphQl\Model\Resolver\Products\DataProvider;

use Laminas\Db\Sql\Ddl\Column\Integer;
use Magento\Catalog\Api\Data\ProductSearchResultsInterfaceFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionPostProcessor;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessorInterface;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\ProductSearch\ProductCollectionSearchCriteriaBuilder;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection\SearchResultApplierFactory;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection\SearchResultApplierInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\GraphQl\Model\Query\ContextInterface;
use Ves\Productlist\Model\ProductFactory as ProductlistProductFactory;

/**
 * Product field data provider, used for GraphQL resolver processing.
 */
class ProductSearch
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var ProductSearchResultsInterfaceFactory
     */
    private $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private $collectionPreProcessor;

    /**
     * @var CollectionPostProcessor
     */
    private $collectionPostProcessor;

    /**
     * @var SearchResultApplierFactory;
     */
    private $searchResultApplierFactory;

    /**
     * @var ProductCollectionSearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var ProductlistProductFactory
     */
    private $productFactory;

    /**
     * @param CollectionFactory $collectionFactory
     * @param ProductSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionPreProcessor
     * @param CollectionPostProcessor $collectionPostProcessor
     * @param SearchResultApplierFactory $searchResultsApplierFactory
     * @param ProductCollectionSearchCriteriaBuilder $searchCriteriaBuilder
     * @param ProductlistProductFactory $productFactory
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        ProductSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionPreProcessor,
        CollectionPostProcessor $collectionPostProcessor,
        SearchResultApplierFactory $searchResultsApplierFactory,
        ProductCollectionSearchCriteriaBuilder $searchCriteriaBuilder,
        ProductlistProductFactory $productFactory
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionPreProcessor = $collectionPreProcessor;
        $this->collectionPostProcessor = $collectionPostProcessor;
        $this->searchResultApplierFactory = $searchResultsApplierFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->productFactory = $productFactory;
    }

    /**
     * Get list of product data with full data set. Adds eav attributes to result set from passed in array
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @param SearchResultInterface $searchResult
     * @param array $attributes
     * @param ContextInterface|null $context
     * @param Int|null $sellerId
     * @param string $source_key
     * @return SearchResultsInterface
     */
    public function getList(
        SearchCriteriaInterface $searchCriteria,
        SearchResultInterface $searchResult,
        array $attributes = [],
        ContextInterface $context = null,
        $source_key = 'latest'
    ): SearchResultsInterface {
        $product = $this->productFactory->create();
        $config = [];
        switch ($source_key) {
            case 'latest':
                $collection = $product->getLatestProducts($config);
                break;
            case 'newArrival':
                $collection = $product->getNewarrivalProducts($config);
                break;
            case 'special':
                $collection = $product->getSpecialProducts($config);
                break;
            case 'mostPopular':
                $collection = $product->getMostViewedProducts($config);
                break;
            case 'bestseller':
                $collection = $product->getBestsellerProducts($config);
                break;
            case 'topRated':
                $collection = $product->getTopratedProducts($config);
                break;
            case 'random':
                $collection = $product->getRandomProducts($config);
                break;
            case 'featured':
                $collection = $product->getFeaturedProducts($config);
                break;
            case 'deals':
                $collection = $product->getDealsProducts($config);
                break;
        }
        $items = [];
        $size = 0;

        if ($collection) {
            //Create a copy of search criteria without filters to preserve the results from search
            $searchCriteriaForCollection = $this->searchCriteriaBuilder->build($searchCriteria);

            //Apply CatalogSearch results from search and join table
            /* $this->getSearchResultsApplier(
                $searchResult,
                $collection,
                $this->getSortOrderArray($searchCriteriaForCollection)
            )->apply(); */
            $collection->setFlag('search_resut_applied', true);
            $this->collectionPreProcessor->process($collection, $searchCriteriaForCollection, $attributes, $context);
            $collection->load();
            $this->collectionPostProcessor->process($collection, $attributes);
            $items = $collection->getItems();
            $size = $collection->getSize();
        }


        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteriaForCollection);
        $searchResults->setItems($items);
        $searchResults->setTotalCount($size);

        return $searchResults;
    }

    /**
     * Create searchResultApplier
     *
     * @param SearchResultInterface $searchResult
     * @param Collection $collection
     * @param array $orders
     * @return SearchResultApplierInterface
     */
    private function getSearchResultsApplier(
        SearchResultInterface $searchResult,
        Collection $collection,
        array $orders
    ): SearchResultApplierInterface {
        return $this->searchResultApplierFactory->create(
            [
                'collection' => $collection,
                'searchResult' => $searchResult,
                'orders' => $orders
            ]
        );
    }

    /**
     * Format sort orders into associative array
     *
     * E.g. ['field1' => 'DESC', 'field2' => 'ASC", ...]
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return array
     */
    private function getSortOrderArray(SearchCriteriaInterface $searchCriteria)
    {
        $ordersArray = [];
        $sortOrders = $searchCriteria->getSortOrders();
        if (is_array($sortOrders)) {
            foreach ($sortOrders as $sortOrder) {
                // I am replacing _id with entity_id because in ElasticSearch _id is required for sorting by ID.
                // Where as entity_id is required when using ID as the sort in $collection->load();.
                // @see \Magento\CatalogGraphQl\Model\Resolver\Products\Query\Search::getResult
                if ($sortOrder->getField() === '_id') {
                    $sortOrder->setField('entity_id');
                }
                $ordersArray[$sortOrder->getField()] = $sortOrder->getDirection();
            }
        }

        return $ordersArray;
    }
}
