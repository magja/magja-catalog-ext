<?php

class Gorilla_ApiExtension_Model_Product_Attribute_Option extends Mage_Catalog_Model_Api_Resource
{
    public function create($attributeId, $attributeOptions, $store = null)
    {

        if (!$attributeId || !$attributeOptions) {
            $this->_fault('data_invalid', "At least one of the required parameters was not set.");
        }

        // get attribute
        try {
            $storeId = $this->_getStoreId($store);
            $attribute = $this->getAttribute($storeId, $attributeId);
        } catch (Exception $e) {
            $this->_fault('not_exist');
        }

        /* @var $attribute Mage_Catalog_Model_Entity_Attribute */
        if (!$attribute) {
            $this->_fault('not_exist');
        }

        // if we were only passed a single option, let's just make it an array with a single value
        if (!is_array($attributeOptions)) {
            $attributeOptions = array($attributeOptions);
        }


        // if option already exists, we will ignore it
        $_options = $this->getAttributeOptions($attribute);

        // only throw errors if it fails to save a new option
        $model = Mage::getModel('eav/entity_setup');
        $result = array();
        foreach ($attributeOptions as $attributeOption)
        {
            if (!isset($_options[$attributeOption])) {
                $option = array();
                $option['attribute_id'] = $attribute->getId();
                $option['value'][0][$storeId] = $attributeOption;
                try {
                    $model->addAttributeOption($option);
                    $result[$attributeOption] = 'success';
                } catch (Exception $e) {
                    $result[$attributeOption] = 'fail';
                }
            } else {
                $result[$attributeOption] = 'already exists';
            }
        }
        
        // compose response
        $response = array();
        $attribute = $this->getAttribute($storeId, $attributeId);
        $_options = $this->getAttributeOptions($attribute); // get updated list of options
        foreach ($result as $o => $s) {
            $id = '';
            if ($s != 'fail') {
                $id = $_options[$o];
            }
            $response[] = array(
                                    'option' => $o,
                                    'status' => $s,
                                    'id'     => $id
                                );
        }
        

        return $response;
    }
    
    protected function getAttribute($storeId, $attributeId) 
    {
        return Mage::getModel('eav/config')->getAttribute('catalog_product', $attributeId);
        
//        return Mage::getModel('catalog/product')
//                                                ->setStoreId($storeId)
//                                                ->getResource()
//                                                ->getAttribute($attributeId)
//                                                ->setStoreId($storeId);
    }
    
    protected function getAttributeOptions($attribute)
    {
        $_options = array();
        foreach ($attribute->getSource()->getAllOptions(false) as $_option) {
            $_options[$_option['label']] = $_option['value'];
        }
        Mage::log($_options);
        return $_options;
    }
}