<?php

namespace Acelle\Model;

use Illuminate\Database\Eloquent\Model;

use Acelle\Model\Subscription;
use Acelle\Model\Transaction;
use Acelle\Library\Traits\HasUid;
use Dompdf\Dompdf;
use Acelle\Library\StringHelper;
use Acelle\Library\Facades\SubscriptionFacade;

use function Acelle\Helpers\getAppHost;

class Invoice extends Model
{
    use HasUid;

    // statuses
    public const STATUS_NEW = 'new';               // unpaid
    public const STATUS_PAID = 'paid';

    // type
    public const TYPE_RENEW_SUBSCRIPTION = 'renew_subscription';
    public const TYPE_NEW_SUBSCRIPTION = 'new_subscription';
    public const TYPE_CHANGE_PLAN = 'change_plan';

    protected $fillable = [
        'billing_first_name',
        'billing_last_name',
        'billing_address',
        'billing_email',
        'billing_phone',
        'billing_country_id',
    ];

    public function scopeNew($query)
    {
        $query->whereIn('status', [
            self::STATUS_NEW,
        ]);
    }

    public function scopeUnpaid($query)
    {
        $query->whereIn('status', [
            self::STATUS_NEW,
        ]);
    }

    public function scopeChangePlan($query)
    {
        $query->where('type', self::TYPE_CHANGE_PLAN);
    }

    public function scopeRenew($query)
    {
        $query->where('type', self::TYPE_RENEW_SUBSCRIPTION);
    }

    public function scopeNewSubscription($query)
    {
        $query->whereIn('type', [
            self::TYPE_NEW_SUBSCRIPTION,
        ]);
    }

    /**
     * Invoice currency.
     */
    public function currency()
    {
        return $this->belongsTo('Acelle\Model\Currency');
    }

    /**
     * Invoice customer.
     */
    public function customer()
    {
        return $this->belongsTo('Acelle\Model\Customer');
    }

    /**
     * Invoice items.
     */
    public function invoiceItems()
    {
        return $this->hasMany('Acelle\Model\InvoiceItem');
    }

    /**
     * Transactions.
     */
    public function transactions()
    {
        return $this->hasMany('Acelle\Model\Transaction');
    }

    public function billingCountry()
    {
        return $this->belongsTo('Acelle\Model\Country', 'billing_country_id');
    }

    /**
     * Get pending transaction.
     */
    public function getPendingTransaction()
    {
        return $this->transactions()
            ->where('status', \Acelle\Model\Transaction::STATUS_PENDING)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Last transaction.
     */
    public function lastTransaction()
    {
        return $this->transactions()
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Last transaction is failed.
     */
    public function lastTransactionIsFailed()
    {
        if ($this->lastTransaction()) {
            return $this->lastTransaction()->isFailed();
        } else {
            return false;
        }
    }

    /**
     * Set as pending.
     *
     * @return void
     */
    public function setPending()
    {
        $this->status = self::STATUS_PENDING;
        $this->save();
    }

    /**
     * Set as paid.
     *
     * @return void
     */
    public function setPaid()
    {
        $this->status = self::STATUS_PAID;
        $this->save();
    }

    public function getTax()
    {
        $total = 0;

        foreach ($this->invoiceItems as $item) {
            $total += $item->getTax();
        }

        return $total;
    }

    public function subTotal()
    {
        $total = 0;

        foreach ($this->invoiceItems as $item) {
            $total += $item->subTotal();
        }

        return $total;
    }

    public function total()
    {
        $total = 0;

        foreach ($this->invoiceItems as $item) {
            $total += $item->total();
        }

        return $total + $this->fee;
    }

    /**
     * formatted Total.
     *
     * @return void
     */
    public function formattedTotal()
    {
        return format_price($this->total(), $this->currency->format);
    }

    /**
     * Get metadata.
     *
     * @var object | collect
     */
    public function getMetadata($name=null)
    {
        if (!$this['metadata']) {
            return json_decode('{}', true);
        }

        $data = json_decode($this['metadata'], true);

        if ($name != null) {
            if (isset($data[$name])) {
                return $data[$name];
            } else {
                return null;
            }
        } else {
            return $data;
        }
    }

    /**
     * Get metadata.
     *
     * @var object | collect
     */
    public function updateMetadata($data)
    {
        $metadata = (object) array_merge((array) $this->getMetadata(), $data);
        $this['metadata'] = json_encode($metadata);

        $this->save();
    }

    // /**
    //  * Get type.
    //  *
    //  * @return void
    //  */
    // public function getType()
    // {
    //     return $this->invoiceItems()->first()->item_type;
    // }

    /**
     * Check new.
     *
     * @return void
     */
    public function isNew()
    {
        return $this->status == self::STATUS_NEW;
    }

    /**
     * set status as new.
     *
     * @return void
     */
    public function setNew()
    {
        $this->status = self::STATUS_NEW;
        $this->save();
    }

    /**
     * Approve invoice.
     *
     * @return void
     */
    public function approve()
    {
        // for only new invoice
        if (!$this->isNew()) {
            throw new \Exception("Trying to approve an invoice that is not NEW (Invoice ID: {$this->id}, status: {$this->status}");
        }

        // for only new invoice
        if (!$this->getPendingTransaction()) {
            throw new \Exception("Trying to approve an invoice that does not have a pending transaction (Invoice ID: {$this->id}, status: {$this->status}");
        }

        \DB::transaction(function () {
            // fulfill invoice
            $this->fulfill();

            // logging by type
            switch ($this->type) {
                case Invoice::TYPE_NEW_SUBSCRIPTION:
                case Invoice::TYPE_RENEW_SUBSCRIPTION:
                case Invoice::TYPE_CHANGE_PLAN:
                    $subscription = Subscription::findByUid($this->getMetadata()['subscription_uid']);
                    SubscriptionFacade::log($subscription, SubscriptionLog::TYPE_ADMIN_APPROVE, $this->uid, [
                        'amount' => $this->formattedTotal(),
                    ]);
                    break;
            }
        });
    }

    /**
     * Reject invoice.
     *
     * @return void
     */
    public function reject($error)
    {
        // for only new invoice
        if (!$this->isNew()) {
            throw new \Exception("Trying to approve an invoice that is not NEW (Invoice ID: {$this->id}, status: {$this->status}");
        }

        // for only new invoice
        if (!$this->getPendingTransaction()) {
            throw new \Exception("Trying to approve an invoice that does not have a pending transaction (Invoice ID: {$this->id}, status: {$this->status}");
        }

        \DB::transaction(function () use ($error) {
            // fulfill invoice
            $this->payFailed($error);

            // logging by type
            switch ($this->type) {
                case Invoice::TYPE_NEW_SUBSCRIPTION:
                case Invoice::TYPE_RENEW_SUBSCRIPTION:
                case Invoice::TYPE_CHANGE_PLAN:
                    $subscription = Subscription::findByUid($this->getMetadata()['subscription_uid']);
                    SubscriptionFacade::log($subscription, SubscriptionLog::TYPE_ADMIN_REJECT, $this->uid, [
                        'amount' => $this->formattedTotal(),
                        'reason' => $error,
                    ]);
                    break;
            }
        });
    }

    /**
     * Pay invoice.
     *
     * @return void
     */
    public function fulfill()
    {
        $invoice = $this;
        \DB::transaction(function () use (&$invoice) {
            // set invoice status as paid
            $invoice->setPaid();

            // set transaction as success
            // Important: according to current design, the rule is: one invoice only has one pending transaction
            $invoice->getPendingTransaction()->setSuccess();

            // set subscription status
            $invoice->process();
        });
    }

    /**
     * Pay invoice failed.
     *
     * @return void
     */
    public function payFailed($error)
    {
        $this->getPendingTransaction()->setFailed(trans('messages.payment.cannot_charge', [
            'id' => $this->uid,
            'error' => $error,
            'service' => $this->getPendingTransaction()->method,
        ]));
    }

    /**
     * Process invoice.
     *
     * @return void
     */
    public function process()
    {
        switch ($this->type) {
            case self::TYPE_NEW_SUBSCRIPTION:
                $subscription = Subscription::findByUid($this->getMetadata()['subscription_uid']);
                $subscription->activate();
                break;
            case self::TYPE_RENEW_SUBSCRIPTION:
                $subscription = Subscription::findByUid($this->getMetadata()['subscription_uid']);
                $subscription->renew();
                break;
            case self::TYPE_CHANGE_PLAN:
                $subscription = Subscription::findByUid($this->getMetadata()['subscription_uid']);
                $newPlan = \Acelle\Model\Plan::findByUid($this->getMetadata()['new_plan_uid']);
                $subscription->changePlan($newPlan);
                break;
            default:
                throw new \Exception('Invoice type is not valid: ' . $this->type);
        }
    }

    /**
     * Check paid.
     *
     * @return void
     */
    public function isPaid()
    {
        return $this->status == self::STATUS_PAID;
    }

    /**
     * Check done.
     *
     * @return void
     */
    public function isDone()
    {
        return $this->status == self::STATUS_DONE;
    }

    /**
     * Check rejected.
     *
     * @return void
     */
    public function isRejected()
    {
        return $this->status == self::STATUS_REJECTED;
    }

    /**
     * Get billing info.
     *
     * @return void
     */
    public function getBillingInfo()
    {
        switch ($this->type) {
            case self::TYPE_RENEW_SUBSCRIPTION:
                $subscription = Subscription::findByUid($this->getMetadata()['subscription_uid']);
                $chargeInfo = trans('messages.bill.charge_before', [
                    'date' => $this->customer->formatDateTime($subscription->current_period_ends_at, 'datetime_full'),
                ]);
                $plan = $subscription->plan;
                break;
            case self::TYPE_NEW_SUBSCRIPTION:
                $subscription = Subscription::findByUid($this->getMetadata()['subscription_uid']);
                $chargeInfo = trans('messages.bill.charge_now');
                $plan = $subscription->plan;
                break;
            case self::TYPE_CHANGE_PLAN:
                $data = $this->getMetadata();
                $plan = \Acelle\Model\Plan::findByUid($data['new_plan_uid']);
                $chargeInfo = trans('messages.bill.charge_now');
                break;
            default:
                $chargeInfo = '';
        }

        return  [
            'title' => $this->title,
            'description' => $this->description,
            'bill' => $this->invoiceItems()->get()->map(function ($item) {
                return [
                    'title' => $item->title,
                    'description' => $item->description,
                    'price' => format_price($item->amount, $item->invoice->currency->format),
                    'tax' => format_price($item->getTax(), $item->invoice->currency->format),
                    'tax_p' => number_with_delimiter($item->getTaxPercent()),
                    'discount' => format_price($item->discount, $item->invoice->currency->format),
                    'sub_total' => format_price($item->subTotal(), $item->invoice->currency->format),
                ];
            }),
            'charge_info' => $chargeInfo,
            'total' => format_price($this->total(), $this->currency->format),
            'sub_total' => format_price($this->subTotal(), $this->currency->format),
            'tax' => format_price($this->getTax(), $this->currency->format),
            'pending' => $this->getPendingTransaction(),
            'invoice_uid' => $this->uid,
            'due_date' => $this->created_at,
            'type' => $this->type,
            'plan' => $plan,
            'has_fee' => $this->fee ? $this->fee > 0 : false,
            'fee' => $this->fee ? format_price($this->fee, $this->currency->format) : null,
            'billing_display_name' => (
                get_localization_config('show_last_name_first', $this->customer->getLanguageCode()) ?
                    ($this->billing_last_name . ' ' . $this->billing_first_name) :
                    ($this->billing_first_name . ' ' . $this->billing_last_name)
            ),
            'billing_first_name' => $this->billing_first_name,
            'billing_last_name' => $this->billing_last_name,
            'billing_address' => $this->billing_address,
            'billing_country' => $this->billing_country_id ? \Acelle\Model\Country::find($this->billing_country_id)->name : '',
            'billing_email' => $this->billing_email,
            'billing_phone' => $this->billing_phone,
        ];
    }

    /**
     * Check is renew subscription invoice.
     *
     * @return boolean
     */
    public function isRenewSubscriptionInvoice()
    {
        return $this->type == self::TYPE_RENEW_SUBSCRIPTION;
    }

    public function isNewSubscriptionInvoice()
    {
        return $this->type == self::TYPE_NEW_SUBSCRIPTION;
    }

    /**
     * Check is change plan invoice.
     *
     * @return boolean
     */
    public function isChangePlanInvoice()
    {
        return $this->type == self::TYPE_CHANGE_PLAN;
    }

    /**
     * Add transaction.
     *
     * @return array
     */
    public function createPendingTransaction($gateway)
    {
        if ($this->getPendingTransaction()) {
            throw new \Exception('Invoice already has a pending transaction!');
        }

        // @todo: dung transactions()->new....
        $transaction = new Transaction();
        $transaction->invoice_id = $this->id;
        $transaction->status = Transaction::STATUS_PENDING;
        $transaction->allow_manual_review = $gateway->allowManualReviewingOfTransaction();

        // This information is needed for verifying a transaction status later on
        $transaction->method = $gateway->getType();

        $transaction->save();

        return $transaction;
    }

    public function isUnpaid()
    {
        return in_array($this->status, [
            self::STATUS_NEW,
        ]);
    }

    /**
     * Checkout.
     *
     * @return array
     */
    public function checkout($gateway, $payCallback)
    {
        $invoice = $this;
        \DB::transaction(function () use ($gateway, $invoice, $payCallback) {
            $invoice->createPendingTransaction($gateway);

            // action by type
            switch ($invoice->type) {
                case Invoice::TYPE_RENEW_SUBSCRIPTION:
                    $subscription = Subscription::findByUid($this->getMetadata()['subscription_uid']);
                    // Xoá NEW change plan invoice hiện tại nếu có
                    if ($subscription->getItsOnlyUnpaidChangePlanInvoice()) {
                        $subscription->getItsOnlyUnpaidChangePlanInvoice()->delete();
                    }
                    break;
                case Invoice::TYPE_CHANGE_PLAN:
                    $subscription = Subscription::findByUid($this->getMetadata()['subscription_uid']);
                    // Xoá NEW renew invoice hiện tại nếu có
                    if ($subscription->getItsOnlyUnpaidRenewInvoice()) {
                        $subscription->getItsOnlyUnpaidRenewInvoice()->delete();
                    }
                    break;
            }

            try {
                $result = $payCallback($invoice);

                if ($result->isDone()) {
                    // Stripe, PayPal, Braintree for example
                    $invoice->fulfill();

                    // logging by type
                    switch ($invoice->type) {
                        case Invoice::TYPE_NEW_SUBSCRIPTION:
                        case Invoice::TYPE_RENEW_SUBSCRIPTION:
                        case Invoice::TYPE_CHANGE_PLAN:
                            $subscription = Subscription::findByUid($this->getMetadata()['subscription_uid']);
                            SubscriptionFacade::log($subscription, SubscriptionLog::TYPE_PAY_SUCCESS, $invoice->uid, [
                                'amount' => $invoice->formattedTotal(),
                            ]);
                            break;
                    }
                } elseif ($result->isFailed()) {
                    // Stripe, PayPal, Braintree for example
                    $invoice->payFailed($result->error);

                    // logging by type
                    switch ($invoice->type) {
                        case Invoice::TYPE_NEW_SUBSCRIPTION:
                        case Invoice::TYPE_RENEW_SUBSCRIPTION:
                        case Invoice::TYPE_CHANGE_PLAN:
                            $subscription = Subscription::findByUid($this->getMetadata()['subscription_uid']);
                            SubscriptionFacade::log($subscription, SubscriptionLog::TYPE_PAY_FAILED, $invoice->uid, [
                                'amount' => $invoice->formattedTotal(),
                                'error' => $result->error,
                            ]);
                            break;
                    }
                } elseif ($result->isStillPending()) {
                    // Coin, offline shouls return this status
                    // Wait more, check again later....
                    // Coinpayment, offline
                    // logging by type
                    switch ($invoice->type) {
                        case Invoice::TYPE_NEW_SUBSCRIPTION:
                        case Invoice::TYPE_RENEW_SUBSCRIPTION:
                        case Invoice::TYPE_CHANGE_PLAN:
                            $subscription = Subscription::findByUid($this->getMetadata()['subscription_uid']);
                            SubscriptionFacade::log($subscription, SubscriptionLog::TYPE_PAYMENT_PENDING, $invoice->uid, [
                                'amount' => $invoice->formattedTotal(),
                            ]);
                            break;
                    }
                } elseif ($result->isVerificationNotNeeded()) {
                    // IMPORTANT: this special status is used for checking (pending) transaction status only
                    //          **** SERVICES SHOULD NOT RETURN THIS STATUS IN CHECKOUT method ****
                    // Do nothing, just wait for the service to finish it itself (Stripe)
                    // Service should not return this status, it is used for verification only
                }
            } catch (\Exception $e) {
                // pay failed
                $invoice->payFailed($e->getMessage());
            }
        });
    }

    public function isFree()
    {
        return $this->total() == 0;
    }

    public function cancel()
    {
        \DB::transaction(function () {
            // Log
            switch ($this->type) {
                case Invoice::TYPE_NEW_SUBSCRIPTION:
                    // new subscription thì không xóa invoice
                    throw new \Exception('Sub đang new thì không thể xóa invoice!');
                    break;
                case Invoice::TYPE_RENEW_SUBSCRIPTION:
                case Invoice::TYPE_CHANGE_PLAN:
                    $subscription = Subscription::findByUid($this->getMetadata()['subscription_uid']);
                    SubscriptionFacade::log($subscription, SubscriptionLog::TYPE_CANCEL_INVOICE, $this->uid, [
                        'amount' => $this->formattedTotal(),
                    ]);
                    break;
            }

            // Tuỳ từng loại invoice mà xử lý trước khi delete invoice
            $this->cancelProcess();

            // Hiện tại cancel đồng nghĩa với xoá luôn invoice đó
            $this->delete();
        });
    }

    public function cancelProcess()
    {
        $data = $this->getMetadata();

        switch ($this->type) {
            case self::TYPE_NEW_SUBSCRIPTION:

                break;
            case self::TYPE_RENEW_SUBSCRIPTION:
                // do nothing
                break;
            case self::TYPE_CHANGE_PLAN:
                // do nothing
                break;
            default:
                throw new \Exception('Invoice type is not valid: ' . $this->type);
        }
    }

    public function updateBillingInformation($billing)
    {
        $validator = \Validator::make($billing, [
            'billing_first_name' => 'required',
            'billing_last_name' => 'required',
            'billing_address' => 'required',
            'billing_country_id' => 'required',
            'billing_email' => 'required|email',
            'billing_phone' => 'required',
        ]);

        if ($validator->fails()) {
            return $validator;
        }

        $this->fill($billing);
        $this->save();

        return $validator;
    }

    public function getBillingName()
    {
        $lastNameFirst = get_localization_config('show_last_name_first', $this->customer->getLanguageCode());

        if ($lastNameFirst) {
            return htmlspecialchars(trim($this->billing_last_name . ' ' . $this->billing_first_name));
        } else {
            return htmlspecialchars(trim($this->billing_first_name . ' ' . $this->billing_last_name));
        }
    }

    public static function createInvoice($type, $title, $description, $customer_id, $currency_id, $billing_address, $invoiceItems, $metadata)
    {
        // create invoice
        $invoice = new self();
        $invoice->status = self::STATUS_NEW;
        $invoice->type = $type;

        $invoice->title = $title;
        $invoice->description = $description;
        $invoice->customer_id = $customer_id;
        $invoice->currency_id = $currency_id;

        // fill billing information
        if ($billing_address) {
            $invoice->billing_first_name = $billing_address->first_name;
            $invoice->billing_last_name = $billing_address->last_name;
            $invoice->billing_address = $billing_address->address;
            $invoice->billing_email = $billing_address->email;
            $invoice->billing_phone = $billing_address->phone;
            $invoice->billing_country_id = $billing_address->country_id;
        }

        // save
        $invoice->save();

        // add invoice number
        $invoice->createInvoiceNumber();

        // data
        $invoice->updateMetadata($metadata);

        // add item
        foreach ($invoiceItems as $invoiceItem) {
            $invoiceItem->invoice_id = $invoice->id;
            $invoiceItem->save();
        }

        return $invoice;
    }

    public function hasBillingInformation()
    {
        if (empty($this->billing_first_name) ||
            empty($this->billing_last_name) ||
            empty($this->billing_phone) ||
            empty($this->billing_address) ||
            empty($this->billing_country_id) ||
            empty($this->billing_email)
        ) {
            return false;
        }

        return true;
    }

    public static function getTemplateContent()
    {
        if (\Acelle\Model\Setting::get('invoice.custom_template')) {
            return \Acelle\Model\Setting::get('invoice.custom_template');
        } else {
            return view('invoices.template');
        }
    }

    public function getInvoiceHtml()
    {
        $content = self::getTemplateContent();
        $bill = $this->getBillingInfo();

        // transalte tags
        $values = [
            ['tag' => '{COMPANY_NAME}', 'value' => \Acelle\Model\Setting::get('company_name')],
            ['tag' => '{COMPANY_ADDRESS}', 'value' => \Acelle\Model\Setting::get('company_address')],
            ['tag' => '{COMPANY_EMAIL}', 'value' => \Acelle\Model\Setting::get('company_email')],
            ['tag' => '{COMPANY_PHONE}', 'value' => \Acelle\Model\Setting::get('company_phone')],
            ['tag' => '{FIRST_NAME}', 'value' => $bill['billing_first_name']],
            ['tag' => '{LAST_NAME}', 'value' => $bill['billing_last_name']],
            ['tag' => '{ADDRESS}', 'value' => $bill['billing_address']],
            ['tag' => '{COUNTRY}', 'value' => $bill['billing_country']],
            ['tag' => '{EMAIL}', 'value' => $bill['billing_email']],
            ['tag' => '{PHONE}', 'value' => $bill['billing_phone']],
            ['tag' => '{INVOICE_NUMBER}', 'value' => $this->number],
            ['tag' => '{CURRENT_DATETIME}', 'value' => $this->customer->formatCurrentDateTime('datetime_full')],
            ['tag' => '{INVOICE_DUE_DATE}', 'value' => $this->customer->formatDateTime($bill['due_date'], 'datetime_full')],
            ['tag' => '{ITEMS}', 'value' => view('invoices._template_items', [
                'bill' => $bill,
                'invoice' => $this,
            ])],
        ];

        foreach ($values as $value) {
            $content = str_replace($value['tag'], $value['value'], $content);
        }

        $content = StringHelper::transformUrls($content, function ($url, $element) {
            if (strpos($url, '#') === 0) {
                return $url;
            }

            if (strpos($url, 'mailto:') === 0) {
                return $url;
            }

            if (parse_url($url, PHP_URL_HOST) === false) {
                // false ==> if url is invalid
                // null ==> if url does not have host information
                return $url;
            }

            if (StringHelper::isTag($url)) {
                return $url;
            }

            if (strpos($url, '/') === 0) {
                // absolute url with leading slash (/) like "/hello/world"

                return join_url(getAppHost(), $url);
            } elseif (strpos($url, 'data:') === 0) {
                // base64 image. Like: "data:image/png;base64,iVBOR"
                return $url;
            } else {
                return $url;
            }
        });

        return $content;
    }

    public function exportToPdf()
    {
        // instantiate and use the dompdf class
        $dompdf = new Dompdf(array('enable_remote' => true));
        $content = mb_convert_encoding($this->getInvoiceHtml(), 'HTML-ENTITIES', 'UTF-8');
        $dompdf->loadHtml($content);

        // (Optional) Setup the paper size and orientation
        $dompdf->setPaper('A4');

        // Render the HTML as PDF
        $dompdf->render();

        return $dompdf->output();
    }

    public static function getTags()
    {
        $tags = [
            ['name' => '{COMPANY_NAME}', 'required' => false],
            ['name' => '{COMPANY_ADDRESS}', 'required' => false],
            ['name' => '{COMPANY_EMAIL}', 'required' => false],
            ['name' => '{COMPANY_PHONE}', 'required' => false],
            ['name' => '{FIRST_NAME}', 'required' => false],
            ['name' => '{LAST_NAME}', 'required' => false],
            ['name' => '{ADDRESS}', 'required' => false],
            ['name' => '{COUNTRY}', 'required' => false],
            ['name' => '{EMAIL}', 'required' => false],
            ['name' => '{PHONE}', 'required' => false],
            ['name' => '{INVOICE_NUMBER}', 'required' => false],
            ['name' => '{CURRENT_DATETIME}', 'required' => false],
            ['name' => '{INVOICE_DUE_DATE}', 'required' => false],
            ['name' => '{ITEMS}', 'required' => false],
            ['name' => '{CUSTOMER_ADDRESS}', 'required' => false],
        ];

        return $tags;
    }

    public function createInvoiceNumber()
    {
        if (\Acelle\Model\Setting::get('invoice.current')) {
            $currentNumber = intval(\Acelle\Model\Setting::get('invoice.current'));
        } else {
            $currentNumber = 1;
        }

        $this->number = sprintf(\Acelle\Model\Setting::get('invoice.format'), $currentNumber);
        $this->save();

        // update current number
        \Acelle\Model\Setting::set('invoice.current', ($currentNumber + 1));
    }

    public function updatePaymentServiceFee($gateway)
    {
        // trường hợp init invoice có free trial và setting không require card thì fee = 0
        if (\Acelle\Model\Setting::get('not_require_card_for_trial') == 'yes' && $this->isInitInvoiceWithTrial()) {
            $this->fee = 0;
            $this->save();

            return;
        }

        if ($this->subTotal() == 0) {
            $this->fee = $gateway->getMinimumChargeAmount($this->currency->code);
            $this->save();
        }
    }

    public function isInitInvoiceWithTrial()
    {
        return $this->type == self::TYPE_NEW_SUBSCRIPTION && $this->getMetadata('trial');
    }

    public function getCurrencyCode()
    {
        return $this->currency->code;
    }

    public function getBillingCountryCode()
    {
        return ($this->billingCountry ? $this->billingCountry->code : '');
    }
}
