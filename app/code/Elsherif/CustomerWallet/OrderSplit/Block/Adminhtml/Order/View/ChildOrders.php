<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Block\Adminhtml\Order\View;

use CrocoIT\OrderSplit\Model\ParentOrderManager;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface;

class ChildOrders extends Template
{
    protected $_template = 'CrocoIT_OrderSplit::order/view/child_orders.phtml';

    private Registry $registry;
    private ParentOrderManager $parentOrderManager;

    public function __construct(
        Context $context,
        Registry $registry,
        ParentOrderManager $parentOrderManager,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->parentOrderManager = $parentOrderManager;
        parent::__construct($context, $data);
    }

    public function getOrder(): ?OrderInterface
    {
        return $this->registry->registry('current_order');
    }

    public function isParentOrder(): bool
    {
        $order = $this->getOrder();
        return $order ? $this->parentOrderManager->isParentOrder($order) : false;
    }

    public function isChildOrder(): bool
    {
        $order = $this->getOrder();
        return $order ? $this->parentOrderManager->isChildOrder($order) : false;
    }

    /**
     * @return OrderInterface[]
     */
    public function getChildOrders(): array
    {
        $order = $this->getOrder();
        if (!$order || !$this->isParentOrder()) {
            return [];
        }
        return $this->parentOrderManager->getChildOrders($order);
    }

    public function getParentOrder(): ?OrderInterface
    {
        $order = $this->getOrder();
        if (!$order || !$this->isChildOrder()) {
            return null;
        }
        return $this->parentOrderManager->getParentOrder($order);
    }

    public function getOrderViewUrl(int $entityId): string
    {
        return $this->getUrl('sales/order/view', ['order_id' => $entityId]);
    }

    public function getAmendmentChain(): array
    {
        $order = $this->getOrder();
        if (!$order) {
            return [];
        }
        $chain = json_decode($order->getData('amendment_chain') ?? '[]', true);
        return is_array($chain) ? $chain : [];
    }
}
