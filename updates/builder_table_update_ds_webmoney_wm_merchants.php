<?php namespace DS\WebMoney\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateDsWebmoneyWmMerchants extends Migration
{
    public function up()
    {
        Schema::table('ds_webmoney_wm_merchants', function($table)
        {
            $table->decimal('payment_amount', 11, 2)->change();
        });
    }
    
    public function down()
    {
        Schema::table('ds_webmoney_wm_merchants', function($table)
        {
            $table->decimal('payment_amount', 7, 2)->change();
        });
    }
}