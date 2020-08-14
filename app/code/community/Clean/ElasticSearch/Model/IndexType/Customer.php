<?php

class Clean_ElasticSearch_Model_IndexType_Customer extends Clean_ElasticSearch_Model_IndexType_Abstract
{
    protected function _getIndexTypeCode()
    {
        return 'customer';
    }

    protected function _getCollection()
    {
        $customers = Mage::getResourceModel('customer/customer_collection')
            ->joinAttribute('firstname', 'customer/firstname', 'entity_id', null, $joinType='left')
            ->joinAttribute('lastname', 'customer/lastname', 'entity_id', null, $joinType='left')
            //->addAttributeToSelect('firstname')
            //->addAttributeToSelect('lastname')
            ->joinAttribute(
                'billing_telephone', 'customer_address/telephone', 'default_billing', null, 'left')
            ->joinAttribute(
                'billing_fax', 'customer_address/fax', 'default_billing', null, 'left');
        //$customers->setPageSize(1000);

        return $customers;
    }

    /**
     * @param $customer Mage_Customer_Model_Customer
     * @return \Elastica\Document
     */
    protected function _prepareDocument($customer)
    {
        if (!$customer->hasData('billing_telephone')
            && $billingAddress = $customer->getDefaultBillingAddress()){
            $customer->setData('billing_telephone', $billingAddress->getTelephone());
            $customer->setData('billing_fax', $billingAddress->getFax());
        }
        $data = array(
            'id'            => $customer->getId(),
            'email'         => $customer->getData('email'),
            'firstname'     => $customer->getData('firstname'),
            'lastname'      => $customer->getData('lastname'),
            'fullname'      => $customer->getData('firstname') . ' ' . $customer->getData('lastname'),
            'telephone'     => $customer->getData('billing_telephone'),
            'fax'           => $customer->getData('billing_fax'),
        );

        $document = new \Elastica\Document($customer->getId(), $data);
        return $document;
    }

    public function getSearchFields($q, $analyzer = false, $withBoost = true)
    {
        return array(
            'firstname',
            'lastname',
            'fullname',
            'email',
            'telephone',
            'fax');
    }
}