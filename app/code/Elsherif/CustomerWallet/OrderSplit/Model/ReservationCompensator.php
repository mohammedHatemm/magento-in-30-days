<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Model;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\InventoryReservationsApi\Model\AppendReservationsInterface;
use Magento\InventoryReservationsApi\Model\ReservationBuilderInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles reservation compensation when splitting orders.
 *
 * When a parent order is split into child orders, we need to:
 * 1. Create compensation reservations (+qty) for the parent order
 *    to cancel out the original negative reservations
 *
 * This prevents double-counting because:
 * - Parent order placed: -5 qty reservation
 * - Compensation: +5 qty reservation (cancels parent)
 * - Child orders handle their own reservations when shipped
 */
class ReservationCompensator
{
    private AppendReservationsInterface $appendReservations;
    private ReservationBuilderInterface $reservationBuilder;
    private StockByWebsiteIdResolverInterface $stockByWebsiteId;
    private SerializerInterface $serializer;
    private LoggerInterface $logger;

    public function __construct(
        AppendReservationsInterface $appendReservations,
        ReservationBuilderInterface $reservationBuilder,
        StockByWebsiteIdResolverInterface $stockByWebsiteId,
        SerializerInterface $serializer,
        LoggerInterface $logger
    ) {
        $this->appendReservations = $appendReservations;
        $this->reservationBuilder = $reservationBuilder;
        $this->stockByWebsiteId = $stockByWebsiteId;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    /**
     * Create compensation reservations for the parent order after split.
     *
     * This adds positive reservations to cancel the parent order's negative reservations,
     * since the child orders will manage inventory from this point forward.
     *
     * @param OrderInterface $parentOrder The parent order that was split
     * @param array $childOrders Array of child OrderInterface objects
     * @return void
     */
    public function compensateParentReservations(OrderInterface $parentOrder, array $childOrders): void
    {
        if (empty($childOrders)) {
            return;
        }

        try {
            $websiteId = (int)$parentOrder->getStore()->getWebsiteId();
            $stock = $this->stockByWebsiteId->execute($websiteId);
            $stockId = (int)$stock->getStockId();

            $reservations = [];

            /** @var \Magento\Sales\Model\Order\Item $item */
            foreach ($parentOrder->getItems() as $item) {
                // Skip parent items (configurable/bundle parents) and virtual items
                if ($item->getParentItem() || $item->getIsVirtual()) {
                    continue;
                }

                $sku = $item->getSku();
                $qty = (float)$item->getQtyOrdered();

                if ($qty <= 0) {
                    continue;
                }

                // Create positive reservation to compensate the parent's negative reservation
                $metadata = $this->serializer->serialize([
                    'event_type' => 'order_split_compensation',
                    'object_type' => 'order',
                    'object_id' => (string)$parentOrder->getEntityId(),
                    'object_increment_id' => $parentOrder->getIncrementId(),
                    'child_order_ids' => array_map(
                        fn($child) => $child->getIncrementId(),
                        $childOrders
                    )
                ]);

                $reservations[] = $this->reservationBuilder
                    ->setStockId($stockId)
                    ->setSku($sku)
                    ->setQuantity($qty) // Positive = compensation (restores salable qty)
                    ->setMetadata($metadata)
                    ->build();

                $this->logger->debug('OrderSplit: Created compensation reservation', [
                    'parent_order' => $parentOrder->getIncrementId(),
                    'sku' => $sku,
                    'qty' => $qty,
                    'stock_id' => $stockId
                ]);
            }

            if (!empty($reservations)) {
                $this->appendReservations->execute($reservations);

                $this->logger->info('OrderSplit: Compensation reservations created', [
                    'parent_order' => $parentOrder->getIncrementId(),
                    'reservation_count' => count($reservations),
                    'child_orders' => array_map(fn($c) => $c->getIncrementId(), $childOrders)
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->error('OrderSplit: Failed to create compensation reservations', [
                'parent_order' => $parentOrder->getIncrementId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Don't throw - the order split should still complete
            // The reservation mismatch can be corrected manually if needed
        }
    }
}
