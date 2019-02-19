<?php

class Clean_ElasticSearch_Model_IndexType_Product extends Clean_ElasticSearch_Model_IndexType_Abstract
{
    protected function _getIndexTypeCode()
    {
        return 'product';
    }

    protected function _getCollection()
    {
        $products = Mage::getResourceModel('catalog/product_collection')
            ->joinAttribute('name', 'catalog_product/name', 'entity_id')
            ->joinAttribute('short_description', 'catalog_product/short_description', 'entity_id', null, $joinType='left');
            //->joinAttribute('description', 'catalog_product/description', 'entity_id', null, $joinType='left');
            //->addAttributeToSelect('name')
            //->addAttributeToSelect('description');

        if (false && Mage::helper('cleanelastic')->isModuleEnabled('MageDoc_DirectoryCatalog')){
            $directoryCatalog = Mage::getResourceSingleton('directory_catalog/directory');
            $products->getSelect()
                ->joinLeft(
                    array('product_index' => $directoryCatalog->getDirectoryTable('product_index')),
                    'e.entity_id = '.'product_index.' . $directoryCatalog->getKeyField('product_index', 'primary'),
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
            'id'            => $product->getId(),
            'name'          => $product->getName(),
            'short_description' => $product->getData('short_description'),
            //'description'   => $product->getData('description'),
            'product_id'     => $product->getData('entity_id'),
            'code'           => $product->getData('code'),
            'code_normalized'=> preg_replace('/[^a-zA-Z0-9]*/', '',$product->getData('code')),
        );

        $document = new \Elastica\Document($data['id'], $data, $this->_getIndexTypeCode());
        return $document;
    }
}