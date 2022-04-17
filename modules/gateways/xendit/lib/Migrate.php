<?php
namespace Xendit\lib;

use Illuminate\Database\Capsule\Manager as Capsule;

class Migrate
{
    /**
     * @return void
     */
    public function createTransactionTable()
    {
        // Create database.
        if (!Capsule::schema()->hasTable('xendit_transactions')) {
            return Capsule::schema()->create('xendit_transactions', function ($table) {
                $table->increments('id');
                $table->integer('invoiceid')->unsigned();
                $table->integer('orderid')->unsigned();
                $table->integer('relid')->unsigned();
                $table->string('type', 100);
                $table->string('external_id', 255);
                $table->string('transactionid', 255);
                $table->string('status', 100);
                $table->string('payment_method', 255);
                $table->timestamps();
            });
        }
    }

    /**
     * @return mixed
     */
    public function removeTransactionTable()
    {
        return Capsule::schema()->dropIfExists('xendit_transactions');
    }
}
