# Mage2 Module Lof ProductListGraphQl

    ``landofcoder/module-productlist-graph-ql``

 - [Main Functionalities](#markdown-header-main-functionalities)
 - [Installation](#markdown-header-installation)
 - [Configuration](#markdown-header-configuration)
 - [Specifications](#markdown-header-specifications)
 - [Attributes](#markdown-header-attributes)
 
### Requirement
- https://github.com/venustheme/magento2-product-list

## Main Functionalities
magento 2 product list graphql extension

## Installation
\* = in production please use the `--keep-generated` option

### Type 1: Zip file

 - Unzip the zip file in `app/code/Lof`
 - Enable the module by running `php bin/magento module:enable Lof_ProductListGraphQl`
 - Apply database updates by running `php bin/magento setup:upgrade`\*
 - Flush the cache by running `php bin/magento cache:flush`

### Type 2: Composer

 - Make the module available in a composer repository for example:
    - private repository `repo.magento.com`
    - public repository `packagist.org`
    - public github repository as vcs
 - Add the composer repository to the configuration by running `composer config repositories.repo.magento.com composer https://repo.magento.com/`
 - Install the module composer by running `composer require landofcoder/module-productlist-graph-ql`
 - enable the module by running `php bin/magento module:enable Lof_ProductListGraphQl`
 - apply database updates by running `php bin/magento setup:upgrade`\*
 - Flush the cache by running `php bin/magento cache:flush`


## TODO
- Support full-text search

## Queries

Get products list:

- Define Fragments before:

```
fragment ProductPriceFragment on ProductPrice {
  discount {
    amount_off
    percent_off
  }
  final_price {
    currency
    value
  }
  regular_price {
    currency
    value
  }
}

fragment ProductBasicInfo on ProductInterface {
  id
  name
  url_key
  rating_summary
  sku
  image {
    url
    label
  }
  description {
    html
  }
  short_description {
    html
  }
  price_range {
    maximum_price {
      ...ProductPriceFragment
    }
    minimum_price {
      ...ProductPriceFragment
    }
  }
  price {
      regularPrice {
          amount {
              currency
          }
      }
  }
}
```

- Use Query:

```
{
    lofProductsList(
        sourceType: LofProductSourceType = latest
        search: String
        filter: ProductFilterInput
        pageSize: Int = 20
        currentPage: Int = 1
    ) {
        items {
            ...ProductBasicInfo
        }
        page_info {
          page_size
          current_page
          total_pages
        }
        total_count
    }
}
```

- LofProductSourceType: enum type

```
enum LofProductSourceType {
    latest
    newArrival
    special
    mostPopular
    bestseller
    topRated
    random
    featured
    deals
}
```

- ProductFilterInput: is deprecated, use @ProductAttributeFilterInput instead. ProductFilterInput defines the filters to be used in the search. A filter contains at least one attribute, a comparison operator, and the value that is being searched for.
view in module ``magento/module-catalog-graph-ql``

Example:

query featured products

```
{
    lofProductsList(
        sourceType: featured
        search: ""
        filter: {}
        pageSize: 5
        currentPage: 1
    ) {
        items {
            id
            name
            url_key
            rating_summary
            sku
            image {
              url
              label
            }
            description {
              html
            }
            short_description {
              html
            }
            price {
                regularPrice {
                    amount {
                        currency
                    }
                }
            }
        }
        page_info {
          page_size
          current_page
          total_pages
        }
        total_count
    }
}

```
