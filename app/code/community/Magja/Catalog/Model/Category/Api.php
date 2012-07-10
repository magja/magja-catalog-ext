<?php
/**
 * Magja Catalog Category api extension
 * This class add 'flat' hierarchical category tree
 *
 * @category   Magja
 * @package    Magja_Catalog
 * @author     Magja Core Team - http://magja.googlecode.com
 */
class Magja_Catalog_Model_Category_Api extends Mage_Catalog_Model_Category_Api {
	
	/**
	* Retrieve hierarchical tree using flat structure
	*
	* @param int $parent
	* @param string|int $store
	* @return array
	*/
	public function flat($parentId = null, $store = null)
	{
		if (is_null($parentId) && !is_null($store)) {
			$parentId = Mage::app()->getStore($this->_getStoreId($store))->getRootCategoryId();
		} elseif (is_null($parentId)) {
			$parentId = 1;
		}
	
		/* @var $tree Mage_Catalog_Model_Resource_Eav_Mysql4_Category_Tree */
		$tree = Mage::getResourceSingleton('catalog/category_tree')
			->load();
		
		$root = $tree->getNodeById($parentId);
		
		if($root && $root->getId() == 1) {
			$root->setName(Mage::helper('catalog')->__('Root'));
		}
		
		$collection = Mage::getModel('catalog/category')->getCollection()
			->setStoreId($storeId)
			->addAttributeToSelect('name')
			->addAttributeToSelect('is_active');
		
		$tree->addCollectionData($collection, true);
		
		$categories = $this->_nodeToArrayPath($root, null);
		return $categories;
	}

	/**
	 * Return map of categories where the key
	 * is category URL path (e.g. "accessories/bb-pouch")
	 * and the value is a map of [id, name]. 
	 */
	public function listPaths() {
		$categories = Mage::getModel('catalog/category')->getCollection()
			->addAttributeToSelect('*');
		$result = array();
		foreach ($categories as $cat) {
			/* @var $cat Mage_Catalog_Model_Category */
			$urlKeyPath = $cat->getUrlKey();
			if ($cat->getLevel() >= 2 && $urlKeyPath != '') {
				// prepend parent url keys (if any)
				$current = $cat->getParentCategory();
				while ($current != null && $current->getLevel() >= 2 && $current->getUrlKey() != '') {
					$urlKeyPath = $current->getUrlKey() . '/' . $urlKeyPath;
					$current = $current->getParentCategory();
				}
				// lookup using this urlkey-path
				$result[ $urlKeyPath ] = array(
					'id' => $cat->getId(),
					'name' => $cat->getName() );
			}
		}
		return $result;
	}
	
	/**
	* Convert node to array with path information.
	* Path information skips the 'Root Catalog' (level=0) and 'Default Category' (level=1) levels.
	*
	* @param Varien_Data_Tree_Node $node
	* @return array
	*/
	protected function _nodeToArrayPath(Varien_Data_Tree_Node $node, $parentPath)
	{
		// Only basic category data
		$categories = array();
	
		$category = array();
		$category['category_id'] = $node->getId();
		$category['parent_id']   = $node->getParentId();
		$category['name']        = $node->getName();
		$category['path']        = !empty($parentPath) && $node->getLevel() > 2 ? $parentPath .'/'. $node->getName() : $node->getName();
		$category['is_active']   = $node->getIsActive();
		$category['position']    = $node->getPosition();
		$category['level']       = $node->getLevel();
		$categories[] = $category;
	
		foreach ($node->getChildren() as $child) {
			$children = $this->_nodeToArrayPath($child, $category['path']);
			$categories = array_merge($categories, $children);
		}
	
		return $categories;
	}
	
}
