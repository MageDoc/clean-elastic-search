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

    protected function _registerEvent(Mage_Index_Model_Event $event)
    {
        // todokj register some stuff
    }

    protected function _processEvent(Mage_Index_Model_Event $event)
    {
        $data = $event->getNewData();
        if (!empty($data['catalog_product_eav_reindex_all'])) {
            $this->reindexAll();
        }
        if (empty($data['catalog_product_eav_skip_call_event_handler'])) {
            $this->callEventHandler($event);
        }
    }

    public function reindexAll()
    {
        Mage::getSingleton('cleanelastic/index')->deleteIndex();
        Mage::getModel('cleanelastic/indexType_order')->index();
        Mage::getModel('cleanelastic/indexType_customer')->index();
        Mage::getModel('cleanelastic/indexType_config')->index();
    }
}
