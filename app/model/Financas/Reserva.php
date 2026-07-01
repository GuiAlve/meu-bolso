<?php

use Adianti\Database\TRecord;

class Reserva extends TRecord
{
    const TABLENAME  = 'reserva';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max';

    const CREATEDBY  = 'usuario_id';
    const CREATEDAT  = 'created_at';

    private $categoria;

    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);

        parent::addAttribute('categoria_id');
        parent::addAttribute('valor');
        parent::addAttribute('ativo');
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
}
