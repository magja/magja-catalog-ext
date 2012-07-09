<?php
/**
 * Magja Sales Order API
 *
 * Inspired by {@link Mage_Adminhtml_Model_Sales_Order_Create}.
 * 
 * @category   Sales
 * @package    Magja_Catalog
 * @author     Rudi Wijaya <rudi@berbatik.com>
 */
class Magja_Catalog_Model_Sales_Order_Api extends Mage_Sales_Model_Order_Api
{
	/**
	 * Create/insert new sales flat product.
	 * @param unknown_type $data
	 * @throws Exception
	 * @return Ambigous <mixed, NULL, multitype:>
	 */
	public function create($data) {
		$customer_id = $data['customer_id'];
		
		$customer = Mage::getModel('customer/customer')->load($customer_id);/*$customerId is the id of the customer who is placing the order, it can be passed as an argument to the function place()*/
		
		$transaction = Mage::getModel('core/resource_transaction');
		$storeId = $customer->getStoreId();
		$reservedOrderId = Mage::getSingleton('eav/config')->getEntityType('order')->fetchNewIncrementId($storeId);
		
		//Set order
		/* @var $order Mage_Sales_Model_Order */
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
		if ($billing == null)
			throw new Exception("Customer #{$customer_id} default billing address must not be empty.");
		
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
		if ($shipping == null)
			throw new Exception("Customer #{$customer_id} default shipping address must not be empty.");
		
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
				->setProductId($product['product_id'])
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
		
		// TODO: send New Order Email should be customizable
		$order->sendNewOrderEmail();

// 		return $order;
		$orderId = $order->getIncrementId();
		Mage::log("Created Sales Order {$order->getId()} #{$order->getIncrementId()}"); 
		return $orderId;
	}
	
	/**
	 * Update sales flat order by IncrementID.
	 * @param unknown_type $incrementId
	 */
	public function update($data) {
		$incrementId 	= $data['incrementId'];
		$status			= $data['status'];
		$awb			= $data['awb'];
		$message		= $data['message'];
		
		$order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
		
		$order->setStatus(Mage_Sales_Model_Order::STATE_NEW);
// 		$order->set
		
		$order -> save();
	}
	
}
