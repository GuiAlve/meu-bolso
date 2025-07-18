<?php

use Adianti\Widget\Form\TDate;
use Adianti\Widget\Wrapper\TDBCombo;
use Adianti\Widget\Wrapper\TDBSelect;

class ReceitasLista extends TPage
{
    use Adianti\Base\AdiantiStandardListTrait;

    public function __construct()
    {
        parent::__construct();

        $this->setDatabase('bolso');
        $this->setActiveRecord('Receita');
        $criteria = new TCriteria();
        $criteria->add( new TFilter( 'usuario_id', '=', TSession::getValue('userid')));
        $this->setCriteria($criteria);

        $this->setDefaultOrder('created_at', 'desc');
        $this->setLimit(100);

        $this->addFilterField('descricao', 'like', 'descricao');
        $this->addFilterField('data_hora', 'like', 'data');
        $this->addFilterField('MONTH(data_hora)', '=', 'filtro_mes');
        $this->addFilterField('YEAR(data_hora)', '=', 'filtro_ano');
        $this->addFilterField('(SELECT id FROM categoria WHERE id = categoria_id)', '=', 'categoria');
        $this->addFilterField('valor', '=', 'valor',
            function($valor) {
                $valor = preg_replace("/[^0-9]/", "", $valor);
                return (int) $valor;
            }
        ); 

        $filtros = TSession::getValue(__CLASS__.'_filter_data');

        if (empty($filtros->filtro_mes) OR empty($filtros->filtro_ano)) {

            $inicio = date('Y-m-01') . ' 00:00:00';
            $fim    = date('Y-m-t') . ' 23:59:59';

            $criteria->add(new TFilter('data_hora', '>=', $inicio));
            $criteria->add(new TFilter('data_hora', '<=', $fim));

            $this->setCriteria($criteria);
        }

        $this->setOrderCommand('categoria->nome', '(SELECT nome FROM categoria_receita WHERE categoria_id = categoria_receita.id)');
        $this->setOrderCommand('banco->nome', '(SELECT nome FROM banco WHERE banco_id = banco.id)');

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->width  = '100%';

        $col_id        = new TDataGridColumn('id', 'ID', 'right', '0px');
        $col_valor     = new TDataGridColumn('valor', 'Valor (R$)', 'center');
        $col_categoria = new TDataGridColumn('categoria_id', 'Categoria', 'center');
        $col_data      = new TDataGridColumn('data_hora', 'Data', 'center', '10%');
        $col_descricao = new TDataGridColumn('descricao', 'Descrição', 'center');
        $col_conta     = new TDataGridColumn('banco->nome', 'Conta', 'center');

        $col_valor->setAction(new TAction([$this, 'onReload']), ['order' => 'valor']);
        $col_categoria->setAction(new TAction([$this, 'onReload']), ['order' => 'categoria->nome']);
        $col_data->setAction(new TAction([$this, 'onReload']), ['order' => 'data_hora']);
        $col_descricao->setAction(new TAction([$this, 'onReload']), ['order' => 'descricao']);
        $col_conta->setAction(new TAction([$this, 'onReload']), ['order' => 'banco->nome']);

        // Valor em verde
        $col_valor->setTransformer(function($valor) {
            $formatado = 'R$ ' . number_format($valor / 100, 2, ',', '.');
            return "<span style='color:rgb(35, 128, 48);'>$formatado</span>";
        });

        $col_data->setTransformer(function($value){
            $dt = TDate::date2br($value);
            return $dt;
        });

        $col_categoria->setTransformer(function($value) {
            if (!$value) {
                return '';
            }

            $categoria = new CategoriaReceita($value);
        
            $bgcolor = $categoria->cor ?? '#999999'; // cor padrão caso não tenha
            $color = '#fff'; // texto branco para contraste
        
            $categoriaNome = htmlspecialchars($categoria->nome, ENT_QUOTES);
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
            '>{$categoriaNome}</span>";
        });

                // define totals
        $col_valor->setTotalFunction( function($values) {
            return $soma = array_sum((array) $values);
            return "<span style='color:rgb(59, 206, 78); font-weight: bold;'>$formatado</span>";
        });

        $action1 = new TDataGridAction(['ReceitasForm', 'onEdit'],   ['key' => '{id}'] );
        $this->datagrid->addAction($action1, 'Editar',   'far:edit blue');

        $action2 = new TDataGridAction([$this, 'onDelete'], ['key' => '{id}']);
        $this->datagrid->addAction($action2, 'Excluir', 'far:trash-alt red');

        //$this->datagrid->addColumn($col_id);
        $this->datagrid->addColumn($col_valor);
        $this->datagrid->addColumn($col_data);
        $this->datagrid->addColumn($col_conta);
        $this->datagrid->addColumn($col_categoria);
        $this->datagrid->addColumn($col_descricao);

        $col_conta->enableAutoHide(500);
        $col_data->enableAutoHide(600);
        $col_descricao->enableAutoHide(700);

        $this->datagrid->createModel();

        //fim da datagrid

        $this->form =  new TForm;
        $this->form->setProperty('onkeydown', 'if(event.keyCode == 13) { event.preventDefault(); return false; }');
        $this->form->add($this->datagrid);

        //filtro de mês
        $filtro_mes = new TCombo('filtro_mes');
        $filtro_mes->setSize('100%');
        $filtro_mes->addItems([
            '1'=>'Jan', '2'=>'Fev', '3'=>'Mar', '4'=>'Abr',
            '5'=>'Mai', '6'=>'Jun', '7'=>'Jul', '8'=>'Ago',
            '9'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'
        ]);
        $mes_atual = date('n');  // Ex: 7
        $filtro_mes->setValue($mes_atual);

        $filtro_mes->setChangeAction(new TAction([$this, 'onSearch'], ['static' => '1']));
        //fim filtro de mês

        //filtro de ano
        $filtro_ano = new TCombo('filtro_ano');
        $filtro_ano->setSize('100%');
        $anos = [];
        for ($i = date('Y'); $i >= date('Y')-5; $i--) {
            $anos[$i] = $i;
        }
        $filtro_ano->addItems($anos);

        $filtro_ano->setChangeAction(new TAction([$this, 'onSearch'], ['static' => '1']));
        //fim filtro de ano

        $filtros = TSession::getValue(__CLASS__.'_filter_data');
        if (empty($filtros)) {
            $filtros = new stdClass;

            TSession::setValue(__CLASS__.'_filter_data', $filtros);
        }

        $box_filtros_data = new THBox;
        $box_filtros_data->style = 'margin: 10px 0; gap: 10px; align-items: end;';
            
        $lblMes = new TLabel('Mês:');
        $lblAno = new TLabel('Ano:');
            
        $lblMes->setFontColor('#333');
        $lblAno->setFontColor('#333');
            
        $box_filtros_data->add($lblMes);
        $box_filtros_data->add($filtro_mes);
        $box_filtros_data->add($lblAno);
        $box_filtros_data->add($filtro_ano);

        $this->form->add($box_filtros_data);
        $this->form->addField($filtro_mes);
        $this->form->addField($filtro_ano);

        $valor = new TEntry('valor');
        $data = new TDate('data');
        $conta = new TEntry('conta');
        $categoria = new TDBCombo('categoria', 'bolso', 'CategoriaReceita', 'id', 'nome');
        $descricao = new TEntry('descricao');

        $valor->setNumericMask(2, ',', '.', true);

        $data->setMask('dd/mm/yyyy');       
        $data->setDatabaseMask('yyyy-mm-dd');

        $valor->tabindex = -1;
        $conta->tabindex = -1;
        $categoria->tabindex = -1;
        $data->tabindex = -1;
        $descricao->tabindex = -1;

        $valor->exitOnEnter();
        $conta->exitOnEnter();
        //$categoria->exitOnEnter();
        $descricao->exitOnEnter();

        $valor->setExitAction(new TAction( [$this, 'onSearch'], ['static' => '1']) );
        $conta->setExitAction(new TAction( [$this, 'onSearch'], ['static' => '1']) );
        $data->setExitAction(new TAction( [$this, 'onSearch'], ['static' => '1']) );
        $descricao->setExitAction(new TAction( [$this, 'onSearch'], ['static' => '1']) );
        $categoria->setChangeAction( new TAction([$this, 'onSearch'], ['static' => '1']));      
        
        $tr = new TElement('tr');
        $this->datagrid->prependRow($tr);

        $tdEmpty1       = TElement::tag('td', '');
        $tdEmpty2       = TElement::tag('td', '');
        $tdValor       = TElement::tag('td', $valor);
        $tdData        = TElement::tag('td', $data);
        $tdConta       = TElement::tag('td', $conta);
        $tdCategoria   = TElement::tag('td', $categoria);
        $tdDescricao   = TElement::tag('td', $descricao);

        // Adiciona classes de responsividade
        $tdData->{'class'}      = 'd-none d-md-table-cell';
        $tdConta->{'class'}     = 'd-none d-lg-table-cell';
        $tdCategoria->{'class'} = 'd-none d-xl-table-cell';

        $tr->add($tdEmpty1);
        $tr->add($tdEmpty2);
        $tr->add($tdValor);
        $tr->add($tdData);
        $tr->add($tdConta);
        $tr->add($tdCategoria);
        $tr->add($tdDescricao);

        $this->form->addField($valor);
        $this->form->addField($data);
        $this->form->addField($conta);
        $this->form->addField($categoria);
        $this->form->addField($descricao);

        $this->form->setData(TSession::getValue(__CLASS__.'_filter_data'));
        
        $vbox = New TVBox();
        $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $panel = new TPanelGroup('<i class="fa-solid fa-money-bill-trend-up fa-2x" style="margin-right: 8px;"></i><b style="font-size: 24px; ">Receitas</b>');
        
        $vbox->add($panel);
        $panel->add($this->form);

        $panel->addHeaderActionLink('<b>Novo</b>', new TAction(['ReceitasForm', 'onNovo'], ['register_state' => 'false']), 'fa:plus green');
        $panel->addFooter($this->pageNavigation);
        
        parent::add($panel);
    }

    public static function onDelete($param)
    {
        // Cria ação de confirmação
        $action = new TAction([__CLASS__, 'Delete']);
        $action->setParameters($param); // passa o ID   

        // Exibe a caixa de confirmação
        new TQuestion('Você tem certeza que deseja excluir esta receita?', $action);
    }   

    public static function Delete($param)
    {
        try {
            TTransaction::open('bolso');    

            $receita = new Receita($param['key']);
            $receita->delete(); 

            TTransaction::close();  

            new TMessage('info', 'receita excluída com sucesso');

            TApplication::loadPage(__CLASS__, 'onReload');

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

}
