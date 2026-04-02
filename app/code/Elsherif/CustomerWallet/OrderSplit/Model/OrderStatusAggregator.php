<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Model;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class OrderStatusAggregator
{
    private OrderRepositoryInterface $orderRepository;
    private LoggerInterface $logger;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    /**
     * Recalculate parent order status based on all child orders
     */
    public function updateParentStatus(Order $parentOrder): void
    {
        $childIds = $this->getChildOrderIds($parentOrder);
        if (empty($childIds)) {
            return;
        }

        $statuses = [];
        foreach ($childIds as $childId) {
            try {
                $child = $this->orderRepository->get((int)$childId);
                $statuses[] = $child->getState();
            } catch (\Exception $e) {
                $this->logger->warning('OrderSplit: Could not load child order', [
                    'child_id' => $childId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        if (empty($statuses)) {
            return;
        }

        $newState = $this->calculateParentState($statuses);
        $newStatus = $this->mapStateToStatus($newState);

        if ($parentOrder->getState() !== $newState) {
            $parentOrder->setState($newState);
            $parentOrder->setStatus($newStatus);
            $this->orderRepository->save($parentOrder);

            $this->logger->info('OrderSplit: Parent order status updated', [
                'parent_id' => $parentOrder->getIncrementId(),
                'new_state' => $newState,
                'child_states' => $statuses
            ]);
        }
    }

    /**
     * Calculate parent state from child states
     */
    private function calculateParentState(array $childStates): string
    {
        $allCanceled = true;
        $allComplete = true;
        $allClosed = true;
        $anyShipped = false;
        $anyProcessing = false;

        foreach ($childStates as $state) {
            if ($state !== Order::STATE_CANCELED) {
                $allCanceled = false;
            }
            if ($state !== Order::STATE_COMPLETE) {
                $allComplete = false;
            }
            if ($state !== Order::STATE_CLOSED) {
                $allClosed = false;
            }
            if ($state === Order::STATE_COMPLETE) {
                $anyShipped = true;
            }
            if ($state === Order::STATE_PROCESSING) {
                $anyProcessing = true;
            }
        }

        if ($allCanceled) {
            return Order::STATE_CANCELED;
        }
        if ($allComplete) {
            return Order::STATE_COMPLETE;
        }
        if ($allClosed) {
            return Order::STATE_CLOSED;
        }
        if ($anyShipped && $anyProcessing) {
            return Order::STATE_PROCESSING; // Partially shipped
        }

        return Order::STATE_PROCESSING;
    }

    private function mapStateToStatus(string $state): string
    {
        return match ($state) {
            Order::STATE_COMPLETE => 'complete',
            Order::STATE_CANCELED => 'canceled',
            Order::STATE_CLOSED => 'closed',
            default => 'processing'
        };
    }

    private function getChildOrderIds(Order $parentOrder): array
    {
        $childIdsJson = $parentOrder->getData('child_order_ids');
        if (!$childIdsJson) {
            return [];
        }

        $ids = json_decode($childIdsJson, true);
        return is_array($ids) ? $ids : [];
    }
}
