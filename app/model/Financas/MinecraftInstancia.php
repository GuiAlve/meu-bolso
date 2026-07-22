<?php

use Adianti\Database\TRecord;

class MinecraftInstancia extends TRecord
{
    const TABLENAME  = 'minecraft_instancia';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max';

    const CREATEDBY  = 'usuario_id';
    const CREATEDAT  = 'created_at';
    const UPDATEDAT  = 'updated_at';

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('tipo');
        parent::addAttribute('custo_hora');
        parent::addAttribute('usuario_id');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }
}
