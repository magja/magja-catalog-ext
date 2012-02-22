<?php

class Gorilla_ApiExtension_Model_Product_Configurable extends Mage_Catalog_Model_Api_Resource
{
    
    public function setConfigurableAttributes($sku, $attributes)
    {
        Mage::log('Starting setConfigurableAttributes');
        Mage::log("Parameters received: ");
        Mage::log("\t sku = ".$sku);
        Mage::log("\t attributes = ".print_r($attributes, true));
        
        if (empty($sku) || empty($attributes)) {
            $this->_fault('data_invalid', "At least one of the required parameters was not set.");
        }
        
        Mage::log('Attempting to load product');
        // load product
        $product = $this->loadProduct($sku, Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);
        Mage::log('Successfully loaded product with id '.$product->getId());
        
        Mage::log('Attempting to lead attributes');
        // load attribute ids
        if (!is_array($attributes)) {
            $attributes = array($attributes);
        }
        $attributeModel = Mage::getModel('eav/config');
        $attrs = array();
        try {
            foreach ($attributes as $attribute) {
                $attr = $attributeModel->getAttribute('catalog_product', $attribute);
                if ( !$attr->getAttributeId() && $product->getTypeInstance()->canUseAttribute($attr) ) {
                    Mage::throwException('invalid attribute');
                }
                $attrs[] = $attr->getAttributeId();
            }
        } catch (Exception $e) {
            $this->_fault('attribute_invalid');
        }
        Mage::log('Successfully loaded attributes');
        
        Mage::log('Attempting to save configurable product attribute selection');
        // set product configurable-on attributes
        try {
            $product->getTypeInstance()->setUsedProductAttributeIds($attrs);
            $product->setConfigurableAttributesData($product->getTypeInstance()->getConfigurableAttributesAsArray());
            $product->setCanSaveConfigurableAttributes(true);
            $product->setCanSaveCustomOptions(true);
            $product->save();
        } catch (Exception $e) {
            $this->_fault('unknown');
        }
        Mage::log('Successfully saved');
        Mage::log('Done');
        
        return 'success';
        
    }
    
    
    
    public function associateSimpleChildren($parentSku, $childrenSkus)
    {
        Mage::log('Starting associateSimpleChildren');
        Mage::log("Parameters received: ");
        Mage::log("\t parentSku = ".$parentSku);
        Mage::log("\t childrenSkus = ".print_r($childrenSkus, true));
        
        if (empty($parentSku) || empty($childrenSkus)) {
            $this->_fault('data_invalid', "At least one of the required parameters was not set.");
        }
        
        Mage::log('Attempting to load parent product');
        // load parent product
        $product = $this->loadProduct($parentSku, Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);
        Mage::log('Successfully loaded product with id '.$product->getId());
        
        Mage::log('Attempting to load child products');
        // load children products
        if (!is_array($childrenSkus)) {
            $childrenSkus = array($childrenSkus);
        }
        $children = array();
        foreach ($childrenSkus as $childSku) {
            $child = $this->loadProduct($childSku, Mage_Catalog_Model_Product_Type::TYPE_SIMPLE);
            $children[$child->getId()] = $child;
        }
        Mage::log('Successfully loaded '.count($children).' children');
        
        Mage::log('Attempting to save configurable product\'s children selection');
        // save
        try {
            $product->setConfigurableProductsData($children);
            $product->save();
        } catch (Exception $e) {
            $this->_fault('unknown');
        }
        Mage::log('Successfully saved');
        Mage::log('Done');
        
        return 'success';
        
    }
    
    protected function loadProduct($sku, $type)
    {
        $product = Mage::getModel('catalog/product');
        try {
            $id = $product->getIdBySku($sku);
            if (empty($id)) {
                Mage::throwException('bad sku');
            }
            $product->load($id);
            if ($product->getTypeId() != $type) {
                Mage::throwException('not a configurable product');
            }
        } catch (Exception $e) {
            $this->_fault('product_not_exist', 'Inavlid product sku: '.$sku);
        }
        return $product;
    }
}