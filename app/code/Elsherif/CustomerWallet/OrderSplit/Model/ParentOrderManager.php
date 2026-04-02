<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Model;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;

class ParentOrderManager
{
    private OrderRepositoryInterface $orderRepository;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private LoggerInterface $logger;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
    }

    /**
     * Mark order as parent and store child IDs
     */
    public function markAsParent(OrderInterface $order, array $childOrderIds): void
    {
        $order->setData('is_parent', 1);
        $order->setData('child_order_ids', json_encode($childOrderIds));
        $order->setData('total_splits', count($childOrderIds));
        $this->orderRepository->save($order);

        $this->logger->info('OrderSplit: Order marked as parent', [
            'parent_id' => $order->getIncrementId(),
            'child_count' => count($childOrderIds),
            'child_ids' => $childOrderIds
        ]);
    }

    /**
     * Add a child order ID to the parent's list
     */
    public function addChildToParent(OrderInterface $parentOrder, int $childEntityId): void
    {
        $existingIds = $this->getChildOrderIds($parentOrder);
        if (!in_array($childEntityId, $existingIds)) {
            $existingIds[] = $childEntityId;
            $parentOrder->setData('child_order_ids', json_encode($existingIds));
            $parentOrder->setData('total_splits', count($existingIds));
            $this->orderRepository->save($parentOrder);
        }
    }

    /**
     * Remove a child order ID from the parent's list
     */
    public function removeChildFromParent(OrderInterface $parentOrder, int $childEntityId): void
    {
        $existingIds = $this->getChildOrderIds($parentOrder);
        $existingIds = array_values(array_filter($existingIds, fn($id) => (int)$id !== $childEntityId));
        $parentOrder->setData('child_order_ids', json_encode($existingIds));
        $parentOrder->setData('total_splits', count($existingIds));
        $this->orderRepository->save($parentOrder);
    }

    /**
     * Get parent order for a child
     */
    public function getParentOrder(OrderInterface $childOrder): ?OrderInterface
    {
        $parentId = $childOrder->getData('parent_order_id');
        if (!$parentId) {
            return null;
        }

        try {
            return $this->orderRepository->get((int)$parentId);
        } catch (\Exception $e) {
            $this->logger->error('OrderSplit: Failed to load parent order', [
                'parent_id' => $parentId,
                'child_id' => $childOrder->getIncrementId()
            ]);
            return null;
        }
    }

    /**
     * Get all child orders for a parent
     *
     * @return OrderInterface[]
     */
    public function getChildOrders(OrderInterface $parentOrder): array
    {
        $childIds = $this->getChildOrderIds($parentOrder);
        if (empty($childIds)) {
            return [];
        }

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $childIds, 'in')
            ->create();

        $result = $this->orderRepository->getList($searchCriteria);
        return $result->getItems();
    }

    /**
     * Find child order linked to an ERPNext SO
     */
    public function findChildByErpSoId(string $erpSoId): ?OrderInterface
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('erp_sales_order_id', $erpSoId)
            ->create();

        $result = $this->orderRepository->getList($searchCriteria);
        if ($result->getTotalCount() > 0) {
            $items = $result->getItems();
            return reset($items);
        }

        return null;
    }

    /**
     * Check if order is a parent order
     */
    public function isParentOrder(OrderInterface $order): bool
    {
        return (bool)$order->getData('is_parent');
    }

    /**
     * Check if order is a child order
     */
    public function isChildOrder(OrderInterface $order): bool
    {
        return (bool)$order->getData('parent_order_id');
    }

    public function getChildOrderIds(OrderInterface $parentOrder): array
    {
        $json = $parentOrder->getData('child_order_ids');
        if (!$json) {
            return [];
        }
        $ids = json_decode($json, true);
        return is_array($ids) ? $ids : [];
    }
}
