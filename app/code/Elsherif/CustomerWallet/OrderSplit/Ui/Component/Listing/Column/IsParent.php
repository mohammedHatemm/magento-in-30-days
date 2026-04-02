<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class IsParent extends Column
{
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $isParent = (int)($item['is_parent'] ?? 0);
                $parentOrderId = $item['parent_order_id'] ?? null;

                if ($isParent) {
                    $item[$this->getData('name')] = '<span style="color:#1565c0;font-weight:bold;">⬇ Parent</span>';
                } elseif ($parentOrderId) {
                    $item[$this->getData('name')] = '<span style="color:#e65100;">↳ Child</span>';
                } else {
                    $item[$this->getData('name')] = '<span style="color:#999;">Regular</span>';
                }
            }
        }

        return $dataSource;
    }
}
