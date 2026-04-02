<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Model\Service;

use CrocoIT\OrderSplit\Model\OrderStatusAggregator;
use CrocoIT\OrderSplit\Model\ParentOrderManager;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order\ShipmentFactory;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Framework\DB\TransactionFactory;
use Psr\Log\LoggerInterface;

class DeliveryNoteService
{
    private ParentOrderManager $parentOrderManager;
    private OrderStatusAggregator $statusAggregator;
    private OrderRepositoryInterface $orderRepository;
    private ShipmentFactory $shipmentFactory;
    private TrackFactory $trackFactory;
    private TransactionFactory $transactionFactory;
    private LoggerInterface $logger;

    public function __construct(
        ParentOrderManager $parentOrderManager,
        OrderStatusAggregator $statusAggregator,
        OrderRepositoryInterface $orderRepository,
        ShipmentFactory $shipmentFactory,
        TrackFactory $trackFactory,
        TransactionFactory $transactionFactory,
        LoggerInterface $logger
    ) {
        $this->parentOrderManager = $parentOrderManager;
        $this->statusAggregator = $statusAggregator;
        $this->orderRepository = $orderRepository;
        $this->shipmentFactory = $shipmentFactory;
        $this->trackFactory = $trackFactory;
        $this->transactionFactory = $transactionFactory;
        $this->logger = $logger;
    }

    /**
     * Process a Delivery Note from ERPNext
     *
     * Creates a shipment on the child order and updates parent status
     *
     * @param array $deliveryData ERPNext Delivery Note data
     * @return array
     */
    public function processDeliveryNote(array $deliveryData): array
    {
        $erpSoName = $deliveryData['against_sales_order'] ?? null;
        $trackingNumber = $deliveryData['tracking_number'] ?? null;
        $carrierCode = $deliveryData['carrier'] ?? 'custom';
        $carrierTitle = $deliveryData['carrier_title'] ?? 'Shipping';

        if (!$erpSoName) {
            // Try items to get the SO reference
            foreach ($deliveryData['items'] ?? [] as $item) {
                if (!empty($item['against_sales_order'])) {
                    $erpSoName = $item['against_sales_order'];
                    break;
                }
            }
        }

        if (!$erpSoName) {
            throw new \InvalidArgumentException('No Sales Order reference found in Delivery Note');
        }

        // Find child order
        $childOrder = $this->parentOrderManager->findChildByErpSoId($erpSoName);
        if (!$childOrder) {
            throw new \RuntimeException("Child order not found for ERPNext SO: {$erpSoName}");
        }

        $this->logger->info('OrderSplit: Processing delivery note', [
            'erp_so' => $erpSoName,
            'child_order' => $childOrder->getIncrementId(),
            'tracking' => $trackingNumber
        ]);

        // Create shipment on child order
        if (!$childOrder->canShip()) {
            $this->logger->warning('OrderSplit: Child order cannot be shipped', [
                'child_id' => $childOrder->getIncrementId(),
                'state' => $childOrder->getState()
            ]);

            return [
                'success' => false,
                'message' => 'Order cannot be shipped',
                'child_order' => $childOrder->getIncrementId()
            ];
        }

        $shipment = $this->createShipment($childOrder, $trackingNumber, $carrierCode, $carrierTitle);

        // Update parent order status
        $parentOrder = $this->parentOrderManager->getParentOrder($childOrder);
        if ($parentOrder) {
            $this->statusAggregator->updateParentStatus($parentOrder);

            // Copy tracking info to parent for customer display
            $parentOrder->addCommentToStatusHistory(
                __('Shipment created for warehouse %1. Tracking: %2',
                    $childOrder->getData('warehouse_code'),
                    $trackingNumber ?? 'N/A'
                )->render()
            );
            $this->orderRepository->save($parentOrder);
        }

        return [
            'success' => true,
            'message' => 'Shipment created',
            'child_order' => $childOrder->getIncrementId(),
            'shipment_id' => $shipment->getIncrementId(),
            'tracking_number' => $trackingNumber
        ];
    }

    /**
     * Create shipment for an order
     */
    private function createShipment($order, ?string $trackingNumber, string $carrierCode, string $carrierTitle)
    {
        $shipment = $this->shipmentFactory->create($order);

        // Add tracking if provided
        if ($trackingNumber) {
            $track = $this->trackFactory->create();
            $track->setCarrierCode($carrierCode);
            $track->setTitle($carrierTitle);
            $track->setTrackNumber($trackingNumber);
            $shipment->addTrack($track);
        }

        $shipment->register();

        $transaction = $this->transactionFactory->create();
        $transaction->addObject($shipment)
            ->addObject($order)
            ->save();

        $this->logger->info('OrderSplit: Shipment created', [
            'order_id' => $order->getIncrementId(),
            'shipment_id' => $shipment->getIncrementId(),
            'tracking' => $trackingNumber
        ]);

        return $shipment;
    }
}
