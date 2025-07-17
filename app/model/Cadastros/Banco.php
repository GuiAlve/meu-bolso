<?php

class Banco extends TRecord
{
    const TABLENAME = 'banco';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'max'; // {max, serial}

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('agencia');
        parent::addAttribute('nome');
        parent::addAttribute('conta');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }

    public function setAtivo($ativo)
    {
        $this->ativo = $ativo;
        return $this;
    }

    public function ativar()
    {
        $this->ativo = 1;
        $this->store();
    }

}