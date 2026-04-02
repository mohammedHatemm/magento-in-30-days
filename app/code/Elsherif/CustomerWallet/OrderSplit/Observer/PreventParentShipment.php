<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Observer;

use CrocoIT\OrderSplit\Model\ParentOrderManager;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;

class PreventParentShipment implements ObserverInterface
{
    private ParentOrderManager $parentOrderManager;

    public function __construct(ParentOrderManager $parentOrderManager)
    {
        $this->parentOrderManager = $parentOrderManager;
    }

    public function execute(Observer $observer): void
    {
        $shipment = $observer->getEvent()->getShipment();
        if (!$shipment) {
            return;
        }

        $order = $shipment->getOrder();
        if ($this->parentOrderManager->isParentOrder($order)) {
            throw new LocalizedException(
                __('Cannot create shipment on parent order. Create shipments on child orders instead.')
            );
        }
    }
}
