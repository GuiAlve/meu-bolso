<?php

class BancoLista extends TPage
{
    use Adianti\Base\AdiantiStandardListTrait;

    public function __construct()
    {
        parent::__construct();

        $this->setDatabase('bolso');
        $this->setActiveRecord('Banco');

        // Filtros com subselects para campos de entidade
        $this->addFilterField('nome', 'like', 'nome');
        $this->addFilterField('agencia', 'like', 'agencia');
        $this->addFilterField('conta', 'like', 'conta');
        $this->addFilterField('id', '=', 'id');

        // Datagrid
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->width = '100%';
        $this->datagrid->disableDefaultClick();

        $col_id            = new TDataGridColumn('id', 'Código', 'left', );
        $col_nome          = new TDataGridColumn('nome', 'Nome', 'left');
        $col_agencia          = new TDataGridColumn('agencia', 'Agência', 'left');
        $col_conta          = new TDataGridColumn('conta', 'Conta', 'left');

        $col_id->setAction(new TAction([$this, 'onReload']), ['order' => 'id']);
        $col_nome->setAction(new TAction([$this, 'onReload']), ['order' => 'entidade->nome']);

        $this->datagrid->addColumn($col_nome);
        $this->datagrid->addColumn($col_agencia);
        $this->datagrid->addColumn($col_conta);

        // Ações
        $editar = new TDataGridAction(['BancoForm', 'onEdit'], ['id' => '{id}', 'register_state' => 'false']);
        $this->datagrid->addAction($editar, 'Editar', 'fa:edit blue');

        $deletar = new TDataGridAction([$this, 'onDelete'], ['id' => '{id}', 'register_state' => 'false']);
        $this->datagrid->addAction($deletar, 'Inativar', 'far:trash-alt red');

        $this->datagrid->createModel();

        // Formulário de busca
        $this->form = new TForm('form_busca_banco');
        $this->form->setProperty('onkeydown', 'if(event.keyCode == 13) { event.preventDefault(); return false; }');
        $this->form->add($this->datagrid);

        $id      = new TEntry('id');
        $nome    = new TEntry('nome');
        $agencia = new TEntry('agencia');
        $conta   = new TEntry('conta');

        $id->tabindex = -1;
        $nome->tabindex = -1;
        $agencia->tabindex = -1;
        $conta->tabindex = -1;

        $id->exitOnEnter();
        $nome->exitOnEnter();

        $id->setExitAction(new TAction( [$this, 'onSearch'], ['static' => '1']) );
        $nome->setExitAction(new TAction([$this, 'onSearch'], ['static' => '1']));
        $conta->setExitAction(new TAction( [$this, 'onSearch'], ['static' => '1']) );
        $agencia->setExitAction(new TAction([$this, 'onSearch'], ['static' => '1']));

        $tr = new TElement('tr');
        $this->datagrid->prependRow($tr);
        
        // Cria as células da linha de filtros
        $tdEmpty1       = TElement::tag('td', '');
        $tdEmpty2       = TElement::tag('td', '');
        $tdnome         = TElement::tag('td', $nome);
        $tdagencia      = TElement::tag('td', $agencia);
        $tdconta        = TElement::tag('td', $conta);

        // Adiciona as células à linha
        $tr->add($tdEmpty1);
        $tr->add($tdEmpty2);
        $tr->add($tdnome);
        $tr->add($tdagencia);
        $tr->add($tdconta);

        $this->form->addField($id);
        $this->form->addField($nome);
        $this->form->addField($agencia);
        $this->form->addField($conta);

        $this->form->setData(TSession::getValue(__CLASS__ . '_filter_data'));

        $this->pageNavigation = new TPageNavigation();
        $this->pageNavigation->setAction(new TAction( [$this, 'onReload' ]));
        $this->pageNavigation->enableCounters();
        
        $page = New TVBox();
        $page->style = 'width: 100%';
        $page->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $panel = new TPanelGroup('<i class="fa-solid fa-building-columns fa-2x" style="margin-right: 8px;"></i><b style="font-size: 24px; ">Bancos</b>');
        $page->add($panel);
        $panel->getBody()->style = "overflow-x:auto;";
        $panel->add($this->form);

        $panel->addHeaderActionLink('<b>Novo</b>', new TAction(['BancoForm', 'onNovo'], ['register_state'=>'false']), 'fa:plus green');
        $panel->addFooter($this->pageNavigation);
        
        parent::add($page);
        
    }

    public function onDelete($param)
    {
        try {
            TTransaction::open('bolso');

            $banco = new Banco($param['key']);

            $mensagem = "Deseja realmente inativar o cliente <b>{$banco->nome}</b>?";

            $acao = new TAction([$this, 'onDeleteConfirmed'], ['key' => $param['key']]);

            new TQuestion($mensagem, $acao);

            TTransaction::close();

        } catch (Exception $e) {

            new TMessage('error', $e->getMessage());

            TTransaction::rollback();
        }
    }

    public static function onDeleteConfirmed($param)
    {
        try {
            TTransaction::open('bolso');

            $banco = new Banco($param['key']);

            $banco->desativar();

            $pos_action = new TAction(['BancoLista', 'onReload']);

            new TMessage('info', 'Banco excluído com sucesso', $pos_action);

            TTransaction::close();

        } catch (Exception $e) {

            new TMessage('error', $e->getMessage());

            TTransaction::rollback();
        }
    }
}
