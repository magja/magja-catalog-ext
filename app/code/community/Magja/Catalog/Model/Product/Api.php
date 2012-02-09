<?php
/**
 * Magja Catalog product api extension
 * This class add the option to add a configurable product
 * reference: http://www.stephenrhoades.com/?p=338
 *
 * @category   Magja
 * @package    Magja_Catalog
 * @author     Magja Core Team - http://magja.googlecode.com
 */
class Magja_Catalog_Model_Product_Api extends Mage_Catalog_Model_Product_Api {
	
	/**
	* Create new product.
	*
	* @param string $type
	* @param int $set
	* @param string $sku
	* @param array $productData
	* @param string $store
	* @return int
	*/
	public function create($type, $set, $sku, $productData, $store = null) {
		if (is_string($productData['websites'])) {
			$productData['websites'] = explode(',', $productData['websites']);
		}
		return parent::create($type, $set, $sku, $productData, $store);
	}

	/**
	 * Retrieve product info
	 *
	 * @param int|string $productId
	 * @param string|int $store
	 * @param array $attributes
	 * @return array
	 */
	public function info($productId, $store = null, $attributes = null, $identifierType = null) {
		
		$result = parent::info ( $productId, $store, $attributes, $identifierType );
		
		if ($result ['type'] == Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE) {
			
			$product = Mage::getModel ( 'catalog/product' )->load ( $result ['product_id'] );
			
			if ($product->isConfigurable ()) {
				$ids = $product->getTypeInstance ( true )->getUsedProductIds ( $product );
				$result ['subproduct_ids'] = $ids;
			}
		}
		
		return $result;
	}
	
	/**
	 * Set additional data before product saved
	 *
	 * @param    Mage_Catalog_Model_Product $product
	 * @param    array $productData
	 * @return	  object
	 */
	protected function _prepareDataForSave($product, $productData) {
		
		parent::_prepareDataForSave ( $product, $productData );
		
		if (isset ( $productData ['configurable_products_data'] ) && is_array ( $productData ['configurable_products_data'] )) {
			$product->setConfigurableProductsData ( $productData ['configurable_products_data'] );
		}
		
		/*
		 * Check for configurable products array passed through API Call
		 */
		if (isset ( $productData ['configurable_attributes_data'] ) && is_array ( $productData ['configurable_attributes_data'] )) {
			foreach ( $productData ['configurable_attributes_data'] as $key => $data ) {
				//Check to see if these values exist, otherwise try and populate from existing values
				$data ['label'] = (! empty ( $data ['label'] )) ? $data ['label'] : $product->getResource ()->getAttribute ( $data ['attribute_code'] )->getStoreLabel ();
				$data ['frontend_label'] = (! empty ( $data ['frontend_label'] )) ? $data ['frontend_label'] : $product->getResource ()->getAttribute ( $data ['attribute_code'] )->getFrontendLabel ();
				$productData ['configurable_attributes_data'] [$key] = $data;
			}
			$product->setConfigurableAttributesData ( $productData ['configurable_attributes_data'] );
			$product->setCanSaveConfigurableAttributes ( 1 );
		}
	}
	
	/**
	* Retrieve products list by filters Include Price and Description etc.
	*
	* @param array $filters
	* @param string|int $store
	* @return array
	* 
	*/
	public function itemsEx($filters = null, $store = null)
	{
		$collection = Mage::getModel('catalog/product')->getCollection()
		->addStoreFilter($this->_getStoreId($store))
		->addAttributeToSelect('name')
		->addAttributeToSelect('price')
		->addAttributeToSelect('description');
		
	
		if (is_array($filters)) {
			try {
				foreach ($filters as $field => $value) {
					if (isset($this->_filtersMap[$field])) {
						$field = $this->_filtersMap[$field];
					}
	
					$collection->addFieldToFilter($field, $value);
				}
			} catch (Mage_Core_Exception $e) {
				$this->_fault('filters_invalid', $e->getMessage());
			}
		}
	
		$result = array();
	
		foreach ($collection as $product) {
			//            $result[] = $product->getData();
			$categoryIds = $product->getCategoryIds();
			
				
			$result[] = array( // Basic product data
	                'product_id' => $product->getId(),
	                'sku'        => $product->getSku(),
	                'name'       => $product->getName(),
	                'set'        => $product->getAttributeSetId(),
	                'type'       => $product->getTypeId(),
					'price'      => $product->getPrice(),
					'description'      => $product->getDescription(),
					'category_ids'       => $categoryIds,
					'category_id'       => !empty($categoryIds) ? $categoryIds[0] : null
			);
		}
		
		return $result;
	}
}
