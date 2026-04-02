<?php

declare(strict_types=1);

namespace CrocoIT\OrderSplit\Api;

interface WebhookInterface
{
    /**
     * Process ERPNext Sales Order amendment webhook
     *
     * @param mixed $data
     * @return mixed[]
     */
    public function processAmendment($data = null);

    /**
     * Process ERPNext Sales Order cancellation webhook
     *
     * @param mixed $data
     * @return mixed[]
     */
    public function processCancellation($data = null);

    /**
     * Process ERPNext Delivery Note webhook
     *
     * @param mixed $data
     * @return mixed[]
     */
    public function processDeliveryNote($data = null);
}
