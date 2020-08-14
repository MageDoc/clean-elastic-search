<?php

class Clean_ElasticSearch_Model_Indexer extends Mage_Index_Model_Indexer_Abstract
{
   public function getName()
    {
        return Mage::helper('core')->__('Elastic Search Cache');
    }

    public function getDescription()
    {
        return Mage::helper('core')->__('Elastic search');
    }

    protected function _construct()
    {
        $this->_init('cleanelastic/indexer');
    }

    /**
     * Handling the incremental indexing in observers
     *
     * @see Clean_ElasticSearch_Model_Observer::customerSaveCommitAfter()
     * @param Mage_Index_Model_Event $event
     */
    protected function _registerEvent(Mage_Index_Model_Event $event) { }
    protected function _processEvent(Mage_Index_Model_Event $event) { }

    public function reindexAll()
    {
        Mage::getSingleton('core/resource')->getConnection('core_write')->query("SET session wait_timeout=3600");
        /** @var Clean_ElasticSearch_Model_Index $indexManager */
        $indexManager = Mage::getSingleton('cleanelastic/index');
        $indexManager->deleteIndex();
        foreach (Mage::helper('cleanelastic')->getStoreTypes() as $type){
            $indexManager->getIndexer($type)->index();
        }
    }
}
