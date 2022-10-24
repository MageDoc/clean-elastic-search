<?php

class Clean_ElasticSearch_Model_IndexType_FlatProduct extends Clean_ElasticSearch_Model_IndexType_Product
{
    const CODE = 'flat_product';

    protected function _getIndexTypeCode()
    {
        return static::CODE;
    }

    protected function _getCollection()
    {
        /** @var $products Testimonial_FlatCatalog_Model_Resource_Product_Collection */
        $products = Mage::getResourceModel('flatcatalog/product_collection');
        $products->joinRetailers();

        if (false && Mage::helper('cleanelastic')->isModuleEnabled('MageDoc_DirectoryCatalog')) {
            $directoryCatalog = Mage::getResourceSingleton('directory_catalog/directory');
            $products->getSelect()
                ->joinLeft(
                    array('product_index' => $directoryCatalog->getDirectoryTable('product_index')),
                    'e.entity_id = ' . 'product_index.' . $directoryCatalog->getKeyField('product_index', 'primary'),
                    array('product_index.code_normalized'));
        }
        return $products;
    }

    /**
     * @param $product Mage_Catalog_Model_Product
     * @return \Elastica\Document
     */
    protected function _prepareDocument($product)
    {
        $data = array(
            'id' => $product->getId(),
            'name' => $product->getName(),
            'manufacturer' => $product->getData('manufacturer'),
            //'short_description' => $product->getData('description'),
            //'used_in_cars_short' => $product->getData('short_description'),
            'description'   => $product->getData('description'),
            'product_id' => $product->getData('product_id'),
            'code' => $product->getData('code'),
            'code_normalized' => preg_replace('/[^a-zA-Z0-9]*/', '', $product->getData('code')),
        );

        $document = new \Elastica\Document($data['id'], $data, $this->getIndexTypeCode());
        return $document;
    }

    public function getStoreIndexProperties($store = null)
    {
        return array(
            'name' =>
                array(
                    'type' => 'string',
                    'analyzer' => 'language',
                    'include_in_all' => true,
                    'boost' => 5,
                    'fields' =>
                        array(
                            'std' =>
                                array(
                                    'type' => 'string',
                                    'analyzer' => 'std',
                                ),
                            'prefix' =>
                                array(
                                    'type' => 'string',
                                    'analyzer' => 'text_prefix',
                                    'search_analyzer' => 'std',
                                ),
                            'suffix' =>
                                array(
                                    'type' => 'string',
                                    'analyzer' => 'text_suffix',
                                    'search_analyzer' => 'std',
                                ),
                        ),
                ),
            'manufacturer' =>
                array(
                    'type' => 'string',
                    'analyzer' => 'brand',
                    'include_in_all' => true,
                    'boost' => 2,
                ),
            'description' =>
                array(
                    'type' => 'string',
                    'analyzer' => 'language',
                    'include_in_all' => true,
                    'boost' => 2,
                    'fields' =>
                        array(
                            'std' =>
                                array(
                                    'type' => 'string',
                                    'analyzer' => 'std',
                                ),
                            'prefix' =>
                                array(
                                    'type' => 'string',
                                    'analyzer' => 'text_prefix',
                                    'search_analyzer' => 'std',
                                ),
                            'suffix' =>
                                array(
                                    'type' => 'string',
                                    'analyzer' => 'text_suffix',
                                    'search_analyzer' => 'std',
                                ),
                        ),
                ),
            /*'used_in_cars_short' =>
                array(
                    'type' => 'string',
                    'analyzer' => 'brand',
                    'include_in_all' => true,
                    'boost' => 2,
                ),*/
            /*'weight' =>
                array(
                    'type' => 'string',
                    'analyzer' => 'language',
                    'include_in_all' => true,
                    'boost' => 1,
                    'fields' =>
                        array(
                            'std' =>
                                array(
                                    'type' => 'string',
                                    'analyzer' => 'std',
                                ),
                            'prefix' =>
                                array(
                                    'type' => 'string',
                                    'analyzer' => 'text_prefix',
                                    'search_analyzer' => 'std',
                                ),
                            'suffix' =>
                                array(
                                    'type' => 'string',
                                    'analyzer' => 'text_suffix',
                                    'search_analyzer' => 'std',
                                ),
                        ),
                ),
            'visibility' =>
                array(
                    'type' => 'integer',
                    'ignore_malformed' => true,
                    'index' => 'not_analyzed',
                ),*/
            'code' =>
                array(
                    'type' => 'string',
                    'include_in_all' => true,
                    'boost' => 6,
                    'fields' =>
                        array(
                            'keyword' =>
                                array(
                                    'type' => 'string',
                                    'analyzer' => 'keyword',
                                ),
                            'prefix' =>
                                array(
                                    'type' => 'string',
                                    'analyzer' => 'keyword_prefix',
                                    'search_analyzer' => 'keyword',
                                ),
                            'suffix' =>
                                array(
                                    'type' => 'string',
                                    'analyzer' => 'keyword_suffix',
                                    'search_analyzer' => 'keyword',
                                ),
                        ),
                ),
            'code_normalized' =>
                array(
                    'type' => 'string',
                    'include_in_all' => true,
                    'boost' => 6,
                    'fields' =>
                        array(
                            'keyword' =>
                                array(
                                    'type' => 'string',
                                    'analyzer' => 'keyword',
                                ),
                            'prefix' =>
                                array(
                                    'type' => 'string',
                                    'analyzer' => 'keyword_prefix',
                                    'search_analyzer' => 'keyword',
                                ),
                            'suffix' =>
                                array(
                                    'type' => 'string',
                                    'analyzer' => 'keyword_suffix',
                                    'search_analyzer' => 'keyword',
                                ),
                        ),
                ),
            '_categories' =>
                array(
                    'type' => 'string',
                    'include_in_all' => true,
                    'analyzer' => 'language',
                ),
            '_parent_ids' =>
                array(
                    'type' => 'integer',
                    'store' => true,
                    'index' => 'no',
                ),
            '_url' =>
                array(
                    'type' => 'string',
                    'store' => true,
                    'index' => 'no',
                ),
        );
    }
}