<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Controller\Webhook;

use CrocoIT\OrderSplit\Model\Service\DeliveryNoteService;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

class DeliveryNote implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private RequestInterface $request;
    private JsonFactory $jsonFactory;
    private DeliveryNoteService $deliveryNoteService;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        DeliveryNoteService $deliveryNoteService,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->deliveryNoteService = $deliveryNoteService;
        $this->logger = $logger;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $body = json_decode($this->request->getContent(), true);

            if (!$body || empty($body['name'])) {
                return $result->setData([
                    'success' => false,
                    'message' => 'Invalid Delivery Note data'
                ])->setHttpResponseCode(400);
            }

            $this->logger->info('OrderSplit Webhook: Delivery Note received', [
                'dn_name' => $body['name']
            ]);

            $response = $this->deliveryNoteService->processDeliveryNote($body);

            return $result->setData($response)->setHttpResponseCode(200);

        } catch (\Exception $e) {
            $this->logger->error('OrderSplit Webhook: Delivery Note failed', [
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
