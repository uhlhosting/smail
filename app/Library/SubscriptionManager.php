<?php

namespace Acelle\Library;

use Acelle\Model\Subscription;
use Acelle\Cashier\Library\TransactionVerificationResult;
use Acelle\Model\SubscriptionLog;

class SubscriptionManager
{
    public function checkExpiration()
    {
        $subscriptions = Subscription::active()->get();

        foreach ($subscriptions as $subscription) {
            $subscription->checkExpiration();
        }
    }

    public function createRenewInvoice()
    {
        $subscriptions = Subscription::active()->get();

        foreach ($subscriptions as $subscription) {
            $subscription->checkAndCreateRenewInvoice();
        }
    }

    public function checkAndAutoPayRenewInvoiceByCustomer($customer)
    {
        $subscription = $customer->getCurrentActiveSubscription();

        if (!$subscription) {
            // do nothing
            return;
        }

        $unpaidRenewInvoice = $subscription->getItsOnlyUnpaidRenewInvoice();

        if (!$unpaidRenewInvoice) {
            // do nothing
            return;
        }

        // not reach due date
        if (!$subscription->reachDueDate()) {
            // do nothing
            return;
        }

        // check if customer can auto charge
        if (!$customer->preferredPaymentGatewayCanAutoCharge()) {
            return;
        }

        // check if invoice amount is 0
        if ($unpaidRenewInvoice->total() == 0) {
            // throw new \Exception("
            //     Không thể auto pay cho Unpaid RENEW invoice [#$unpaidRenewInvoice->uid] vì invoice->total() = 0.
            //     Trường hợp auto charge cho renew invoice cho plan free!"
            // );

            // Trường hợp invoice total = 0 thì pay nothing và set done luôn cho renew invoice
            $unpaidRenewInvoice->checkout($customer->getPreferredPaymentGateway(), function ($invoice) {
                return new TransactionVerificationResult(TransactionVerificationResult::RESULT_DONE);
            });

            return;
        }

        // auto charge
        $customer->getPreferredPaymentGateway()->autoCharge($unpaidRenewInvoice);
    }

    public function log($subscription, $type, $invoice_uid=null, $metadata=[])
    {
        $log = $subscription->subscriptionLogs()->create([
            'type' => $type,
            'invoice_uid' => $invoice_uid,
        ]);

        if (isset($metadata)) {
            $log->updateData($metadata);
        }

        return $log;
    }
}
