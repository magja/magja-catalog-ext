<?php

/**
 * Magja Catalog Product Attribute Media API extension
 *
 * @category   Magja
 * @package    Magja_Catalog
 * @author     Magja Core Team - http://magja.googlecode.com
 */
class Magja_Catalog_Model_Product_Attribute_Media_Api extends Mage_Catalog_Model_Product_Attribute_Media_Api {
	
	/**
	 * Retrieve md5
	 *
	 * @param string $file
	 * @return String
	 */
	public function md5($file) {
		return md5_file ( Mage::getBaseDir ( 'media' ) . DS . 'catalog' . DS . 'product' . $file );
	}
}

?>