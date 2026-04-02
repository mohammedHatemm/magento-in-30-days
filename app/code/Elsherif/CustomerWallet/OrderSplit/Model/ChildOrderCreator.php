<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Model;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\ItemFactory as OrderItemFactory;
use Magento\Sales\Model\Order\AddressFactory as OrderAddressFactory;
use Magento\Sales\Model\Order\PaymentFactory as OrderPaymentFactory;
use Magento\Framework\Registry;
use Psr\Log\LoggerInterface;

class ChildOrderCreator
{
    private OrderRepositoryInterface $orderRepository;
    private \Magento\Sales\Model\OrderFactory $orderFactory;
    private OrderItemFactory $orderItemFactory;
    private OrderAddressFactory $orderAddressFactory;
    private OrderPaymentFactory $orderPaymentFactory;
    private Registry $registry;
    private LoggerInterface $logger;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        OrderItemFactory $orderItemFactory,
        OrderAddressFactory $orderAddressFactory,
        OrderPaymentFactory $orderPaymentFactory,
        Registry $registry,
        LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderFactory = $orderFactory;
        $this->orderItemFactory = $orderItemFactory;
        $this->orderAddressFactory = $orderAddressFactory;
        $this->orderPaymentFactory = $orderPaymentFactory;
        $this->registry = $registry;
        $this->logger = $logger;
    }

    /**
     * Create a child order by cloning parent order data directly
     * This avoids the full checkout flow (no quote, no payment validation, no inventory re-reservation)
     */
    public function createChildOrder(
        OrderInterface $parentOrder,
        array $items,
        string $warehouseCode,
        int $splitIndex,
        int $totalSplits
    ): OrderInterface {
        $this->registry->register('order_split_creating_child', true, true);

        try {
            $this->logger->info('OrderSplit: Creating child order', [
                'parent_id' => $parentOrder->getIncrementId(),
                'warehouse' => $warehouseCode,
                'split' => "{$splitIndex}/{$totalSplits}",
                'items_count' => count($items)
            ]);

            // Calculate child totals from items
            $childSubtotal = 0;
            $childDiscount = 0;
            $childTax = 0;
            $childRowTotalInclTax = 0;

            foreach ($items as $item) {
                $childSubtotal += (float)$item->getRowTotal();
                $childDiscount += abs((float)$item->getDiscountAmount());
                $childTax += (float)$item->getTaxAmount();
                $childRowTotalInclTax += (float)$item->getRowTotalInclTax();
            }

            // Calculate proportional shipping
            $parentSubtotal = (float)$parentOrder->getSubtotal();
            $proportion = $parentSubtotal > 0 ? $childSubtotal / $parentSubtotal : (1 / $totalSplits);
            $childShipping = round((float)$parentOrder->getShippingAmount() * $proportion, 2);
            $childShippingTax = round((float)$parentOrder->getShippingTaxAmount() * $proportion, 2);
            $childShippingInclTax = round((float)$parentOrder->getShippingInclTax() * $proportion, 2);

            // Grand total
            $childGrandTotal = $childSubtotal - $childDiscount + $childTax + $childShipping + $childShippingTax;

            // Build increment ID: parent-splitIndex (e.g., 000000003-1)
            $childIncrementId = $parentOrder->getIncrementId() . '-' . $splitIndex;

            // Create new order object
            /** @var Order $childOrder */
            $childOrder = $this->orderFactory->create();

            // Copy basic order data
            $childOrder->setStoreId($parentOrder->getStoreId());
            $childOrder->setIncrementId($childIncrementId);
            $childOrder->setQuoteId(0); // No quote
            $childOrder->setCustomerId($parentOrder->getCustomerId());
            $childOrder->setCustomerEmail($parentOrder->getCustomerEmail());
            $childOrder->setCustomerFirstname($parentOrder->getCustomerFirstname());
            $childOrder->setCustomerLastname($parentOrder->getCustomerLastname());
            $childOrder->setCustomerGroupId($parentOrder->getCustomerGroupId());
            $childOrder->setCustomerIsGuest($parentOrder->getCustomerIsGuest());

            // Totals
            $childOrder->setSubtotal($childSubtotal);
            $childOrder->setBaseSubtotal($childSubtotal);
            $childOrder->setDiscountAmount(-$childDiscount);
            $childOrder->setBaseDiscountAmount(-$childDiscount);
            $childOrder->setTaxAmount($childTax);
            $childOrder->setBaseTaxAmount($childTax);
            $childOrder->setShippingAmount($childShipping);
            $childOrder->setBaseShippingAmount($childShipping);
            $childOrder->setShippingTaxAmount($childShippingTax);
            $childOrder->setBaseShippingTaxAmount($childShippingTax);
            $childOrder->setShippingInclTax($childShippingInclTax);
            $childOrder->setBaseShippingInclTax($childShippingInclTax);
            $childOrder->setGrandTotal($childGrandTotal);
            $childOrder->setBaseGrandTotal($childGrandTotal);
            $childOrder->setTotalItemCount(count($items));
            $childOrder->setTotalQtyOrdered(array_sum(array_map(fn($i) => (float)$i->getQtyOrdered(), $items)));
            $childOrder->setSubtotalInclTax($childRowTotalInclTax);
            $childOrder->setBaseSubtotalInclTax($childRowTotalInclTax);

            // Order info
            $childOrder->setOrderCurrencyCode($parentOrder->getOrderCurrencyCode());
            $childOrder->setBaseCurrencyCode($parentOrder->getBaseCurrencyCode());
            $childOrder->setGlobalCurrencyCode($parentOrder->getGlobalCurrencyCode());
            $childOrder->setStoreCurrencyCode($parentOrder->getStoreCurrencyCode());
            $childOrder->setStoreToBaseRate($parentOrder->getStoreToBaseRate());
            $childOrder->setStoreToOrderRate($parentOrder->getStoreToOrderRate());
            $childOrder->setBaseToGlobalRate($parentOrder->getBaseToGlobalRate());
            $childOrder->setBaseToOrderRate($parentOrder->getBaseToOrderRate());
            $childOrder->setCouponCode($parentOrder->getCouponCode());
            $childOrder->setShippingMethod($parentOrder->getShippingMethod());
            $childOrder->setShippingDescription($parentOrder->getShippingDescription());
            $childOrder->setWeight(array_sum(array_map(fn($i) => (float)$i->getWeight() * (float)$i->getQtyOrdered(), $items)));
            $childOrder->setIsVirtual($parentOrder->getIsVirtual());

            // Status
            $childOrder->setState(Order::STATE_PROCESSING);
            $childOrder->setStatus('processing');

            // Split metadata
            $childOrder->setData('parent_order_id', $parentOrder->getEntityId());
            $childOrder->setData('is_parent', 0);
            $childOrder->setData('warehouse_code', $warehouseCode);
            $childOrder->setData('split_index', $splitIndex);
            $childOrder->setData('total_splits', $totalSplits);

            // Copy payment (just reference, no actual transaction)
            $parentPayment = $parentOrder->getPayment();
            if ($parentPayment) {
                $payment = $this->orderPaymentFactory->create();
                $payment->setMethod($parentPayment->getMethod());
                $payment->setAdditionalInformation($parentPayment->getAdditionalInformation());
                $payment->setAmountOrdered($childGrandTotal);
                $payment->setBaseAmountOrdered($childGrandTotal);
                $childOrder->setPayment($payment);
            }

            // Copy billing address
            if ($parentOrder->getBillingAddress()) {
                $billingAddress = $this->cloneAddress($parentOrder->getBillingAddress(), 'billing');
                $childOrder->setBillingAddress($billingAddress);
            }

            // Copy shipping address
            if ($parentOrder->getShippingAddress()) {
                $shippingAddress = $this->cloneAddress($parentOrder->getShippingAddress(), 'shipping');
                $childOrder->setShippingAddress($shippingAddress);
            }

            // Add items
            foreach ($items as $originalItem) {
                $childItem = $this->cloneOrderItem($originalItem);
                $childOrder->addItem($childItem);
            }

            // Save the order
            $this->orderRepository->save($childOrder);

            // Add comment
            $childOrder->addCommentToStatusHistory(
                __('Child order created from parent #%1 (Warehouse: %2, Split %3/%4)',
                    $parentOrder->getIncrementId(),
                    $warehouseCode,
                    $splitIndex,
                    $totalSplits
                )->render()
            );
            $this->orderRepository->save($childOrder);

            $this->logger->info('OrderSplit: Child order created successfully', [
                'parent_id' => $parentOrder->getIncrementId(),
                'child_id' => $childOrder->getIncrementId(),
                'warehouse' => $warehouseCode,
                'grand_total' => $childGrandTotal,
                'items_count' => count($items)
            ]);

            return $childOrder;

        } catch (\Exception $e) {
            $this->logger->error('OrderSplit: Failed to create child order', [
                'parent_id' => $parentOrder->getIncrementId(),
                'warehouse' => $warehouseCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } finally {
            $this->registry->unregister('order_split_creating_child');
        }
    }

    /**
     * Clone an order address
     */
    private function cloneAddress($sourceAddress, string $type): \Magento\Sales\Model\Order\Address
    {
        $address = $this->orderAddressFactory->create();
        $address->setAddressType($type);
        $address->setFirstname($sourceAddress->getFirstname());
        $address->setLastname($sourceAddress->getLastname());
        $address->setStreet($sourceAddress->getStreet());
        $address->setCity($sourceAddress->getCity());
        $address->setRegion($sourceAddress->getRegion());
        $address->setRegionId($sourceAddress->getRegionId());
        $address->setPostcode($sourceAddress->getPostcode());
        $address->setCountryId($sourceAddress->getCountryId());
        $address->setTelephone($sourceAddress->getTelephone());
        $address->setEmail($sourceAddress->getEmail());
        $address->setCompany($sourceAddress->getCompany());
        return $address;
    }

    /**
     * Clone an order item
     */
    private function cloneOrderItem(OrderItemInterface $original): \Magento\Sales\Model\Order\Item
    {
        $item = $this->orderItemFactory->create();
        $item->setProductId($original->getProductId());
        $item->setProductType($original->getProductType());
        $item->setSku($original->getSku());
        $item->setName($original->getName());
        $item->setQtyOrdered($original->getQtyOrdered());
        $item->setPrice($original->getPrice());
        $item->setBasePrice($original->getBasePrice());
        $item->setOriginalPrice($original->getOriginalPrice());
        $item->setBaseOriginalPrice($original->getBaseOriginalPrice());
        $item->setRowTotal($original->getRowTotal());
        $item->setBaseRowTotal($original->getBaseRowTotal());
        $item->setDiscountAmount($original->getDiscountAmount());
        $item->setBaseDiscountAmount($original->getBaseDiscountAmount());
        $item->setDiscountPercent($original->getDiscountPercent());
        $item->setTaxAmount($original->getTaxAmount());
        $item->setBaseTaxAmount($original->getBaseTaxAmount());
        $item->setTaxPercent($original->getTaxPercent());
        $item->setRowTotalInclTax($original->getRowTotalInclTax());
        $item->setBaseRowTotalInclTax($original->getBaseRowTotalInclTax());
        $item->setWeight($original->getWeight());
        $item->setStoreId($original->getStoreId());
        $item->setIsVirtual($original->getIsVirtual());
        $item->setProductOptions($original->getProductOptions());
        return $item;
    }
}
