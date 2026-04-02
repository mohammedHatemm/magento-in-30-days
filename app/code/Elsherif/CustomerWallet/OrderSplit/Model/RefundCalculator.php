<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Model;

use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

class RefundCalculator
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Calculate the refund amount for an amendment
     *
     * @param OrderInterface $originalChild The cancelled child order
     * @param array $newErpData The new ERPNext SO data
     * @return float Refund amount (positive = refund to customer, negative = charge more)
     */
    public function calculateRefund(OrderInterface $originalChild, array $newErpData): float
    {
        $originalTotal = (float)$originalChild->getGrandTotal();

        // Calculate new total from ERPNext data
        $newTotal = 0;
        foreach ($newErpData['items'] ?? [] as $item) {
            $qty = (float)($item['qty'] ?? 0);
            $rate = (float)($item['rate'] ?? 0);
            $newTotal += $qty * $rate;
        }

        $refund = $originalTotal - $newTotal;

        $this->logger->info('OrderSplit: Refund calculated', [
            'original_total' => $originalTotal,
            'new_total' => $newTotal,
            'refund_amount' => $refund,
            'order_id' => $originalChild->getIncrementId()
        ]);

        return round($refund, 2);
    }

    /**
     * Calculate total refund across all amendments in a chain
     */
    public function calculateNetRefund(OrderInterface $parentOrder, array $amendmentChain): float
    {
        $originalTotal = (float)$parentOrder->getGrandTotal();
        $currentActiveTotal = 0;

        // Sum up all currently active child order totals
        $childIds = json_decode($parentOrder->getData('child_order_ids') ?? '[]', true);

        // This would need to be passed the actual child orders
        // For now return 0 - the per-amendment refund is more practical
        return 0;
    }
}
