<?php

use Adianti\Widget\Form\TDate;
use Adianti\Widget\Wrapper\TDBCombo;
use Adianti\Widget\Wrapper\TDBSelect;

class DespesasLista extends TPage
{
    use Adianti\Base\AdiantiStandardListTrait;

    public function __construct()
    {
        parent::__construct();

        $this->setDatabase('bolso');
        $this->setActiveRecord('Despesa');
        $criteria = new TCriteria();
        $criteria->add( new TFilter( 'usuario_id', '=', TSession::getValue('userid')));
        $this->setCriteria($criteria);

        $this->setDefaultOrder('created_at', 'desc');
        $this->setLimit(100);

        $this->addFilterField('descricao', 'like', 'descricao');
        $this->addFilterField('(SELECT nome FROM banco WHERE id = banco_id)', 'like', 'banco');
        $this->addFilterField('MONTH(data_hora)', '=', 'filtro_mes');
        $this->addFilterField('YEAR(data_hora)', '=', 'filtro_ano');
        $this->addFilterField('data_hora', 'like', 'data');
        $this->addFilterField('(SELECT id FROM categoria WHERE id = categoria_id)', '=', 'categoria');
        $this->addFilterField('valor', '=', 'valor',
            function($valor) {
                $valor = preg_replace("/[^0-9]/", "", $valor);
                return (int) $valor;
            }
        ); 

        $filtros = TSession::getValue(__CLASS__.'_filter_data');



        if (empty($filtros->filtro_mes) AND empty($filtros->filtro_ano)) {

            $inicio = date('Y-m-01') . ' 00:00:00';
            $fim    = date('Y-m-t') . ' 23:59:59';

            $criteria->add(new TFilter('data_hora', '>=', $inicio));
            $criteria->add(new TFilter('data_hora', '<=', $fim));

            $this->setCriteria($criteria);
        }

        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->width  = '100%';

        $col_id        = new TDataGridColumn('id', 'ID', 'right', '0px');
        $col_valor     = new TDataGridColumn('valor', 'Valor (R$)', 'center');
        $col_categoria = new TDataGridColumn('categoria_id', 'Categoria', 'center');
        $col_data      = new TDataGridColumn('data_hora', 'Data', 'center', '10%');
        $col_descricao = new TDataGridColumn('descricao', 'Descrição', 'center');
        $col_conta     = new TDataGridColumn('banco->nome', 'Conta', 'center');

        // Valor em verde
        $col_valor->setTransformer(function($valor) {
            $formatado = 'R$ ' . number_format($valor / 100, 2, ',', '.');
            return "<span style='color:rgb(240, 49, 49);'>$formatado</span>";
        });

        $col_data->setTransformer(function($value){
            $dt = TDate::date2br($value);
            return $dt;
        });

        $col_categoria->setTransformer(function($value) {
            if (!$value) {
                return '';
            }

            $categoria = new Categoria($value);
        
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

        $col_valor->setTotalFunction( function($values) {
            return $soma = array_sum((array) $values);
            return "<span style='color:rgb(202, 45, 45); font-weight: bold;'>$formatado</span>";
        });

        $col_conta->setTotalFunction( function($value) {
            return $value;
        });

        $action1 = new TDataGridAction(['DespesaForm', 'onEdit'],   ['key' => '{id}'] );
        $this->datagrid->addAction($action1, 'Editar',   'far:edit blue');

        $action2 = new TDataGridAction([$this, 'onDelete'], ['key' => '{id}']);
        $this->datagrid->addAction($action2, 'Excluir', 'far:trash-alt red');

        $col_conta->enableAutoHide(500);
        $col_data->enableAutoHide(600);
        $col_descricao->enableAutoHide(700);

        //$this->datagrid->addColumn($col_id);
        $this->datagrid->addColumn($col_valor);
        $this->datagrid->addColumn($col_data);
        $this->datagrid->addColumn($col_conta);
        $this->datagrid->addColumn($col_categoria);
        $this->datagrid->addColumn($col_descricao);

        $this->datagrid->createModel();

        //fim da datagrid

        $this->form =  new TForm;
        $this->form->setProperty('onkeydown', 'if(event.keyCode == 13) { event.preventDefault(); return false; }');
        $this->form->add($this->datagrid);

        $valor = new TEntry('valor');
        $data = new TDate('data');
        $banco = new TEntry('banco');
        $categoria = new TDBCombo('categoria', 'bolso', 'Categoria', 'id', 'nome');
        $descricao = new TEntry('descricao');

        //filtro de mês
        $filtro_mes = new TCombo('filtro_mes');
        $filtro_mes->setSize('100%');
        $filtro_mes->addItems([
            '1'=>'Jan', '2'=>'Fev', '3'=>'Mar', '4'=>'Abr',
            '5'=>'Mai', '6'=>'Jun', '7'=>'Jul', '8'=>'Ago',
            '9'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'
        ]);
        $mes_atual = date('n');  // Ex: 7

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

        $valor->setNumericMask(2, ',', '.', true);

        $data->setMask('dd/mm/yyyy');       
        $data->setDatabaseMask('yyyy-mm-dd');

        $valor->tabindex = -1;
        $banco->tabindex = -1;
        $categoria->tabindex = -1;
        $data->tabindex = -1;
        $descricao->tabindex = -1;

        $valor->exitOnEnter();
        $banco->exitOnEnter();
        //$categoria->exitOnEnter();
        $descricao->exitOnEnter();

        $valor->setExitAction(new TAction( [$this, 'onSearch'], ['static' => '1']) );
        $banco->setExitAction(new TAction( [$this, 'onSearch'], ['static' => '1']) );
        $data->setExitAction(new TAction( [$this, 'onSearch'], ['static' => '1']) );
        $descricao->setExitAction(new TAction( [$this, 'onSearch'], ['static' => '1']) );
        $categoria->setChangeAction( new TAction([$this, 'onSearch'], ['static' => '1']));      
        
        $tr = new TElement('tr');
        $this->datagrid->prependRow($tr);

        $tdEmpty1       = TElement::tag('td', '');
        $tdEmpty2       = TElement::tag('td', '');
        $tdValor       = TElement::tag('td', $valor);
        $tdData        = TElement::tag('td', $data);
        $tdConta       = TElement::tag('td', $banco);
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
        $this->form->addField($banco);
        $this->form->addField($categoria);
        $this->form->addField($descricao);

        $this->form->setData(TSession::getValue(__CLASS__.'_filter_data'));
        
        $vbox = New TVBox();
        $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $panel = new TPanelGroup('<i class="fa-solid fa-money-check-dollar fa-2x" style="margin-right: 8px;"></i><b style="font-size: 24px; ">Despesas</b>');
        
        $vbox->add($panel);
        $panel->add($this->form);

        $panel->addHeaderActionLink('<b>Novo</b>', new TAction(['DespesaForm', 'onNovo'], ['register_state' => 'false']), 'fa:plus green');
        
        parent::add($panel);
    }

public static function onDelete($param)
{
    TTransaction::open('bolso');
    $despesa = new Despesa($param['key']);
    TTransaction::close();

    if (!empty($despesa->parcelamento_registro)) {
        // Deletar todas as parcelas do mesmo grupo
        $action = new TAction([__CLASS__, 'deleteParcelamento']);
        $action->setParameters(['registro' => $despesa->parcelamento_registro]);

        new TQuestion('Essa despesa faz parte de um parcelamento. Deseja excluir TODAS as parcelas?', $action);
    } else {
        // Deletar apenas essa despesa
        $action = new TAction([__CLASS__, 'deleteUnica']);
        $action->setParameters(['key' => $despesa->id]);

        new TQuestion('Você tem certeza que deseja excluir esta despesa?', $action);
    }
}

    public static function deleteUnica($param)
    {
        try {
            TTransaction::open('bolso');

            $despesa = new Despesa($param['key']);
            $despesa->delete();

            TTransaction::close();

            new TMessage('info', 'Despesa excluída com sucesso');
            TApplication::loadPage(__CLASS__, 'onReload');

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public static function deleteParcelamento($param)
    {
        try {
            TTransaction::open('bolso');
        
            $registro = $param['registro'];
            $despesas = Despesa::where('parcelamento_registro', '=', $registro)->load();
        
            foreach ($despesas as $despesa) {
                $despesa->delete();
            }
        
            TTransaction::close();
        
            new TMessage('info', 'Todas as parcelas foram excluídas com sucesso');
            TApplication::loadPage(__CLASS__, 'onReload');
        
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
}
