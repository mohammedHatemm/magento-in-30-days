<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Observer;

use CrocoIT\OrderSplit\Model\OrderStatusAggregator;
use CrocoIT\OrderSplit\Model\ParentOrderManager;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class UpdateParentStatusObserver implements ObserverInterface
{
    private ParentOrderManager $parentOrderManager;
    private OrderStatusAggregator $statusAggregator;
    private LoggerInterface $logger;

    public function __construct(
        ParentOrderManager $parentOrderManager,
        OrderStatusAggregator $statusAggregator,
        LoggerInterface $logger
    ) {
        $this->parentOrderManager = $parentOrderManager;
        $this->statusAggregator = $statusAggregator;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();

        if (!$order || !$this->parentOrderManager->isChildOrder($order)) {
            return;
        }

        $parentOrder = $this->parentOrderManager->getParentOrder($order);
        if (!$parentOrder) {
            return;
        }

        try {
            $this->statusAggregator->updateParentStatus($parentOrder);
        } catch (\Exception $e) {
            $this->logger->error('OrderSplit: Failed to update parent status', [
                'child_id' => $order->getIncrementId(),
                'parent_id' => $parentOrder->getIncrementId(),
                'error' => $e->getMessage()
            ]);
        }
    }
}
