<?php

abstract class Clean_ElasticSearch_Model_IndexType_Abstract extends Varien_Object
{
    abstract protected function _getIndexTypeCode();
    abstract protected function _getCollection();
    abstract protected function _prepareDocument($item);

    public function index()
    {
        $indexType = $this->_getIndexType();
        $collection = $this->_getCollection();

        foreach ($collection as $item) {
            $document = $this->_prepareDocument($item);
            $indexType->addDocument($document);
        }
    }

    protected function _getIndexType()
    {
        return Mage::getSingleton('cleanelastic/index')->getIndex()->getType($this->_getIndexTypeCode());
    }
}