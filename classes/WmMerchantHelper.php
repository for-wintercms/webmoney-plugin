<?php

namespace DS\WebMoney\Classes;

use Lang;
use Event;
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
        return (! empty($productData['LMI_PAYMENT_AMOUNT']) && $productData['LMI_PAYMENT_AMOUNT'] > 0 &&
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
     * @return bool
     */
    public function createPayment(array $paymentData): bool
    {
        // chek required payment data
        if (! $this->checkRequiredPaymentData($paymentData))
            throw new ApplicationException(Lang::get('ds.webmoney::lang.wmmerchanthelper.error_required_payment_data'));

        // payment number
        if (! empty($paymentData['LLMI_PAYMENT_NO']))
        {
            if (! is_numeric($paymentData['LLMI_PAYMENT_NO']) || $paymentData['LLMI_PAYMENT_NO'] < 1 ||
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
            'payment_desc' => $paymentData['LMI_PAYMENT_DESC'],
            'payee_purse' => $paymentData['LMI_PAYEE_PURSE'],
            'payment_no' => $paymentData['LMI_PAYMENT_NO'],
        ];

        if (! empty($paymentData['LMI_SIM_MODE']) && in_array($paymentData['LMI_SIM_MODE'], [0,1,2]))
            $createData['sim_mode'] = (int)$paymentData['LMI_SIM_MODE'];

        $createData['pay_step'] = WmMerchant::PAYMENT_STEP_CREATE;

        // create payment
        $merchant = WmMerchant::create($createData);

        Event::fire('ds.webmoney.create_merchant_payment', $merchant);

        return true;
    }
}
