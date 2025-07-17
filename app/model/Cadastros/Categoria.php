<?php

use Adianti\Database\TRecord;

class Categoria extends TRecord
{
    const TABLENAME  = 'categoria';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max'; // 'max' para MySQL AUTO_INCREMENT, use 'serial' para PostgreSQL

    const CREATEDBY  = 'usuario_id';

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('nome');
        parent::addAttribute('cor');
        parent::addAttribute('usuario_id');
    }
}
