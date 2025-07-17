<?php

class Banco extends TRecord
{
    const TABLENAME = 'banco';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'max'; // {max, serial}

    const CREATEDBY  = 'usuario_id';

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('agencia');
        parent::addAttribute('nome');
        parent::addAttribute('conta');
        parent::addAttribute('ativo');
        parent::addAttribute('usuario_id');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }

    public function desativar()
    {
        $this->ativo = 0;
        $this->store();
    }

}