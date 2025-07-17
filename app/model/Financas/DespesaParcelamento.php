<?php

use Adianti\Database\TRecord;

class Despesa extends TRecord
{
    const TABLENAME  = 'despesa_parcelamento';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max';

    private $categoria;
    private $cartao;

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('despesa_id');
        parent::addAttribute('vezes');
    }

}
