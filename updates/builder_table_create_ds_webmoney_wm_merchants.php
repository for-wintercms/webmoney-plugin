<?php

namespace DS\WebMoney\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateDsWebmoneyWmMerchants extends Migration
{
    public function up()
    {
        Schema::create('ds_webmoney_wm_merchants', function($table)
        {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id')->unsigned();
            $table->bigInteger('payment_no')->unsigned()->unique();
            $table->string('payee_purse', 13);
            $table->decimal('payment_amount', 11, 2);
            $table->string('payment_desc', 256);
            $table->smallInteger('pay_step')->unsigned();
            $table->smallInteger('payment_mode')->nullable();
            $table->text('payment_request_data')->nullable();
            $table->text('prerequest_data')->nullable();
            $table->text('payment_success_data')->nullable();
            $table->text('payment_error_data')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ds_webmoney_wm_merchants');
    }
}
