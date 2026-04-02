<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Model;

use CrocoIT\OrderSplit\Api\WebhookInterface;
use CrocoIT\OrderSplit\Model\Service\DeliveryNoteService;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Webapi\Rest\Request;
use Psr\Log\LoggerInterface;

class WebhookProcessor implements WebhookInterface
{
    private AmendmentHandler $amendmentHandler;
    private DeliveryNoteService $deliveryNoteService;
    private ParentOrderManager $parentOrderManager;
    private OrderStatusAggregator $statusAggregator;
    private \Magento\Sales\Api\OrderManagementInterface $orderManagement;
    private Request $request;
    private LoggerInterface $logger;

    public function __construct(
        AmendmentHandler $amendmentHandler,
        DeliveryNoteService $deliveryNoteService,
        ParentOrderManager $parentOrderManager,
        OrderStatusAggregator $statusAggregator,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        Request $request,
        LoggerInterface $logger
    ) {
        $this->amendmentHandler = $amendmentHandler;
        $this->deliveryNoteService = $deliveryNoteService;
        $this->parentOrderManager = $parentOrderManager;
        $this->statusAggregator = $statusAggregator;
        $this->orderManagement = $orderManagement;
        $this->request = $request;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function processAmendment($data = null)
    {
        $body = $data ?? json_decode($this->request->getContent(), true);
        return $this->amendmentHandler->handleAmendment($body);
    }

    /**
     * @inheritdoc
     */
    public function processCancellation($data = null)
    {
        $body = $data ?? json_decode($this->request->getContent(), true);
        $erpSoName = $body['name'] ?? null;

        if (!$erpSoName) {
            return ['success' => false, 'message' => 'Missing ERPNext SO name'];
        }

        $childOrder = $this->parentOrderManager->findChildByErpSoId($erpSoName);
        if (!$childOrder) {
            return ['success' => false, 'message' => "Child order not found for SO: {$erpSoName}"];
        }

        if ($childOrder->canCancel()) {
            $this->orderManagement->cancel($childOrder->getEntityId());
        }

        $parentOrder = $this->parentOrderManager->getParentOrder($childOrder);
        if ($parentOrder) {
            $this->parentOrderManager->removeChildFromParent($parentOrder, (int)$childOrder->getEntityId());
            $this->statusAggregator->updateParentStatus($parentOrder);
        }

        return [
            'success' => true,
            'message' => 'Child order cancelled',
            'child_order' => $childOrder->getIncrementId()
        ];
    }

    /**
     * @inheritdoc
     */
    public function processDeliveryNote($data = null)
    {
        $body = $data ?? json_decode($this->request->getContent(), true);
        return $this->deliveryNoteService->processDeliveryNote($body);
    }
}
