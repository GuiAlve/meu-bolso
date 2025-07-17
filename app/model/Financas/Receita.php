<?php

class Receita extends TRecord
{
    const TABLENAME  = 'receita';
    const PRIMARYKEY = 'id';
    const IDPOLICY   =  'max';

    const CREATEDBY  = 'usuario_id';
    const CREATEDAT  = 'created_at';

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('valor');
        parent::addAttribute('categoria_id');
        parent::addAttribute('conta_id');
        parent::addAttribute('data_hora');
        parent::addAttribute('descricao');
        parent::addAttribute('usuario_id');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
    }

    public function get_categoria()
    {
        return new Categoria($this->categoria_id);
    }

    public function get_conta()
    {
        return new Conta($this->conta_id);
    }
}
