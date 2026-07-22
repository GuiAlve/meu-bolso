<?php

use Adianti\Database\TRecord;

class MinecraftEstatistica extends TRecord
{
    const TABLENAME  = 'minecraft_estatistica';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max';

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('jogador_uuid');
        parent::addAttribute('categoria');
        parent::addAttribute('chave');
        parent::addAttribute('valor');
        parent::addAttribute('usuario_id');
        parent::addAttribute('atualizado_em');
    }
}
