<?php

use Adianti\Database\TRecord;

class MinecraftSessao extends TRecord
{
    const TABLENAME  = 'minecraft_sessao';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max';

    const CREATEDBY  = 'usuario_id';
    const CREATEDAT  = 'created_at';

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('instance_id');
        parent::addAttribute('hora_inicio');
        parent::addAttribute('hora_fim');
        parent::addAttribute('duracao_segundos');
        parent::addAttribute('custo_estimado');
        parent::addAttribute('custo_hora');
        parent::addAttribute('usuario_id');
        parent::addAttribute('created_at');
    }
}
