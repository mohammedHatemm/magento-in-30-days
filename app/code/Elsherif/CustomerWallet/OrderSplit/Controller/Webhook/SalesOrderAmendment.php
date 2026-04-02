<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Controller\Webhook;

use CrocoIT\OrderSplit\Model\AmendmentHandler;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

class SalesOrderAmendment implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private RequestInterface $request;
    private JsonFactory $jsonFactory;
    private AmendmentHandler $amendmentHandler;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        AmendmentHandler $amendmentHandler,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->amendmentHandler = $amendmentHandler;
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
                    'message' => 'Invalid request body'
                ])->setHttpResponseCode(400);
            }

            $this->logger->info('OrderSplit Webhook: Amendment received', [
                'so_name' => $body['name'],
                'amended_from' => $body['amended_from'] ?? null
            ]);

            $response = $this->amendmentHandler->handleAmendment($body);

            return $result->setData($response)->setHttpResponseCode(200);

        } catch (\Exception $e) {
            $this->logger->error('OrderSplit Webhook: Amendment failed', [
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
