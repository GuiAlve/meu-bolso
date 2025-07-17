 <?php

use Adianti\Widget\Container\TPanelGroup;

 class CategoriaReceitaLista extends TPage
{
    use Adianti\Base\AdiantiStandardListTrait;
    use Adianti\Base\AdiantiStandardFormTrait;

    public function __construct()
    {
        parent::__construct();

        $this->setDatabase('bolso');
        $this->setActiveRecord('CategoriaReceita');

        // create the form
        $this->form = new BootstrapFormBuilder('form_categoria_receita');

        // create the form fields
        $id         = new TEntry('id');
        $name       = new TEntry('nome');
        $cor        = new TColor('cor');

        // add the fields in the form
        $this->form->addFields( [new TLabel('ID')],    [$id] );
        $this->form->addFields( [new TLabel('Nome', 'red')],  [$name] );
        $this->form->addFields( [new TLabel('Cor')],  [$cor] );

        $name->addValidation('Nome', new TRequiredValidator);

        // define the form actions
        $this->form->addAction( 'Salvar',  new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addActionLink( 'Limpar', new TAction([$this, 'onClear']), 'fa:eraser red');
        $this->form->addActionLink( 'Atualizar Lista', new TAction([$this, 'onReload']), 'fa:rotate orange');

        // id not editable
        $id->setEditable(FALSE);

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->width = '100%';

        // add the columns
        $col_id         = new TDataGridColumn('id', 'Id', 'right', '10%');
        $col_name       = new TDataGridColumn('nome', 'Nome', 'left', '40%');
        $col_cor        = new TDataGridColumn('cor', 'Cor', 'left', '50%');

        $col_cor->setTransformer(function($value, $object) {
            if (!$value) {
                return '';
            }
        
            $bgcolor = $object->cor ?? '#999999'; // cor padrão caso não tenha
            $color = '#fff'; // texto branco para contraste
        
            $objectNome = htmlspecialchars($object->nome, ENT_QUOTES);
            return "<span style='
                background-color: {$bgcolor};
                color: {$color};
                padding: 4px 10px;
                border-radius: 12px;
                font-size: 0.9em;
                font-weight: 600;
                display: inline-block;
                min-width: 80px;
                text-align: center;
            '>{$objectNome}</span>";
        });

        $this->datagrid->addColumn($col_id);
        $this->datagrid->addColumn($col_name);
        $this->datagrid->addColumn($col_cor);

        $col_id->setAction( new TAction([$this, 'onReload']),   ['order' => 'id']);
        $col_name->setAction( new TAction([$this, 'onReload']), ['order' => 'nome']);

        $action1 = new TDataGridAction([$this, 'onEdit'],   ['key' => '{id}'] );
        $action2 = new TDataGridAction([$this, 'onDelete'], ['key' => '{id}'] );

        $this->datagrid->addAction($action1, 'Edit',   'far:edit blue');
        $this->datagrid->addAction($action2, 'Delete', 'far:trash-alt red');

        // create the datagrid model
        $this->datagrid->createModel();

        // wrap objects
        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));

        $panel = new TPanelGroup('<i class="fa-solid fa-money-bill-trend-up fa-2x" style="margin-right: 8px;"></i><b style="font-size: 24px; ">Categorias de receitas</b>');
        $panel->add($this->form);
        $panel->add($this->datagrid);

        $vbox->add($panel);

        // add the box in the page
        parent::add($vbox);

    }


}