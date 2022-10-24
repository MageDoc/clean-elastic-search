<?php

class Clean_ElasticSearch_Model_Index extends Varien_Object
{
    /**
     * @var Clean_ElasticSearch_Helper_Data
     */
    protected $_helper;

    /**
     * @var Elastica\Index
     */
    protected $_index;

    public function _construct()
    {
        $this->_helper = Mage::helper('cleanelastic');

        parent::_construct();
    }

    /**
     * @return \Elastica\Client
     */
    public function getClient()
    {
        if (isset($this->_client)) {
            return $this->_client;

        }

        $elasticaClient = new \Elastica\Client(array(
            'host' => Mage::helper('cleanelastic')->getHost(),
            'port' => Mage::helper('cleanelastic')->getPort(),
        ));

        $this->_client = $elasticaClient;
        return $this->_client;
    }

    /**
     * @return \Elastica\Index
     */
    public function getIndex()
    {
        if (!isset($this->_index)) {
            $this->_index = $this->_initIndex();
        }

        return $this->_index;
    }

    public function _initIndex($new = false)
    {
        $store = Mage::app()->getStore();
        $name = Mage::helper('cleanelastic')->getIndexName();
        $index = $this->getClient()->getIndex($name);

        // If index doesn't exist, create it with store settings
        if (!$this->indexExists($name)) {
            $index->create(array('settings' => $this->_helper->getStoreIndexSettings($store)));

            // Send index mapping if not yet defined
            foreach ($this->_helper->getStoreTypes($store) as $indexerType) {
                $indexer = $this->getIndexer($indexerType);
                $type = $indexer->getIndexTypeCode();
                if ($index->getType($type)->exists()) {
                    continue;
                }
                $mapping = new \Elastica\Type\Mapping();
                $mapping->setType($index->getType($type));
                if (!$this->isSourceEnabled($store)) {
                    $mapping->disableSource();
                }

                // Hanle boost at query time
                $properties = $indexer->getStoreIndexProperties($store);
                foreach ($properties as &$field) {
                    if (isset($field['boost'])) {
                        unset($field['boost']);
                    }
                }
                unset($field);

                $mapping->setAllField(array('analyzer' => 'std'));

                $mapping->setProperties($properties);

                Mage::dispatchEvent('clean_elasticsearch_mapping_send_before', array(
                    'client' => $this,
                    'store' => $store,
                    'mapping' => $mapping,
                    'type' => $type,
                    'indexer' => $indexer
                ));

                $mapping->getType()->request(
                    '_mapping',
                    \Elastica\Request::PUT,
                    $mapping->toArray(),
                    array('update_all_types' => false)
                );
            }

            // Set index analyzers for future search
            //$index->setAnalyzers($this->_helper->getStoreAnalyzers($store));
        }

        return $index;
    }

    /**
     * Checks if given index already exists
     * Here because of a bug when calling exists() method directly on index object during index process
     *
     * @param mixed $index
     * @return bool
     */
    public function indexExists($index)
    {
        return $this->getClient()->getStatus()->indexExists($index);
    }

    public function isSourceEnabled($store = null)
    {
        return true;
    }

    /**
     * @return \Elastica\Type
     */
    public function getCustomerType()
    {
        return $this->getIndex()->getType('customer');
    }

    /**
     * @return \Elastica\Type
     */
    public function getOrderType()
    {
        return $this->getIndex()->getType('order');
    }

    public function deleteIndex()
    {
        try {
            $name = Mage::helper('cleanelastic')->getIndexName();
            if ($this->indexExists($name)) {
                $this->getIndex()->delete();
                unset($this->_index);
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'IndexMissingException') !== false) {
                // Do nothing
            } else {
                throw $e;
            }
        }

        return $this;
    }

    /**
     * @param $type string
     * @return Clean_ElasticSearch_Model_IndexType_Abstract
     */

    public function getIndexer($type)
    {
        return Mage::getSingleton('cleanelastic/indexType_'.$type);
    }
}