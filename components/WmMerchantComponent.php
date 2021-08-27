<?php

namespace DS\WebMoney\Components;

use Event;
use Cms\Classes\ComponentBase;
use DS\WebMoney\Classes\WmMerchantHelper;

class WmMerchantComponent extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'WmMerchant Component',
            'description' => 'For WebMoney Merchant Interface'
        ];
    }

    public function defineProperties()
    {
        return [];
    }

    public function onGetPaymentData()
    {
        $productData = Event::fire('ds.webmoney.product_data', [[
            'LMI_PAYMENT_AMOUNT' => 0,
            'LMI_PAYMENT_DESC'   => '',
            'LMI_PAYEE_PURSE'    => '',
        ]], true);

        return WmMerchantHelper::instance()->createPayment($productData);
    }
}
