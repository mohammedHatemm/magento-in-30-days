<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Model;

use Magento\InventoryApi\Api\SourceRepositoryInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySourceSelectionApi\Api\Data\InventoryRequestInterfaceFactory;
use Magento\InventorySourceSelectionApi\Api\Data\ItemRequestInterfaceFactory;
use Magento\InventorySourceSelectionApi\Api\SourceSelectionServiceInterface;
use Magento\InventorySourceSelectionApi\Api\GetDefaultSourceSelectionAlgorithmCodeInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Psr\Log\LoggerInterface;

class WarehouseResolver
{
    private SourceSelectionServiceInterface $sourceSelectionService;
    private GetDefaultSourceSelectionAlgorithmCodeInterface $defaultAlgorithm;
    private InventoryRequestInterfaceFactory $inventoryRequestFactory;
    private ItemRequestInterfaceFactory $itemRequestFactory;
    private StockByWebsiteIdResolverInterface $stockByWebsiteId;
    private LoggerInterface $logger;

    public function __construct(
        SourceSelectionServiceInterface $sourceSelectionService,
        GetDefaultSourceSelectionAlgorithmCodeInterface $defaultAlgorithm,
        InventoryRequestInterfaceFactory $inventoryRequestFactory,
        ItemRequestInterfaceFactory $itemRequestFactory,
        StockByWebsiteIdResolverInterface $stockByWebsiteId,
        LoggerInterface $logger
    ) {
        $this->sourceSelectionService = $sourceSelectionService;
        $this->defaultAlgorithm = $defaultAlgorithm;
        $this->inventoryRequestFactory = $inventoryRequestFactory;
        $this->itemRequestFactory = $itemRequestFactory;
        $this->stockByWebsiteId = $stockByWebsiteId;
        $this->logger = $logger;
    }

    /**
     * Group order items by warehouse/source code using MSI source selection
     *
     * @param OrderInterface $order
     * @return array<string, OrderItemInterface[]> ['source_code' => [items...]]
     */
    public function groupItemsByWarehouse(OrderInterface $order): array
    {
        $websiteId = (int)$order->getStore()->getWebsiteId();

        try {
            $stock = $this->stockByWebsiteId->execute($websiteId);
            $stockId = (int)$stock->getStockId();
        } catch (\Exception $e) {
            $this->logger->error('OrderSplit: Failed to resolve stock for website', [
                'website_id' => $websiteId,
                'error' => $e->getMessage()
            ]);
            return $this->fallbackGrouping($order);
        }

        // Build item requests for MSI source selection
        $itemRequests = [];
        $orderItems = [];

        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($order->getItems() as $item) {
            if ($item->getData('has_children') || $item->getIsVirtual()) {
                continue;
            }

            $sku = $item->getSku();
            $qty = (float)$item->getQtyOrdered();

            $itemRequests[] = $this->itemRequestFactory->create([
                'sku' => $sku,
                'qty' => $qty
            ]);
            $orderItems[$sku] = $item;
        }

        if (empty($itemRequests)) {
            return [];
        }

        try {
            $inventoryRequest = $this->inventoryRequestFactory->create([
                'stockId' => $stockId,
                'items' => $itemRequests
            ]);

            $algorithmCode = $this->defaultAlgorithm->execute();
            $result = $this->sourceSelectionService->execute($inventoryRequest, $algorithmCode);

            $grouped = [];
            foreach ($result->getSourceSelectionItems() as $selectionItem) {
                $sourceCode = $selectionItem->getSourceCode();
                $sku = $selectionItem->getSku();
                $qtyToDeduct = $selectionItem->getQtyToDeduct();

                if ($qtyToDeduct <= 0) {
                    continue;
                }

                if (isset($orderItems[$sku])) {
                    $grouped[$sourceCode][$sku] = $orderItems[$sku];
                }
            }

            $this->logger->info('OrderSplit: Items grouped by warehouse', [
                'order_id' => $order->getIncrementId(),
                'warehouses' => array_keys($grouped),
                'items_per_warehouse' => array_map('count', $grouped)
            ]);

            return $grouped;

        } catch (\Exception $e) {
            $this->logger->error('OrderSplit: Source selection failed, using fallback', [
                'order_id' => $order->getIncrementId(),
                'error' => $e->getMessage()
            ]);
            return $this->fallbackGrouping($order);
        }
    }

    /**
     * Fallback: put all items in 'default' source
     */
    private function fallbackGrouping(OrderInterface $order): array
    {
        $items = [];
        foreach ($order->getItems() as $item) {
            if ($item->getData('has_children') || $item->getIsVirtual()) {
                continue;
            }
            $items[$item->getSku()] = $item;
        }

        return ['default' => $items];
    }
}
