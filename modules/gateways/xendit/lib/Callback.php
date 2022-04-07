<?php
namespace Xendit\Lib;

class Callback extends ActionBase
{
    /**
     * @param string $external_id
     * @return false|string
     */
    public function getInvoiceIdFromExternalId(string $external_id)
    {
        $externalArray = array_map("trim", explode("-", $external_id));
        return end($externalArray);
    }
}
