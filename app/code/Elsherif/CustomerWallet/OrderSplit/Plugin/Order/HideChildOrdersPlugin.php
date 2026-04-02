<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Plugin\Order;

use CrocoIT\OrderSplit\Model\Config;
use Magento\Sales\Model\ResourceModel\Order\Collection;

/**
 * Hide child orders from customer's "My Orders" page on frontend.
 * Customer should only see parent orders (orders without parent_order_id).
 */
class HideChildOrdersPlugin
{
    private Config $config;
    private bool $filterApplied = false;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Filter out child orders from frontend order history
     * Using afterAddFieldToFilter to ensure our filter is applied after customer_id filter
     */
    public function afterAddFieldToFilter(Collection $subject, Collection $result, $field, $condition = null)
    {
        // Only apply filter once, and only when customer_id filter is being added (order history page)
        if ($this->filterApplied || !$this->config->isEnabled()) {
            return $result;
        }

        // Check if this is the customer_id filter (indicates order history page)
        if ($field === 'customer_id') {
            $this->filterApplied = true;
            
            // Filter out child orders (orders that have a parent_order_id > 0)
            // Show only orders where:
            // - parent_order_id IS NULL (normal orders)
            // - OR parent_order_id = 0 (parent orders after split)
            $result->getSelect()->where(
                '(main_table.parent_order_id IS NULL OR main_table.parent_order_id = 0)'
            );
        }

        return $result;
    }

    /**
     * Reset flag when collection is cleared
     */
    public function afterClear(Collection $subject, Collection $result)
    {
        $this->filterApplied = false;
        return $result;
    }
}
