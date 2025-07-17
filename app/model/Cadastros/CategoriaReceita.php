<?php

use Adianti\Database\TRecord;

class CategoriaReceita extends TRecord
{
    const TABLENAME  = 'categoria_receita';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max'; // 'max' para MySQL AUTO_INCREMENT, use 'serial' para PostgreSQL

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('nome');
        parent::addAttribute('cor');
    }
}
