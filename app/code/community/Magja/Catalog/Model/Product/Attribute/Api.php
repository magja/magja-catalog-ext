<?php

/**
 * Magja Catalog product attribute api extension
 *
 * @category   Magja
 * @package    Magja_Catalog
 * @author     Magja Core Team - http://magja.googlecode.com
 */
class Magja_Catalog_Model_Product_Attribute_Api extends Mage_Catalog_Model_Product_Attribute_Api {
	
    /**
     * Create new product attribute
     *
     * @param array $data input data
     * @return integer
     */
    public function create($data) {
    	// if frontend_label is not provided as an array, help the client
    	if (!empty($data['frontend_label']) && !is_array($data['frontend_label'])) {
    		$frontend_label = $data['frontend_label']; 
    		$data['frontend_label'] = array( array('store_id' => 0, 'label' => $frontend_label) );
    	}
    	return parent::create($data);
    }
	
	/**
	 * Create attribute options
	 *
	 * @param string $attributeId
	 * @param array $attributeOptions
	 * @return int
	 */
	public function addoptions($attributeId, $attributeOptions) {
		$setup = new Mage_Eav_Model_Entity_Setup ( 'core_setup' );
		
		for($i = 0; $i < sizeof ( $attributeOptions ); $i ++) {
			$option = array ();
			$option ['attribute_id'] = $attributeId;
			$option ['value'] [$value] [0] = $attributeOptions [$i];
			
			$setup->addAttributeOption ( $option );
		}
		
		return true;
	}
	
	/**
	 * Delete product attribute.
	 *
	 * @param string $attributeName
	 * @param string|int $store
	 * @return int
	 */
	public function delete($attributeName, $store = null) {
		$storeId = $this->_getStoreId ( $store );
		$attribute = Mage::getModel ( 'catalog/product' )->setStoreId ( $storeId )->getResource ()->getAttribute ( $attributeName );
		
		if (! $attribute) {
			$this->_fault ( 'not_exists' );
		}
		
		try {
			$attribute->delete ();
		} catch ( Mage_Core_Exception $e ) {
			$this->_fault ( 'not_deleted', $e->getMessage () );
			
			return false;
		}
		
		return true;
	}

	/**
	* Retrieve all attributes
	*
	* @return array
	*/
	public function listAll()
	{
		$attrs = Mage::getResourceModel('catalog/product_attribute_collection');
		Mage::log('Magja_Catalog_Model_Product_Attribute_Api.listAll:'. count($attrs) . ' attributes total');
		$attrs_data = $attrs->load()->getData();
		return $attrs_data;
	}
	
	/**
	* Retrieve all attribute options
	*/
	public function optionsAll()
	{
		$allOptions = array();
		$attributes = Mage::getResourceModel('catalog/product_attribute_collection');
		$attributes->addFieldToFilter('is_user_defined', array('eq' => '1'));
		$attributes->addFieldToFilter('is_configurable', array('eq' => '1'));
		$attributes->addFieldToFilter('frontend_input', array('eq' => 'select'));
		foreach ($attributes as $attr) {
// 			echo "{$attr->getId()} {$attr->getAttributeCode()} {$attr->getIsConfigurable()}\n";
			if ($attr->usesSource()) {
				$source = $attr->getSource();
				foreach ($source->getAllOptions() as $optionOrder => $optionValue) {
					if (empty($optionOrder) || empty($optionValue))
						continue;
					$allOptions[] = array(
						'attribute_id' => $attr->getId(),
						'option_order' => $optionOrder,
						'option_id' => $optionValue['value'],
						'option_value' => $optionValue['label']);
				}
			}
		}
		return $allOptions;
	}
	
	/**
	* Retrieve attribute sets including child groups and attributes
	*
	* @return array
	*/
	public function listFlat()
	{
		$entityType = Mage::getModel('catalog/product')->getResource()->getEntityType();
		$collection = Mage::getResourceModel('eav/entity_attribute_set_collection')
		->setEntityTypeFilter($entityType->getId());
	
		$result = array();
		foreach ($collection as $attributeSet) {
			$groups = Mage::getModel('eav/entity_attribute_group')
				->getResourceCollection()
				->setAttributeSetFilter($attributeSet->getId())
				->load();
				
			foreach ($groups as $group) {
				$groupAttributesCollection = Mage::getModel('eav/entity_attribute')
					->getResourceCollection()
					->setAttributeGroupFilter($group->getId())
					->load();
				
				foreach ($groupAttributesCollection as $attr) {
					$result[] = array('attribute_id' => $attr->getId(),
						'attribute_code' => $attr->getAttributeCode(),
						'group_id' => $group->getId(),
						'group_name' => $group->getAttributeGroupName(),
						'attribute_set_id' => $attributeSet->getId(),
						'attribute_set_name' => $attributeSet->getAttributeSetName(),
					);
						
				}
			}
	
		}
	
		return $result;
	}
	
}
?>