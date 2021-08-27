<?php

// WebMoney Merchant
Route::post('pay/webmoney/result', function () {
    return \DS\WebMoney\Classes\WmMerchantHelper::instance()->resultsProcessing();
});
