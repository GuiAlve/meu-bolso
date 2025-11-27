<?php

use Adianti\Database\TRecord;

class Despesa extends TRecord
{
    const TABLENAME  = 'despesa';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max';

    const CREATEDBY  = 'usuario_id';
    const CREATEDAT  = 'created_at';

    private $categoria;
    private $banco;

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('valor');
        parent::addAttribute('categoria_id');
        parent::addAttribute('data_hora');
        parent::addAttribute('descricao');
        parent::addAttribute('banco_id');
        parent::addAttribute('parcela');
        parent::addAttribute('parcelamento_registro');
        parent::addAttribute('usuario_id');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }

    public function get_categoria()
    {
        if (empty($this->categoria)) {
            $this->categoria = new Categoria($this->categoria_id);
        }
        return $this->categoria;
    }

    public function get_banco()
    {
        if (empty($this->banco)) {
            $this->banco = new Banco($this->banco_id);
        }
        return $this->banco;
    }

}
