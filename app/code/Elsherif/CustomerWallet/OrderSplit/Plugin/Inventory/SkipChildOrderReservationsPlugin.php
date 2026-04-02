<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Plugin\Inventory;

use Magento\Framework\Registry;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface;
use Psr\Log\LoggerInterface;

/**
 * Plugin to prevent reservation creation for child orders during order split.
 *
 * When splitting an order, the parent order already has reservations.
 * We create compensation reservations for the parent (via ReservationCompensator),
 * but we must ensure NO new reservations are created for child orders
 * during their creation process.
 *
 * This plugin acts as a safety net to skip reservation placement when
 * the 'order_split_creating_child' registry flag is set.
 */
class SkipChildOrderReservationsPlugin
{
    private Registry $registry;
    private LoggerInterface $logger;

    public function __construct(
        Registry $registry,
        LoggerInterface $logger
    ) {
        $this->registry = $registry;
        $this->logger = $logger;
    }

    /**
     * Skip reservation placement when creating child orders during split.
     *
     * @param PlaceReservationsForSalesEventInterface $subject
     * @param callable $proceed
     * @param array $items
     * @param SalesChannelInterface $salesChannel
     * @param SalesEventInterface $salesEvent
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        PlaceReservationsForSalesEventInterface $subject,
        callable $proceed,
        array $items,
        SalesChannelInterface $salesChannel,
        SalesEventInterface $salesEvent
    ): void {
        // Skip reservations when creating child orders
        if ($this->registry->registry('order_split_creating_child')) {
            $this->logger->debug('OrderSplit: Skipping reservations for child order creation', [
                'event_type' => $salesEvent->getType(),
                'items_count' => count($items)
            ]);
            return;
        }

        // Proceed with normal reservation placement
        $proceed($items, $salesChannel, $salesEvent);
    }
}
