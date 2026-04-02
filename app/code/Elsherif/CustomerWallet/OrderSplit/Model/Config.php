<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_PREFIX = 'order_split/';
    private const XML_PATH_ENABLED = 'general/enabled';
    private const XML_PATH_SPLIT_SINGLE_WAREHOUSE = 'general/split_single_warehouse';
    private const XML_PATH_DEBUG_MODE = 'general/debug_mode';

    private ScopeConfigInterface $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function isEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Whether to create parent/child even when all items come from single warehouse
     */
    public function shouldSplitSingleWarehouse($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . self::XML_PATH_SPLIT_SINGLE_WAREHOUSE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isDebugMode($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . self::XML_PATH_DEBUG_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
