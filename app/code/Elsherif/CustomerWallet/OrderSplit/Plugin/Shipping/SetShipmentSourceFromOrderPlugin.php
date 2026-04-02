<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Plugin\Shipping;

use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Sales\Api\Data\ShipmentExtensionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Plugin to automatically set the shipment source code from the order's warehouse_code.
 *
 * When a child order has a warehouse_code set (from the order split process),
 * this plugin ensures that any shipment created for that order will use
 * the correct inventory source for deduction.
 *
 * This is critical for MSI to deduct inventory from the correct warehouse.
 */
class SetShipmentSourceFromOrderPlugin
{
    private OrderRepositoryInterface $orderRepository;
    private ShipmentExtensionFactory $shipmentExtensionFactory;
    private LoggerInterface $logger;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        ShipmentExtensionFactory $shipmentExtensionFactory,
        LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->shipmentExtensionFactory = $shipmentExtensionFactory;
        $this->logger = $logger;
    }

    /**
     * Before saving a shipment, set the source code from the order's warehouse_code.
     *
     * @param \Magento\Sales\Api\ShipmentRepositoryInterface $subject
     * @param ShipmentInterface $shipment
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSave(
        \Magento\Sales\Api\ShipmentRepositoryInterface $subject,
        ShipmentInterface $shipment
    ): array {
        // Only process new shipments (not updates)
        if ($shipment->getEntityId()) {
            return [$shipment];
        }

        // Check if source code is already set
        $extensionAttributes = $shipment->getExtensionAttributes();
        if ($extensionAttributes && $extensionAttributes->getSourceCode()) {
            // Source already set, don't override
            return [$shipment];
        }

        // Get the order's warehouse_code
        $orderId = $shipment->getOrderId();
        if (!$orderId) {
            return [$shipment];
        }

        try {
            $order = $this->orderRepository->get($orderId);
            $warehouseCode = $order->getData('warehouse_code');

            if ($warehouseCode) {
                // Create or get extension attributes
                if (!$extensionAttributes) {
                    $extensionAttributes = $this->shipmentExtensionFactory->create();
                }

                // Set the source code from warehouse_code
                $extensionAttributes->setSourceCode($warehouseCode);
                $shipment->setExtensionAttributes($extensionAttributes);

                $this->logger->info('OrderSplit: Set shipment source from order warehouse', [
                    'order_id' => $order->getIncrementId(),
                    'shipment_increment_id' => $shipment->getIncrementId(),
                    'source_code' => $warehouseCode
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('OrderSplit: Failed to set shipment source', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
        }

        return [$shipment];
    }
}
