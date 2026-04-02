<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Block\Order\View;

use CrocoIT\OrderSplit\Model\ParentOrderManager;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Registry;

class Shipments extends Template
{
    protected $_template = 'CrocoIT_OrderSplit::order/view/shipments.phtml';

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

    public function getOrder()
    {
        return $this->registry->registry('current_order');
    }

    public function isParentOrder(): bool
    {
        $order = $this->getOrder();
        return $order ? $this->parentOrderManager->isParentOrder($order) : false;
    }

    /**
     * Get shipments grouped by warehouse from child orders
     */
    public function getShipmentsByWarehouse(): array
    {
        $order = $this->getOrder();
        if (!$order || !$this->isParentOrder()) {
            return [];
        }

        $result = [];
        $children = $this->parentOrderManager->getChildOrders($order);

        foreach ($children as $child) {
            $warehouse = $child->getData('warehouse_code') ?? 'Unknown';
            $shipments = $child->getShipmentsCollection();

            if ($shipments && $shipments->getSize() > 0) {
                foreach ($shipments as $shipment) {
                    $tracks = [];
                    foreach ($shipment->getAllTracks() as $track) {
                        $tracks[] = [
                            'carrier' => $track->getTitle(),
                            'number' => $track->getTrackNumber()
                        ];
                    }

                    $result[] = [
                        'warehouse' => $warehouse,
                        'child_order' => $child->getIncrementId(),
                        'shipment_id' => $shipment->getIncrementId(),
                        'status' => $child->getStatusLabel(),
                        'tracks' => $tracks
                    ];
                }
            } else {
                $result[] = [
                    'warehouse' => $warehouse,
                    'child_order' => $child->getIncrementId(),
                    'shipment_id' => null,
                    'status' => $child->getStatusLabel(),
                    'tracks' => []
                ];
            }
        }

        return $result;
    }
}
