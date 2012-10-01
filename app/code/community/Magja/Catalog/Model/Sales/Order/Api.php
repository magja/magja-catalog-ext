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
	 * Create/insert new sales order.
	 * @param array $data Contains, among others: currency_code
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
		$currency_code = isset($data['currency_code']) ? $data['currency_code'] : 'USD';
		$order
			->setIncrementId($reservedOrderId)
			->setStoreId($storeId)
			->setQuoteId(0)
			->setGlobal_currency_code($currency_code)
			->setBase_currency_code($currency_code)
			->setStore_currency_code($currency_code)
			->setOrder_currency_code($currency_code);

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
		
		// Quote will be picked up by Mage_CatalogInventory_Model::subtractQuoteInventory()
		// which requires $quote->getAllItems()
		$quote = Mage::getModel('sales/quote');
		foreach ($order->getItemsCollection(
				array_keys(Mage::getConfig()->getNode('adminhtml/sales/order/create/available_product_types')->asArray()),
				true
		) as $orderItem) {
			/* @var $orderItem Mage_Sales_Model_Order_Item */
			if (!$orderItem->getParentItem()) {
				if ($order->getReordered()) {
					$qty = $orderItem->getQtyOrdered();
				} else {
					$qty = $orderItem->getQtyOrdered() - $orderItem->getQtyShipped() - $orderItem->getQtyInvoiced();
				}
		
				if ($qty > 0) {
					$item = $this->initFromOrderItem($quote, $orderItem, $qty, $storeId);
					if (is_string($item)) {
						Mage::throwException($item);
					}
				}
			}
		}
		// This eventually decreases the stock on Manage Stock=Yes products, observed by Mage_CatalogInventory
		Mage::dispatchEvent('checkout_submit_all_after', array('order' => $order, 'quote' => $quote));

// 		return $order;
		$orderId = $order->getIncrementId();
		Mage::log("Created Sales Order {$order->getId()} #{$order->getIncrementId()}"); 
		return $orderId;
	}
	
	/**
	 * Create/insert new sales order with custom shipping address
	 * @param array $data Contains, among others: currency_code
	 *   Additional: shipping_method (default: flatrate_flatrate);
	 *   shipping_description (default: 'Flat Rate - Fixed'),
	 *   payment_method (default: 'banktransfer'), others: purchaseorder, checkmo;
	 *   shipping_amount (default: 0)
	 * @throws Exception
	 * @return Ambigous <mixed, NULL, multitype:>
	 */
	public function createEx($data) {
		//Mage::log('Create sales Order Ex '. var_export($data, true));
		//return null;
		
		$customer_id = $data['customer_id'];
		
		$customer = Mage::getModel('customer/customer')->load($customer_id);/*$customerId is the id of the customer who is placing the order, it can be passed as an argument to the function place()*/
		
		$transaction = Mage::getModel('core/resource_transaction');
		$storeId = $customer->getStoreId();
		$reservedOrderId = Mage::getSingleton('eav/config')->getEntityType('order')->fetchNewIncrementId($storeId);
		
		//Set order
		/* @var $order Mage_Sales_Model_Order */
		$order = Mage::getModel('sales/order');
		$currency_code = isset($data['currency_code']) ? $data['currency_code'] : 'USD';
		$order
			->setIncrementId($reservedOrderId)
			->setStoreId($storeId)
			->setQuoteId(0)
			->setGlobalCurrencyCode($currency_code)
			->setBaseCurrencyCode($currency_code)
			->setStoreCurrencyCode($currency_code)
			->setOrderCurrencyCode($currency_code);
		
		// set Customer data
		$order->setCustomerEmail($customer->getEmail())
			->setCustomerFirstname($customer->getFirstname())
			->setCustomerLastname($customer->getLastname())
			->setCustomerGroupId($customer->getGroupId())
			->setCustomerIsGuest(0)
			->setCustomer($customer);
		
		// set Billing Address
		$billing = $customer->getDefaultBillingAddress();
		if ($billing == null)
			throw new Exception("Customer #{$customer_id} default billing address must not be empty.");
		
		$newbilling = $data['billingAddress'];
		Mage::log('trying to set billing address '. var_export($newbilling, true));
		
		$billingAddress = Mage::getModel('sales/order_address')
			->setStoreId($storeId)
			->setAddressType(Mage_Sales_Model_Quote_Address::TYPE_BILLING)
			->setCustomerId($customer->getId())
			->setFirstname($newbilling['firstname'])
			->setLastname($newbilling['lastname'])
			->setCompany($newbilling['company'])
			->setStreet($newbilling['street'])
			->setCity($newbilling['city'])
			->setCountryId($newbilling['country_id'])
			->setRegion($newbilling['region'])
			->setPostcode($newbilling['postcode'])
			->setTelephone($newbilling['telephone']);
		$order->setBillingAddress($billingAddress);
		Mage::log('billing address has been setted '. var_export($newbilling, true));
		//return null;
		// set shipping address
		$shipping = $customer->getDefaultShippingAddress();
		if ($shipping == null)
			throw new Exception("Customer #{$customer_id} default shipping address must not be empty.");
		
		$shipping = $data['shippingAddress'];
		Mage::log("sales_order.createEx with shipping Address ". var_export($shipping, true));
		$shippingAddress = Mage::getModel('sales/order_address')
			->setStoreId($storeId)
			->setAddressType(Mage_Sales_Model_Quote_Address::TYPE_SHIPPING)
			->setCustomerId($customer->getId())
			->setCustomerId($customer->getId())
			->setFirstname($shipping['firstname'])
			->setLastname($shipping['lastname'])
			->setCompany($shipping['company'])
			->setStreet($shipping['street'])
			->setCity($shipping['city'])
			->setCountryId($shipping['country_id'])
			->setRegion($shipping['region'])
			->setPostcode($shipping['postcode'])
			->setTelephone($shipping['telephone']);
		
		//var_dump($shippingAddress);
		$order->setShippingAddress($shippingAddress)
			->setShippingMethod(!empty($data['shipping_method']) ? $data['shipping_method'] : 'flatrate_flatrate')
			->setShippingDescription(!empty($data['shipping_description']) ? $data['shipping_description'] : 'Flat Rate - Fixed');

		$shippingCost = !empty($data['shipping_amount']) ? $data['shipping_amount'] : 0;
		$order->setShippingAmount($shippingCost);
		$order->setShippingInclTax($shippingCost);
		
		// Set sales order payment method
		$orderPayment = Mage::getModel('sales/order_payment')
			->setStoreId($storeId)
			->setCustomerPaymentId(0)
			->setMethod('banktransfer'); // ->setMethod('banktransfer');//->setMethod('purchaseorder') // ->setMethod('checkmo');
			//->setPo_number('-');
		$order->setPayment($orderPayment);
		
		// Set sales order status to 'complete'
		$order->setStatus(Mage_Sales_Model_Order::STATE_COMPLETE);
		
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
		
		$grandTotal = $subTotal + $shippingCost;
		$order->setSubtotal($subTotal)
			->setBaseSubtotal($subTotal)
			->setGrandTotal($grandTotal)
			->setBaseGrandTotal($grandTotal);
		
		$transaction->addObject($order);
		$transaction->addCommitCallback(array($order, 'place'));
		$transaction->addCommitCallback(array($order, 'save'));
		$transaction->save();
		
		// TODO: send New Order Email should be customizable
		$order->sendNewOrderEmail();

		// Quote will be picked up by Mage_CatalogInventory_Model::subtractQuoteInventory()
		// which requires $quote->getAllItems()
		$quote = Mage::getModel('sales/quote');
		foreach ($order->getItemsCollection(
				array_keys(Mage::getConfig()->getNode('adminhtml/sales/order/create/available_product_types')->asArray()),
				true
		) as $orderItem) {
			/* @var $orderItem Mage_Sales_Model_Order_Item */
			if (!$orderItem->getParentItem()) {
				if ($order->getReordered()) {
					$qty = $orderItem->getQtyOrdered();
				} else {
					$qty = $orderItem->getQtyOrdered() - $orderItem->getQtyShipped() - $orderItem->getQtyInvoiced();
				}
		
				if ($qty > 0) {
					$item = $this->initFromOrderItem($quote, $orderItem, $qty, $storeId);
					if (is_string($item)) {
						Mage::throwException($item);
					}
				}
			}
		}
		// This eventually decreases the stock on Manage Stock=Yes products, observed by Mage_CatalogInventory
		Mage::dispatchEvent('checkout_submit_all_after', array('order' => $order, 'quote' => $quote));

// 		return $order;
		$orderId = $order->getIncrementId();
		Mage::log("Created Sales Order {$order->getId()} #{$order->getIncrementId()}"); 
		return $orderId;
	}
	
    /**
     * Initialize creation data from existing order Item
     *
     * @param Mage_Sales_Model_Order_Item $orderItem
     * @param int $qty
     * @return Mage_Sales_Model_Quote_Item | string
     */
    protected function initFromOrderItem(Mage_Sales_Model_Quote $quote, Mage_Sales_Model_Order_Item $orderItem, $qty, $storeId)
    {
        if (!$orderItem->getId()) {
            return $this;
        }

        $product = Mage::getModel('catalog/product')
            ->setStoreId($storeId)
            ->load($orderItem->getProductId());

        if ($product->getId()) {
            $product->setSkipCheckRequiredOption(true);
            $buyRequest = $orderItem->getBuyRequest();
            if (is_numeric($qty)) {
                $buyRequest->setQty($qty);
            }
            $item = $quote->addProduct($product, $buyRequest);
            if (is_string($item)) {
                return $item;
            }

            if ($additionalOptions = $orderItem->getProductOptionByCode('additional_options')) {
                $item->addOption(new Varien_Object(
                    array(
                        'product' => $item->getProduct(),
                        'code' => 'additional_options',
                        'value' => serialize($additionalOptions)
                    )
                ));
            }

            Mage::dispatchEvent('sales_convert_order_item_to_quote_item', array(
                'order_item' => $orderItem,
                'quote_item' => $item
            ));
            return $item;
        }

        return $this;
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
		
		$order->save();
	}
	
}
