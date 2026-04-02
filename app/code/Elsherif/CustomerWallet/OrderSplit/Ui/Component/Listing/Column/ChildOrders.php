<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class ChildOrders extends Column
{
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $warehouseCode = $item['warehouse_code'] ?? null;
                $erpSoId = $item['erp_sales_order_id'] ?? null;

                $parts = [];
                if ($warehouseCode) {
                    $parts[] = $warehouseCode;
                }
                if ($erpSoId) {
                    $parts[] = $erpSoId;
                }

                $item[$this->getData('name')] = !empty($parts) ? implode(' | ', $parts) : '-';
            }
        }

        return $dataSource;
    }
}
