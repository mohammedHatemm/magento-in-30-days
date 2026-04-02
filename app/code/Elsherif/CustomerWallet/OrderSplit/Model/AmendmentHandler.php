<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Model;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Psr\Log\LoggerInterface;

class AmendmentHandler
{
    private ParentOrderManager $parentOrderManager;
    private ChildOrderCreator $childOrderCreator;
    private RefundCalculator $refundCalculator;
    private OrderStatusAggregator $statusAggregator;
    private OrderRepositoryInterface $orderRepository;
    private OrderManagementInterface $orderManagement;
    private LoggerInterface $logger;

    public function __construct(
        ParentOrderManager $parentOrderManager,
        ChildOrderCreator $childOrderCreator,
        RefundCalculator $refundCalculator,
        OrderStatusAggregator $statusAggregator,
        OrderRepositoryInterface $orderRepository,
        OrderManagementInterface $orderManagement,
        LoggerInterface $logger
    ) {
        $this->parentOrderManager = $parentOrderManager;
        $this->childOrderCreator = $childOrderCreator;
        $this->refundCalculator = $refundCalculator;
        $this->statusAggregator = $statusAggregator;
        $this->orderRepository = $orderRepository;
        $this->orderManagement = $orderManagement;
        $this->logger = $logger;
    }

    /**
     * Handle an amended Sales Order from ERPNext
     *
     * Flow:
     * 1. Find original child order linked to amended_from SO
     * 2. Determine what changed (qty_reduced, item_removed, etc.)
     * 3. Cancel old child order
     * 4. Create new child order with updated data
     * 5. Link new child to parent and new ERPNext SO
     * 6. Process refund if needed
     *
     * @param array $erpNextData New ERPNext SO data with 'amended_from' field
     * @return array Result
     */
    public function handleAmendment(array $erpNextData): array
    {
        $newSoName = $erpNextData['name'] ?? null;
        $amendedFrom = $erpNextData['amended_from'] ?? null;

        if (!$newSoName || !$amendedFrom) {
            throw new \InvalidArgumentException('Missing name or amended_from in ERPNext data');
        }

        $this->logger->info('OrderSplit: Processing amendment', [
            'new_so' => $newSoName,
            'amended_from' => $amendedFrom
        ]);

        // Find the original child order
        $originalChild = $this->parentOrderManager->findChildByErpSoId($amendedFrom);
        if (!$originalChild) {
            throw new \RuntimeException("Child order not found for ERPNext SO: {$amendedFrom}");
        }

        $parentOrder = $this->parentOrderManager->getParentOrder($originalChild);
        if (!$parentOrder) {
            throw new \RuntimeException("Parent order not found for child: {$originalChild->getIncrementId()}");
        }

        // Detect amendment type
        $changeType = $this->detectChangeType($originalChild, $erpNextData);

        $this->logger->info('OrderSplit: Amendment type detected', [
            'change_type' => $changeType,
            'original_child' => $originalChild->getIncrementId()
        ]);

        // Cancel old child order
        if ($originalChild->canCancel()) {
            $this->orderManagement->cancel($originalChild->getEntityId());
            $this->logger->info('OrderSplit: Original child cancelled', [
                'child_id' => $originalChild->getIncrementId()
            ]);
        }

        // Remove old child from parent
        $this->parentOrderManager->removeChildFromParent($parentOrder, (int)$originalChild->getEntityId());

        // Build items from ERPNext data
        $warehouseCode = $originalChild->getData('warehouse_code') ?? 'default';
        $splitIndex = (int)$originalChild->getData('split_index');
        $totalSplits = (int)$parentOrder->getData('total_splits');

        // Create new child order from ERPNext items
        $newChildItems = $this->buildItemsFromErpData($erpNextData, $parentOrder);

        if (!empty($newChildItems)) {
            $newChild = $this->childOrderCreator->createChildOrder(
                $parentOrder,
                $newChildItems,
                $warehouseCode,
                $splitIndex,
                $totalSplits
            );

            // Link to new ERPNext SO
            $newChild->setData('erp_sales_order_id', $newSoName);
            $newChild->setData('amended_from_child_id', $originalChild->getEntityId());
            $this->orderRepository->save($newChild);

            // Add new child to parent
            $this->parentOrderManager->addChildToParent($parentOrder, (int)$newChild->getEntityId());

            // Update amendment chain on parent
            $this->updateAmendmentChain($parentOrder, $amendedFrom, $newSoName, $originalChild, $newChild);
        }

        // Calculate and process refund if needed
        $refundAmount = $this->refundCalculator->calculateRefund($originalChild, $erpNextData);

        // Update parent status
        $this->statusAggregator->updateParentStatus($parentOrder);

        $result = [
            'success' => true,
            'change_type' => $changeType,
            'original_child' => $originalChild->getIncrementId(),
            'new_child' => isset($newChild) ? $newChild->getIncrementId() : null,
            'refund_amount' => $refundAmount,
            'parent_order' => $parentOrder->getIncrementId()
        ];

        $this->logger->info('OrderSplit: Amendment processed', $result);
        return $result;
    }

    /**
     * Detect what kind of amendment was made
     */
    private function detectChangeType(OrderInterface $originalChild, array $erpNextData): string
    {
        $originalItems = [];
        foreach ($originalChild->getItems() as $item) {
            if (!$item->getData('has_children')) {
                $originalItems[$item->getSku()] = (float)$item->getQtyOrdered();
            }
        }

        $newItems = [];
        foreach ($erpNextData['items'] ?? [] as $erpItem) {
            $sku = $erpItem['item_code'] ?? '';
            $qty = (float)($erpItem['qty'] ?? 0);
            if ($sku) {
                $newItems[$sku] = $qty;
            }
        }

        $removedSkus = array_diff_key($originalItems, $newItems);
        $addedSkus = array_diff_key($newItems, $originalItems);

        if (!empty($removedSkus) && empty($addedSkus)) {
            return 'item_removed';
        }
        if (!empty($addedSkus) && empty($removedSkus)) {
            return 'item_added';
        }

        foreach ($originalItems as $sku => $qty) {
            if (isset($newItems[$sku]) && $newItems[$sku] < $qty) {
                return 'qty_reduced';
            }
            if (isset($newItems[$sku]) && $newItems[$sku] > $qty) {
                return 'qty_increased';
            }
        }

        return 'modified';
    }

    /**
     * Build Magento order items from ERPNext SO items data
     * Returns items from parent order that match the ERPNext items
     */
    private function buildItemsFromErpData(array $erpNextData, OrderInterface $parentOrder): array
    {
        $erpItems = $erpNextData['items'] ?? [];
        $matchedItems = [];

        foreach ($erpItems as $erpItem) {
            $sku = $erpItem['item_code'] ?? '';
            // Skip shipping item
            if ($sku === 'ISG012583') {
                continue;
            }

            foreach ($parentOrder->getItems() as $orderItem) {
                if ($orderItem->getSku() === $sku && !$orderItem->getData('has_children')) {
                    $matchedItems[] = $orderItem;
                    break;
                }
            }
        }

        return $matchedItems;
    }

    /**
     * Track amendment history on parent order
     */
    private function updateAmendmentChain(
        OrderInterface $parentOrder,
        string $oldSoName,
        string $newSoName,
        OrderInterface $oldChild,
        OrderInterface $newChild
    ): void {
        $chain = json_decode($parentOrder->getData('amendment_chain') ?? '[]', true);
        if (!is_array($chain)) {
            $chain = [];
        }

        $chain[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'old_erp_so' => $oldSoName,
            'new_erp_so' => $newSoName,
            'old_child_id' => $oldChild->getIncrementId(),
            'new_child_id' => $newChild->getIncrementId()
        ];

        $parentOrder->setData('amendment_chain', json_encode($chain));
        $this->orderRepository->save($parentOrder);
    }
}
