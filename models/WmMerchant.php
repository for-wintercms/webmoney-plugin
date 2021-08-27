<?php

namespace DS\WebMoney\Models;

use Model;

/**
 * Model
 */
class WmMerchant extends Model
{
    const PAYMENT_STEP_CREATE          = 0;
    const PAYMENT_STEP_PREREQUEST      = 1;
    const PAYMENT_STEP_SUCCESS_REQUEST = 2;
    const PAYMENT_STEP_ERROR_REQUEST   = 3;

    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'ds_webmoney_wm_merchants';

    /**
     * @var array Validation rules
     */
    public $rules = [];
}
