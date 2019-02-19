<?php

abstract class Clean_ElasticSearch_Model_IndexType_Abstract extends Varien_Object
{
    const BULK_SIZE = 1000;

    abstract protected function _getIndexTypeCode();
    abstract protected function _getCollection();
    abstract protected function _prepareDocument($item);

    public function index()
    {
        $this->delete();
        $indexType = $this->_getIndexType();

        $collection = $this->_getCollection();
        $bunch = array();
        $i = 0;

        if ($collection instanceof Varien_Data_Collection_Db){
            while ($item = $collection->fetchItem()) {
                $document = $this->_prepareDocument($item);
                $bunch[] = $document;
                if (++$i % self::BULK_SIZE == 0){
                    $indexType->addDocuments($bunch);
                    $bunch = array();
                }
                //$indexType->addDocument($document);
            }
        } else {
            foreach ($collection as $item) {
                $document = $this->_prepareDocument($item);
                $bunch[] = $document;
                if (++$i % self::BULK_SIZE == 0){
                    $indexType->addDocuments($bunch);
                    $bunch = array();
                }
                //$indexType->addDocument($document);
            }
        }
        if ($bunch){
            $indexType->addDocuments($bunch);
        }
    }

    protected function _getIndexType()
    {
        return Mage::getSingleton('cleanelastic/index')->getIndex()->getType($this->_getIndexTypeCode());
    }

    public function delete()
    {
        try {
            return $this->_getIndexType()->delete();
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'TypeMissingException') !== false) {
                // Do nothing
            } elseif ((strpos($e->getMessage(), 'IndexMissingException') !== false)) {
                // Do nothing
            } else {
                throw $e;
            }
        }
    }

    public function updateDocument($model)
    {
        try {
            $document = $this->_prepareDocument($model);
            $this->_getIndexType()->updateDocument($document);
        } catch (Exception $e) {
            Mage::logException($e);
        }

        return $this;
    }

    public function addDocument($model)
    {
        try {
            $document = $this->_prepareDocument($model);
            $this->_getIndexType()->addDocument($document);
        } catch (Exception $e) {
            Mage::logException($e);
        }

        return $this;
    }
}