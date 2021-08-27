<?php

namespace DS\WebMoney\Classes;

use Lang;
use Event;
use Config;
use Request;
use ApplicationException;
use DS\WebMoney\Models\WmMerchant;
use October\Rain\Support\Traits\Singleton;

class WmMerchantHelper
{
    use Singleton;

    /**
     * Check required payment data
     *
     * @param array $productData
     * @return bool
     */
    public function checkRequiredPaymentData(array $productData): bool
    {
        return (! empty($productData['LMI_PAYMENT_AMOUNT']) && is_numeric($productData['LMI_PAYMENT_AMOUNT']) && (double)$productData['LMI_PAYMENT_AMOUNT'] > 0 &&
                ! empty($productData['LMI_PAYMENT_DESC']) && mb_strlen($productData['LMI_PAYMENT_DESC']) > 0 &&
                ! empty($productData['LMI_PAYEE_PURSE']) && mb_strlen($productData['LMI_PAYEE_PURSE']) == 13);
    }

    /**
     * Generate unique payment number
     *
     * @return int
     */
    public function generatePaymentNumber(): int
    {
        $i = 0;
        do {
            try {
                $number = random_int(1, 99).time().random_int(1, 999);
            }
            catch (\Exception $e) {
                $number = rand(1, 99).time().rand(1, 999);
            }
        }
        while ($this->checkPaymentNumber($number) && ++$i < 99999);

        return $number;
    }

    /**
     * Check the payment number for uniqueness
     *
     * @param int $paymentNumber
     * @return bool
     */
    public function checkPaymentNumber(int $paymentNumber): bool
    {
        return WmMerchant::where('payment_no', $paymentNumber)->value('id') > 0;
    }

    /**
     * Create payment (save in DB)
     *
     * @param array $paymentData
     * @return array
     */
    public function createPayment(array $paymentData): array
    {
        // chek required payment data
        if (! $this->checkRequiredPaymentData($paymentData))
            throw new ApplicationException(Lang::get('ds.webmoney::lang.wmmerchanthelper.error_required_payment_data'));

        // payment number
        if (! empty($paymentData['LLMI_PAYMENT_NO']))
        {
            if (! is_numeric($paymentData['LLMI_PAYMENT_NO']) || (int)$paymentData['LLMI_PAYMENT_NO'] < 1 ||
                $this->checkPaymentNumber((int)$paymentData['LLMI_PAYMENT_NO']))
            {
                throw new ApplicationException(Lang::get('ds.webmoney::lang.wmmerchanthelper.error_payment_number'));
            }
        }
        else
            $paymentData['LMI_PAYMENT_NO'] = $this->generatePaymentNumber();

        // payment data
        $createData = [
            'payment_amount' => $paymentData['LMI_PAYMENT_AMOUNT'],
            'payment_desc'   => $paymentData['LMI_PAYMENT_DESC'],
            'payee_purse'    => $paymentData['LMI_PAYEE_PURSE'],
            'payment_no'     => $paymentData['LMI_PAYMENT_NO'],
        ];

        $createData['pay_step'] = WmMerchant::PAYMENT_STEP_CREATE;
        $createData['payment_request_data'] = $paymentData;

        // create payment
        $merchant = WmMerchant::create($createData);

        Event::fire('ds.webmoney.create_merchant_payment', [$merchant, $paymentData]);

        return $paymentData;
    }

    /**
     * Results processing
     *
     * @return string
     */
    public function resultsProcessing(): string
    {
        try {
            if (Request::method() != 'POST')
                return '?';

            $resultData       = post();
            $isPrerequest     = (! empty($resultData['LMI_PREREQUEST']) && is_numeric($resultData['LMI_PREREQUEST']) && (int)$resultData['LMI_PREREQUEST'] === 1);
            $isPaymentSuccess = (! empty($resultData['LMI_SYS_INVS_NO']) && is_numeric($resultData['LMI_SYS_INVS_NO']) && (int)$resultData['LMI_SYS_INVS_NO'] > 0);
            $isPaymentError   = (! empty($resultData['LMI_FAILREQUEST']) && is_numeric($resultData['LMI_FAILREQUEST']) && (int)$resultData['LMI_FAILREQUEST'] === 1);
            $paymentMode      = (empty($resultData['LMI_MODE']) || ! is_numeric($resultData['LMI_MODE'])) ? -1 : (int)$resultData['LMI_MODE'];
            $paymentNumber    = (empty($resultData['LMI_PAYMENT_NO']) || ! is_numeric($resultData['LMI_PAYMENT_NO'])) ? -1 : (int)$resultData['LMI_PAYMENT_NO'];

            if (! $isPrerequest && ! $isPaymentSuccess && ! $isPaymentError)
                return '?';
            if ($paymentMode == -1 || ($paymentMode !== 0 && $paymentMode !== 1))
                return '?';
            if ($paymentMode === 1 && ! Config::get('ds.webmoney::wm_merchant.test_mode', false))
                return 'Test mode?';
            if ($paymentNumber <= 1 || $paymentNumber > 999999999999999)
                return '?';

            $merchant = WmMerchant::where('payment_no', $paymentNumber)->first();

            if (! $merchant || $merchant->payment_no != $paymentNumber)
                return Lang::get('ds.webmoney::lang.wmmerchanthelper.payment_not_found');

            if (! $isPaymentError)
            {
                // check payee purse
                $payeePurse = $resultData['LMI_PAYEE_PURSE'] ?? 0;
                if (empty($payeePurse) || $merchant->payee_purse !== $payeePurse)
                    return Lang::get('ds.webmoney::lang.wmmerchanthelper.not_our_purse');

                // check payment amount
                $paymentAmount = $resultData['LMI_PAYMENT_AMOUNT'] ?? 0;
                if (empty($paymentAmount) || ! $this->comparePaymentAmount($merchant->payment_amount, $paymentAmount))
                    return Lang::get('ds.webmoney::lang.wmmerchanthelper.invalid_payment_amount');
            }

            // verification by step of the request
            if ($isPrerequest)
            {
                ### prerequest data
                $saveField = 'prerequest_data';
                $merchant->pay_step = WmMerchant::PAYMENT_STEP_PREREQUEST;

                Event::fire('ds.webmoney.merchant_results_processing.prerequest', [$merchant, $resultData]);
            }
            elseif ($isPaymentSuccess)
            {
                ### success request data
                $saveField = 'payment_success_data';
                $merchant->pay_step = WmMerchant::PAYMENT_STEP_SUCCESS_REQUEST;

                // check payment hash
                if (empty($resultData['LMI_HASH']) || empty($resultData['LMI_SYS_TRANS_NO']) || empty($resultData['LMI_SYS_TRANS_DATE']))
                    return '?';

                $secretKey = Event::fire('ds.webmoney.merchant_results_processing.secret_key', $payeePurse, true);
                if (empty($secretKey) || ! is_string($secretKey))
                    return 'Error: key not found';

                $hash  = $resultData['LMI_PAYEE_PURSE'] ?? '';
                $hash .= $resultData['LMI_PAYMENT_AMOUNT'] ?? '';
                $hash .= $resultData['LMI_HOLD'] ?? '';
                $hash .= $resultData['LMI_PAYMENT_NO'] ?? '';
                $hash .= $resultData['LMI_MODE'] ?? '';
                $hash .= $resultData['LMI_SYS_INVS_NO'] ?? '';
                $hash .= $resultData['LMI_SYS_TRANS_NO'] ?? '';
                $hash .= $resultData['LMI_SYS_TRANS_DATE'] ?? '';
                $hash .= $resultData['LMI_SECRET_KEY'] ?? '';
                $hash .= $resultData['LMI_PAYER_PURSE'] ?? '';
                $hash .= $resultData['LMI_PAYER_WM'] ?? '';

                if (strcmp(mb_strtoupper(hash('sha256', $hash)), $resultData['LMI_HASH']) !== 0)
                    return 'So...';

                Event::fire('ds.webmoney.merchant_results_processing.success_request', [$merchant, $resultData]);
            }
            else
            {
                ### error request data
                $saveField = 'payment_error_data';
                $merchant->pay_step = WmMerchant::PAYMENT_STEP_ERROR_REQUEST;

                Event::fire('ds.webmoney.merchant_results_processing.error_request', [$merchant, $resultData]);
            }

            // Save result
            $merchant->$saveField = $resultData;
            $merchant->save();

            Event::fire('ds.webmoney.merchant_results_processing.save_request', [$merchant, $resultData]);

            return 'YES';
        }
        catch (\Exception $e) {
            return 'ERROR!';
        }
    }

    /**
     * Compare payment amount
     *
     * @param $price1
     * @param $price2
     * @return bool
     */
    protected function comparePaymentAmount($savedPrice, $checkPrice): bool
    {
        return (($checkPrice+0.001) > $savedPrice && ($checkPrice-0.001) < $savedPrice);
    }
}
