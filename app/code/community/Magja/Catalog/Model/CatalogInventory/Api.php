<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_CatalogInventory
 * @copyright   Copyright (c) 2011 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Catalog inventory api
 *
 * @category   Mage
 * @package    Mage_CatalogInventory
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class Magja_Catalog_Model_CatalogInventory_Api extends Mage_CatalogInventory_Model_Stock_Item_Api
{
	
	protected function splitByComma($str) {
		$raw_ids = explode(',', $str);
		$result = array();
		foreach ($raw_ids as $id) {
			$result[] = trim($id);
		}
		return $result;
	}
	
    public function items($productIds)
    {
    	if (is_array($productIds) && isset($productIds['product_ids'])) {
    		$productIds = $this->splitByComma($productIds['product_ids']);
    	}

    	if (!is_array($productIds)) {
        	if (is_string($productIds)) {
        		$productIds = $this->splitByComma($productIds);
        	} else {
            	$productIds = array($productIds);
        	}
        }

        $product = Mage::getModel('catalog/product');

        foreach ($productIds as &$productId) {
            if ($newId = $product->getIdBySku($productId)) {
                $productId = $newId;
            }
        }

        $collection = Mage::getModel('catalog/product')
            ->getCollection()
            ->setFlag('require_stock_items', true)
            ->addFieldToFilter('entity_id', array('in'=>$productIds));

        $result = array();

        foreach ($collection as $product) {
            if ($product->getStockItem()) {
                $result[] = array(
                    'product_id'    => $product->getId(),
                    'sku'           => $product->getSku(),
                    'qty'           => $product->getStockItem()->getQty(),
                    'is_in_stock'   => $product->getStockItem()->getIsInStock()
                );
            }
        }

        return $result;
    }
    
    public function itemsAll()
    {
    	$products = Mage::getModel('catalog/product')->getResourceCollection();
    	$productIds = array();
    	foreach ($products as $product) {
			$productIds[] = $product->getId();
    	}
    	$result = $this->items($productIds);
    	return $result;
    }
    
} // Class Mage_CatalogInventory_Model_Stock_Item_Api End
