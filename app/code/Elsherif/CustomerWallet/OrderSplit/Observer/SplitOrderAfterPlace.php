<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Observer;

use CrocoIT\OrderSplit\Model\OrderSplitter;
use CrocoIT\ErpNextIntegration\Model\Config as ErpConfig;
use CrocoIT\ErpNextIntegration\Model\Queue\Publisher;
use CrocoIT\ErpNextIntegration\Model\Mapper\OrderDataMapper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use Psr\Log\LoggerInterface;

class SplitOrderAfterPlace implements ObserverInterface
{
    private OrderSplitter $orderSplitter;
    private Registry $registry;
    private LoggerInterface $logger;
    private ErpConfig $erpConfig;
    private Publisher $publisher;
    private OrderDataMapper $orderMapper;

    private static array $processedOrders = [];

    public function __construct(
        OrderSplitter $orderSplitter,
        Registry $registry,
        LoggerInterface $logger,
        ErpConfig $erpConfig,
        Publisher $publisher,
        OrderDataMapper $orderMapper
    ) {
        $this->orderSplitter = $orderSplitter;
        $this->registry = $registry;
        $this->logger = $logger;
        $this->erpConfig = $erpConfig;
        $this->publisher = $publisher;
        $this->orderMapper = $orderMapper;
    }

    public function execute(Observer $observer): void
    {
        // Skip if we're creating a child order (prevent infinite loop)
        if ($this->registry->registry('order_split_creating_child')) {
            return;
        }

        // Skip if ERPNext is creating an order (amendment flow)
        if ($this->registry->registry('erpnext_creating_order')) {
            return;
        }

        /** @var \Magento\Sales\Model\Order|null $order */
        $order = $observer->getEvent()->getOrder();

        // Fallback for checkout_submit_all_after
        if (!$order) {
            $orders = $observer->getEvent()->getOrders();
            if (is_array($orders) && !empty($orders)) {
                $order = reset($orders);
            }
        }

        if (!$order || !$order->getEntityId()) {
            return;
        }

        $orderId = (int)$order->getEntityId();

        // Prevent duplicate processing
        if (isset(self::$processedOrders[$orderId])) {
            return;
        }
        self::$processedOrders[$orderId] = true;

        try {
            $result = $this->orderSplitter->splitOrder($order);

            if (!empty($result['children'])) {
                $this->logger->info('OrderSplit: Order split by observer', [
                    'parent' => $order->getIncrementId(),
                    'children' => count($result['children'])
                ]);

                // Publish child orders to ERP queue directly
                // This ensures children are synced even if OrderPlaceAfter runs before this observer
                $this->publishChildOrdersToErp($result['children']);
            }
        } catch (\Exception $e) {
            $this->logger->error('OrderSplit: Split failed in observer', [
                'order_id' => $order->getIncrementId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Publish child orders to ERP queue
     *
     * @param array $childOrders
     */
    private function publishChildOrdersToErp(array $childOrders): void
    {
        // Check if ERP sync is enabled
        if (!$this->erpConfig->isEnabled() || !$this->erpConfig->isOrderSyncEnabled()) {
            $this->logger->info('OrderSplit: ERP sync disabled, skipping child order publish');
            return;
        }

        foreach ($childOrders as $childOrder) {
            try {
                $orderData = $this->orderMapper->mapToErpNext($childOrder);

                $this->logger->info('OrderSplit: Publishing child order to ERP queue', [
                    'child_order_id' => $childOrder->getEntityId(),
                    'increment_id' => $childOrder->getIncrementId(),
                    'warehouse' => $childOrder->getData('warehouse_code')
                ]);

                $this->publisher->publishOrderCreate((int)$childOrder->getEntityId(), $orderData);
            } catch (\Exception $e) {
                $this->logger->error('OrderSplit: Failed to publish child order to ERP', [
                    'child_order_id' => $childOrder->getEntityId(),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
