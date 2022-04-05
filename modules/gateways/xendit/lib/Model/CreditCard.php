<?php

namespace Xendit\Lib\Model;

use Illuminate\Database\Eloquent\Model;

class CreditCard extends Model
{
    const CARD_LABEL = [
        'visa'          => 'Visa',
        'mastercard'    => 'MasterCard'
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tblcreditcards';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        "pay_method_id",
        "card_type",
        "last_four",
        "expired_date"
    ];
}
