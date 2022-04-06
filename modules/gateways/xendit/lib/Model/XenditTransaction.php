<?php

namespace Xendit\Lib\Model;

use Illuminate\Database\Eloquent\Model;

class XenditTransaction extends Model
{
    const STATUS_PENDING = 'PENDING';
    const STATUS_PAID = 'PAID';
    const STATUS_EXPIRED = 'EXPIRED';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'xendit_transactions';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        "invoiceid",
        "orderid",
        "relid",
        "type",
        "external_id",
        "status",
        "payment_method"
    ];
}
