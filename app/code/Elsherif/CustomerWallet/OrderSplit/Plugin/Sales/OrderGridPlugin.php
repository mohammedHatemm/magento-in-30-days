<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Plugin\Sales;

use Magento\Sales\Model\ResourceModel\Order\Grid\Collection;

/**
 * Hide child orders from the default order grid (optional)
 * By default shows all - can be toggled by admin
 */
class OrderGridPlugin
{
    /**
     * Add split columns to grid collection
     * Note: We do NOT hide child orders from grid - admin needs to see them all
     * The IsParent column makes it clear which are parents vs children
     */
    public function afterGetSelect(Collection $subject, $result)
    {
        // Columns are already added via db_schema.xml on sales_order_grid table
        // No additional select manipulation needed
        return $result;
    }
}
