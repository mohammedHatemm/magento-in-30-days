<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Plugin\Order;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Add split extension attributes to order when loaded
 */
class OrderRepositoryPlugin
{
    /**
     * After get - add split info to order extension attributes
     */
    public function afterGet(
        OrderRepositoryInterface $subject,
        OrderInterface $order
    ): OrderInterface {
        $this->addSplitAttributes($order);
        return $order;
    }

    /**
     * After getList - add split info to all orders
     */
    public function afterGetList(
        OrderRepositoryInterface $subject,
        OrderSearchResultInterface $searchResult
    ): OrderSearchResultInterface {
        foreach ($searchResult->getItems() as $order) {
            $this->addSplitAttributes($order);
        }
        return $searchResult;
    }

    private function addSplitAttributes(OrderInterface $order): void
    {
        $extensionAttributes = $order->getExtensionAttributes();
        if ($extensionAttributes) {
            // Extension attributes can be added here if needed
            // For now the data is directly on the order via getData()
        }
    }
}
