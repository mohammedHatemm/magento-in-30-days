<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Controller\Webhook;

use CrocoIT\OrderSplit\Model\ParentOrderManager;
use CrocoIT\OrderSplit\Model\OrderStatusAggregator;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class SalesOrderCancel implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private RequestInterface $request;
    private JsonFactory $jsonFactory;
    private ParentOrderManager $parentOrderManager;
    private OrderStatusAggregator $statusAggregator;
    private OrderManagementInterface $orderManagement;
    private OrderRepositoryInterface $orderRepository;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        ParentOrderManager $parentOrderManager,
        OrderStatusAggregator $statusAggregator,
        OrderManagementInterface $orderManagement,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->parentOrderManager = $parentOrderManager;
        $this->statusAggregator = $statusAggregator;
        $this->orderManagement = $orderManagement;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $body = json_decode($this->request->getContent(), true);
            $erpSoName = $body['name'] ?? null;
            $reason = $body['custom_cancellation_reason'] ?? 'customer_request';

            if (!$erpSoName) {
                return $result->setData([
                    'success' => false,
                    'message' => 'Missing ERPNext SO name'
                ])->setHttpResponseCode(400);
            }

            $this->logger->info('OrderSplit Webhook: Cancel received', [
                'so_name' => $erpSoName,
                'reason' => $reason
            ]);

            // Find child order
            $childOrder = $this->parentOrderManager->findChildByErpSoId($erpSoName);
            if (!$childOrder) {
                return $result->setData([
                    'success' => false,
                    'message' => "Child order not found for SO: {$erpSoName}"
                ])->setHttpResponseCode(404);
            }

            // Cancel child order
            if ($childOrder->canCancel()) {
                $this->orderManagement->cancel($childOrder->getEntityId());
            }

            // Update parent
            $parentOrder = $this->parentOrderManager->getParentOrder($childOrder);
            if ($parentOrder) {
                $this->parentOrderManager->removeChildFromParent($parentOrder, (int)$childOrder->getEntityId());
                $this->statusAggregator->updateParentStatus($parentOrder);

                // Check if all children are cancelled
                $activeChildren = array_filter(
                    $this->parentOrderManager->getChildOrders($parentOrder),
                    fn($child) => $child->getState() !== \Magento\Sales\Model\Order::STATE_CANCELED
                );

                if (empty($activeChildren) && $parentOrder->canCancel()) {
                    $this->orderManagement->cancel($parentOrder->getEntityId());
                    $this->logger->info('OrderSplit: All children cancelled, parent cancelled', [
                        'parent_id' => $parentOrder->getIncrementId()
                    ]);
                }
            }

            return $result->setData([
                'success' => true,
                'message' => 'Child order cancelled',
                'child_order' => $childOrder->getIncrementId()
            ])->setHttpResponseCode(200);

        } catch (\Exception $e) {
            $this->logger->error('OrderSplit Webhook: Cancel failed', [
                'error' => $e->getMessage()
            ]);

            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ])->setHttpResponseCode(500);
        }
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
