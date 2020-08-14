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
                //throw $e;
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

    public function getStoreIndexProperties($store = null)
    {
        return array();
    }

    /**
     * Retrieves search fields of specific analyzer if specified
     *
     * @param string $q
     * @param mixed $analyzer
     * @param bool $withBoost
     * @return array
     */
    public function getSearchFields($q, $analyzer = false, $withBoost = true)
    {
        $fields = array();
        foreach ($this->getStoreIndexProperties() as $fieldName => $property) {
            // If field is not searchable, ignore it
            if (!isset($property['include_in_all']) ||
                !$property['include_in_all'] ||
                $property['type'] == 'integer' && !is_int($q))
            {
                continue;
            }

            $boost = 1;
            if ($withBoost && isset($property['boost'])) {
                $boost = intval($property['boost']);
            }

            if (!$analyzer || (isset($property['analyzer']) && $property['analyzer'] == $analyzer)) {
                $fields[] = $fieldName . ($boost > 1 ? '^' . $boost : '');
            }

            if (isset($property['fields'])) {
                foreach ($property['fields'] as $key => $field) {
                    if (!$analyzer || (isset($field['analyzer']) && $field['analyzer'] == $analyzer)) {
                        $fields[] = $fieldName . '.' . $key . ($boost > 1 ? '^' . $boost : '');
                    }
                }
            }
        }

        return $fields;
    }
}