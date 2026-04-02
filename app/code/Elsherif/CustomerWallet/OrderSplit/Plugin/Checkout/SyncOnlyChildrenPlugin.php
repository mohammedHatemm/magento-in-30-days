<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Plugin\Checkout;

use CrocoIT\OrderSplit\Model\Config;
use CrocoIT\OrderSplit\Model\ParentOrderManager;
use CrocoIT\ErpNextIntegration\Observer\OrderPlaceAfter;
use Magento\Framework\Event\Observer;
use Psr\Log\LoggerInterface;

/**
 * Plugin to prevent ERP sync for parent orders.
 * Only child orders should be synced to ERPNext.
 */
class SyncOnlyChildrenPlugin
{
    private Config $config;
    private ParentOrderManager $parentOrderManager;
    private LoggerInterface $logger;

    public function __construct(
        Config $config,
        ParentOrderManager $parentOrderManager,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->parentOrderManager = $parentOrderManager;
        $this->logger = $logger;
    }

    /**
     * Skip ERP sync for parent orders - only child orders should be synced
     */
    public function aroundExecute(OrderPlaceAfter $subject, callable $proceed, Observer $observer): void
    {
        if (!$this->config->isEnabled()) {
            $proceed($observer);
            return;
        }

        $order = $observer->getEvent()->getOrder();
        if (!$order) {
            $orders = $observer->getEvent()->getOrders();
            if (is_array($orders) && !empty($orders)) {
                $order = reset($orders);
            }
        }

        if (!$order) {
            $proceed($observer);
            return;
        }

        // If this is a parent order, skip ERP sync
        if ($this->parentOrderManager->isParentOrder($order)) {
            $this->logger->info('OrderSplit: Skipping ERP sync for parent order', [
                'order_id' => $order->getIncrementId()
            ]);
            return;
        }

        // For child orders and regular orders, proceed with normal sync
        $proceed($observer);
    }
}
