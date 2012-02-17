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
	
	/**
	* Download image from a URL, add it as product image and return image filename
	*
	* @param int|string $productId
	* @param string $name Image filename
	* @param string $mimetype Image MIME type
	* @param string $url Image URL
	* @param string|int $store
	* @return string
	*/
	public function createFromUrl($productId, $name, $mimetype, $url, $label, $types = array('image', 'small_image', 'thumbnail'),
		$store = null, $identifierType = null) {
		if (is_string($types))
			$types = explode(',', $types);
		Mage::log("Downloading from ". $url);
		$content = file_get_contents($url);
		$data = array(
			'label' => $label,
			'types' => $types,
			'exclude' => 0,
			'file' => array(
				'name' => $name,
				'content' => base64_encode($content),
				'mime' => $mimetype
			));
		return $this->create($productId, $data, $store, $identifierType);
	}
	
}

?>