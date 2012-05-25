<?php
/**
 * Magja Sales Order API
 *
 * @category   Sales
 * @package    Magja_Catalog
 * @author     Rudi Wijaya <rudi@berbatik.com>
 */
class Magja_Catalog_Model_Sales_Order_Api extends Mage_Sales_Model_Order_Api
{

	public function create($data) {
		$customer_id = $data['customer_id'];
		
		$customer = Mage::getModel('customer/customer')->load($customer_id);/*$customerId is the id of the customer who is placing the order, it can be passed as an argument to the function place()*/
		
		$transaction = Mage::getModel('core/resource_transaction');
		$storeId = $customer->getStoreId();
		$reservedOrderId = Mage::getSingleton('eav/config')->getEntityType('order')->fetchNewIncrementId($storeId);
		
		//Set order
		$order = Mage::getModel('sales/order');
		$order
		->setIncrementId($reservedOrderId)
		->setStoreId($storeId)
		->setQuoteId(0)
		->setGlobal_currency_code('USD')
		->setBase_currency_code('USD')
		->setStore_currency_code('USD')
		->setOrder_currency_code('USD');
		
		// set Customer data
		$order->setCustomer_email($customer->getEmail())
		->setCustomerFirstname($customer->getFirstname())
		->setCustomerLastname($customer->getLastname())
		->setCustomerGroupId($customer->getGroupId())
		->setCustomer_is_guest(0)
		->setCustomer($customer);
		
		// set Billing Address
		$billing = $customer->getDefaultBillingAddress();
		
		$billingAddress = Mage::getModel('sales/order_address')
		->setStoreId($storeId)
		->setAddressType(Mage_Sales_Model_Quote_Address::TYPE_BILLING)
		->setCustomerId($customer->getId())
		->setCustomerAddressId($customer->getDefaultBilling())
		->setCustomer_address_id($billing->getEntityId())
		->setPrefix($billing->getPrefix())
		->setFirstname($billing->getFirstname())
		->setMiddlename($billing->getMiddlename())
		->setLastname($billing->getLastname())
		->setSuffix($billing->getSuffix())
		->setCompany($billing->getCompany())
		->setStreet($billing->getStreet())
		->setCity($billing->getCity())
		->setCountry_id($billing->getCountryId())
		->setRegion($billing->getRegion())
		->setRegion_id($billing->getRegionId())
		->setPostcode($billing->getPostcode())
		->setTelephone($billing->getTelephone())
		->setFax($billing->getFax());
		$order->setBillingAddress($billingAddress);
		
		$shipping = $customer->getDefaultShippingAddress();
		
		$shippingAddress = Mage::getModel('sales/order_address')
		->setStoreId($storeId)
		->setAddressType(Mage_Sales_Model_Quote_Address::TYPE_SHIPPING)
		->setCustomerId($customer->getId())
		->setCustomerAddressId($customer->getDefaultShipping())
		->setCustomer_address_id($shipping->getEntityId())
		->setPrefix($shipping->getPrefix())
		->setFirstname($shipping->getFirstname())
		->setMiddlename($shipping->getMiddlename())
		->setLastname($shipping->getLastname())
		->setSuffix($shipping->getSuffix())
		->setCompany($shipping->getCompany())
		->setStreet($shipping->getStreet())
		->setCity($shipping->getCity())
		->setCountry_id($shipping->getCountryId())
		->setRegion($shipping->getRegion())
		->setRegion_id($shipping->getRegionId())
		->setPostcode($shipping->getPostcode())
		->setTelephone($shipping->getTelephone())
		->setFax($shipping->getFax());
		
		$order->setShippingAddress($shippingAddress)
		->setShipping_method('flatrate_flatrate')
		->setShippingDescription('flatrate');
		
		$orderPayment = Mage::getModel('sales/order_payment')
		->setStoreId($storeId)
		->setCustomerPaymentId(0)
		->setMethod('purchaseorder')
		->setPo_number(' - ');
		$order->setPayment($orderPayment);
		
		// Set sales order status to 'complete'
		$order->setStatus(Mage_Sales_Model_Order::STATE_COMPLETE);
		
		// Set sales order payment method
		$payment = Mage::getModel('sales/order_payment')->setMethod('checkmo');
		$order->setPayment($payment);
		
		//Set products
		$subTotal = 0;
		$products = $data["items"];
		foreach ($products as $productId=>$product) {
			$_product = Mage::getModel('catalog/product')->load($product['product_id']);
			$rowTotal = $_product->getPrice() * $product['qty'];
			$orderItem = Mage::getModel('sales/order_item')
			->setStoreId($storeId)
			->setQuoteItemId(0)
			->setQuoteParentItemId(NULL)
			->setProductId($productId)
			->setProductType($_product->getTypeId())
			->setQtyBackordered(NULL)
			->setTotalQtyOrdered($product['qty'])
			->setQtyOrdered($product['qty'])
			->setName($_product->getName())
			->setSku($_product->getSku())
			->setPrice($_product->getPrice())
			->setBasePrice($_product->getPrice())
			->setOriginalPrice($_product->getPrice())
			->setRowTotal($rowTotal)
			->setBaseRowTotal($rowTotal);
		
			$subTotal += $rowTotal;
			$order->addItem($orderItem);
		}
		
		$order->setSubtotal($subTotal)
		->setBaseSubtotal($subTotal)
		->setGrandTotal($subTotal)
		->setBaseGrandTotal($subTotal);
		
		$transaction->addObject($order);
		$transaction->addCommitCallback(array($order, 'place'));
		$transaction->addCommitCallback(array($order, 'save'));
		$transaction->save();
		
// 		return $order;
		return 'success';
	}
	
}
