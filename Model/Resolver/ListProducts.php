<?php
/**
 * Copyright © Landofcoder All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Lof\ProductListGraphQl\Model\Resolver;

use Lof\ProductListGraphQl\Model\Resolver\Products\Query\ProductQueryInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\Resolver\Value;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Catalog\Model\Layer\Resolver;

/**
 * Class ListProducts
 */
class ListProducts implements ResolverInterface
{

    /**
     * @var ProductQueryInterface
     */
    private $searchQuery;

    /**
     * Random constructor.
     * @param ProductQueryInterface $searchQuery
     */
    public function __construct(
        ProductQueryInterface $searchQuery
    ) {
        $this->searchQuery = $searchQuery;
    }

    /**
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array|Value|mixed
     * @throws GraphQlInputException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if ($args['currentPage'] < 1) {
            throw new GraphQlInputException(__('currentPage value must be greater than 0.'));
        }
        if ($args['pageSize'] < 1) {
            throw new GraphQlInputException(__('pageSize value must be greater than 0.'));
        }
        $args['type'] = $args["sourceType"];
        $searchResult = $this->searchQuery->getResult($args, $info, $context);

        if ($searchResult->getCurrentPage() > $searchResult->getTotalPages() && $searchResult->getTotalCount() > 0) {
            throw new GraphQlInputException(
                __(
                    'currentPage value %1 specified is greater than the %2 page(s) available.',
                    [$searchResult->getCurrentPage(), $searchResult->getTotalPages()]
                )
            );
        }

        $totalPages = $args['pageSize'] ? ((int)ceil($searchResult->getTotalCount() / $args['pageSize'])) : 0;
        $layerType = isset($args['search']) ? Resolver::CATALOG_LAYER_SEARCH : Resolver::CATALOG_LAYER_CATEGORY;
        $data = [
            'total_count' => $searchResult->getTotalCount(),
            'items' => $searchResult->getProductsSearchResult(),
            'page_info' => [
                'page_size' => $args['pageSize'],
                'current_page' => $args['currentPage'],
                'total_pages' => $totalPages
            ],
            'search_result' => $searchResult,
            'layer_type' => $layerType,
            'search' => isset($args['search']) ? $args['search'] : ''
        ];

        if (isset($args['filter']['category_id'])) {
            $data['categories'] = $args['filter']['category_id']['eq'] ?? $args['filter']['category_id']['in'];
            $data['categories'] = is_array($data['categories']) ? $data['categories'] : [$data['categories']];
        }
        return $data;
    }
}
