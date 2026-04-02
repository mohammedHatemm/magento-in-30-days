<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Plugin\GraphQl;

use CrocoIT\OrderSplit\Model\Config;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\SalesGraphQl\Model\Resolver\CustomerOrders;

/**
 * Plugin to filter out child orders from GraphQL customerOrders query.
 * For PWA/GraphQL frontend - customers should only see parent orders.
 */
class HideChildOrdersGraphQlPlugin
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * After resolve - filter out child orders from the result
     */
    public function afterResolve(
        CustomerOrders $subject,
        array $result,
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): array {
        if (!$this->config->isEnabled()) {
            return $result;
        }

        if (!isset($result['items']) || !is_array($result['items'])) {
            return $result;
        }

        // Filter out child orders (orders that have parent_order_id > 0)
        $filteredOrders = [];
        foreach ($result['items'] as $order) {
            // Check if this is a child order by looking at the order number pattern (contains "-")
            // Child orders have increment_id like "2000002111-1", "2000002111-2"
            $orderNumber = $order['number'] ?? '';
            
            // If order number contains "-" followed by a digit, it's a child order
            if (!preg_match('/-\d+$/', $orderNumber)) {
                $filteredOrders[] = $order;
            }
        }

        $result['items'] = $filteredOrders;
        $result['total_count'] = count($filteredOrders);

        return $result;
    }
}
