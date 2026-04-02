<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Model;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class OrderSplitter
{
    private Config $config;
    private WarehouseResolver $warehouseResolver;
    private ChildOrderCreator $childOrderCreator;
    private ParentOrderManager $parentOrderManager;
    private ReservationCompensator $reservationCompensator;
    private OrderRepositoryInterface $orderRepository;
    private LoggerInterface $logger;

    public function __construct(
        Config $config,
        WarehouseResolver $warehouseResolver,
        ChildOrderCreator $childOrderCreator,
        ParentOrderManager $parentOrderManager,
        ReservationCompensator $reservationCompensator,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->warehouseResolver = $warehouseResolver;
        $this->childOrderCreator = $childOrderCreator;
        $this->parentOrderManager = $parentOrderManager;
        $this->reservationCompensator = $reservationCompensator;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    /**
     * Split an order by warehouse
     *
     * @param OrderInterface $order
     * @return array ['parent' => OrderInterface, 'children' => OrderInterface[]]
     */
    public function splitOrder(OrderInterface $order): array
    {
        if (!$this->config->isEnabled($order->getStoreId())) {
            $this->logger->debug('OrderSplit: Module disabled, skipping split');
            return ['parent' => $order, 'children' => []];
        }

        // Don't split child orders or already-parent orders
        if ($order->getData('parent_order_id') || $order->getData('is_parent')) {
            $this->logger->debug('OrderSplit: Order already split/child, skipping', [
                'order_id' => $order->getIncrementId()
            ]);
            return ['parent' => $order, 'children' => []];
        }

        // Group items by warehouse
        $warehouseGroups = $this->warehouseResolver->groupItemsByWarehouse($order);

        $warehouseCount = count($warehouseGroups);

        // If single warehouse and config says don't split single, still create parent-child
        if ($warehouseCount <= 1 && !$this->config->shouldSplitSingleWarehouse($order->getStoreId())) {
            $this->logger->info('OrderSplit: Single warehouse, no split needed', [
                'order_id' => $order->getIncrementId()
            ]);
            return ['parent' => $order, 'children' => []];
        }

        $this->logger->info('OrderSplit: Starting order split', [
            'order_id' => $order->getIncrementId(),
            'warehouse_count' => $warehouseCount,
            'warehouses' => array_keys($warehouseGroups)
        ]);

        // Create child orders
        $childOrders = [];
        $childEntityIds = [];
        $splitIndex = 1;

        foreach ($warehouseGroups as $warehouseCode => $items) {
            try {
                $childOrder = $this->childOrderCreator->createChildOrder(
                    $order,
                    $items,
                    $warehouseCode,
                    $splitIndex,
                    $warehouseCount
                );

                $childOrders[] = $childOrder;
                $childEntityIds[] = (int)$childOrder->getEntityId();
                $splitIndex++;

            } catch (\Exception $e) {
                $this->logger->error('OrderSplit: Failed to create child order', [
                    'parent_id' => $order->getIncrementId(),
                    'warehouse' => $warehouseCode,
                    'error' => $e->getMessage()
                ]);
                // Continue with other warehouses - partial split is better than no split
            }
        }

        if (empty($childOrders)) {
            $this->logger->error('OrderSplit: No child orders created, order not split', [
                'order_id' => $order->getIncrementId()
            ]);
            return ['parent' => $order, 'children' => []];
        }

        // Mark original order as parent
        $this->parentOrderManager->markAsParent($order, $childEntityIds);

        // CRITICAL: Create compensation reservations for the parent order.
        // This cancels out the parent's negative reservations since child orders
        // will manage inventory independently. Without this, we'd have double reservations.
        $this->reservationCompensator->compensateParentReservations($order, $childOrders);

        $this->logger->info('OrderSplit: Order split complete', [
            'parent_id' => $order->getIncrementId(),
            'children_count' => count($childOrders),
            'children' => array_map(fn($c) => $c->getIncrementId(), $childOrders)
        ]);

        return [
            'parent' => $order,
            'children' => $childOrders
        ];
    }
}
