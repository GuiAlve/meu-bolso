<?php

use Adianti\Database\TRecord;

class MinecraftJogador extends TRecord
{
    const TABLENAME  = 'minecraft_jogador';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max';

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('uuid');
        parent::addAttribute('nome');
        parent::addAttribute('usuario_id');
        parent::addAttribute('atualizado_em');
    }
}
