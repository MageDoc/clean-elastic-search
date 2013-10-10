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
        $this->_deleteElasticaIndex();
        $this->_reindexAllOrders();
        $this->_reindexAllCustomers();
    }

    protected function _deleteElasticaIndex()
    {
        Mage::getSingleton('cleanelastic/index')->deleteIndex();
    }

    protected function _getCustomers()
    {
        $customers = Mage::getResourceModel('customer/customer_collection')
            ->addAttributeToSelect('firstname')
            ->addAttributeToSelect('lastname');
        //$customers->setPageSize(1000);

        return $customers;
    }

    protected function _reindexAllCustomers()
    {
        $customerType = Mage::getSingleton('cleanelastic/index')->getCustomerType();

        foreach ($this->_getCustomers() as $customer) {
            $customerDocument = $this->_prepareCustomerDocument($customer);
            $customerType->addDocument($customerDocument);
        }
    }

    /**
     * @param $customer Mage_Customer_Model_Customer
     */
    protected function _prepareCustomerDocument($customer)
    {
        $data = array(
            'id'            => $customer->getId(),
            'email'         => $customer->getData('email'),
            'firstname'     => $customer->getData('firstname'),
            'lastname'      => $customer->getData('lastname'),
            'fullname'      => $customer->getData('firstname') . ' ' . $customer->getData('lastname'),
        );

        $document = new \Elastica\Document($customer->getId(), $data);
        return $document;
    }

    protected function _getOrders()
    {
        $orders = Mage::getResourceModel('sales/order_collection');
        // $orders->setPageSize(2000);

        $orders->getSelect()
            ->joinLeft(
                array('item' => $orders->getTable('sales/order_item')),
                'item.order_id = main_table.entity_id',
                array("GROUP_CONCAT(sku SEPARATOR ', ') AS sku_list")
            )
            ->group('main_table.entity_id');

        return $orders;
    }

    protected function _reindexAllOrders()
    {
        Mage::getSingleton('core/resource_iterator')->walk(
            $this->_getOrders()->getSelect(),
            array(array($this, 'reindexOrder'))
        );
    }

    public function reindexOrder($data)
    {
        $type = Mage::getSingleton('cleanelastic/index')->getOrderType();
        $document = $this->_prepareOrderDocument($data['row']);
        $type->addDocument($document);
    }

    /**
     * @param $order Mage_Sales_Model_Order
     */
    protected function _prepareOrderDocument($orderData)
    {
        // $customer = $customer->load($customer->getId());
        $data = array(
            'id'            => $orderData['entity_id'],
            'email'         => $orderData['customer_email'],
            'firstname'     => $orderData['customer_firstname'],
            'lastname'      => $orderData['customer_lastname'],
            'fullname'      => $orderData['customer_firstname'] . ' ' . $orderData['customer_lastname'],
            'increment_id'  => $orderData['increment_id'],
            'sku_list'      => $orderData['sku_list'],
        );

        $document = new \Elastica\Document($orderData['entity_id'], $data);
        return $document;
    }
}
