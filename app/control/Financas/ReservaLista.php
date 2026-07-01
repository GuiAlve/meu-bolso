<?php

use Adianti\Registry\TSession;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Widget\Wrapper\TDBCombo;
use Adianti\Widget\Container\TPanelGroup;

class ReservaLista extends TPage
{
    use Adianti\Base\AdiantiStandardListTrait;
    use Adianti\Base\AdiantiStandardFormTrait;

    public function __construct()
    {
        parent::__construct();

        $this->setDatabase('bolso');
        $this->setActiveRecord('Reserva');
        $criteria = new TCriteria();
        $criteria->add(new TFilter('usuario_id', '=', TSession::getValue('userid')));
        $this->setCriteria($criteria);

        $this->setDefaultOrder('id', 'desc');

        // formulário
        $this->form = new BootstrapFormBuilder('form_reserva');

        $id  = new TEntry('id');

        $cat_criteria = new TCriteria();
        $cat_criteria->add(new TFilter('usuario_id', '=', TSession::getValue('userid')));
        $categoria = new TDBCombo('categoria_id', 'bolso', 'Categoria', 'id', 'nome', 'nome', $cat_criteria);

        $valor = new TEntry('valor');
        $ativo = new TCombo('ativo');
        $ativo->addItems(['1' => 'Ativa', '0' => 'Inativa']);

        $id->setEditable(FALSE);
        $id->setSize('100%');
        $categoria->setSize('100%');
        $valor->setSize('100%');
        $valor->setNumericMask(2, ',', '.', false);
        $valor->setProperty('autocomplete', 'off');
        $ativo->setSize('100%');
        $ativo->setValue('1');

        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('Categoria', 'red')], [$categoria], [new TLabel('Valor reservado (R$)', 'red')], [$valor]);
        $this->form->addFields([new TLabel('Situação')], [$ativo]);

        $categoria->addValidation('Categoria', new TRequiredValidator);
        $valor->addValidation('Valor reservado', new TRequiredValidator);

        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $this->form->addActionLink('Limpar', new TAction([$this, 'onClear']), 'fa:eraser red');
        $this->form->addActionLink('Atualizar Lista', new TAction([$this, 'onReload']), 'fa:rotate orange');

        // datagrid
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->width = '100%';

        $col_id        = new TDataGridColumn('id', 'Id', 'right', '8%');
        $col_categoria = new TDataGridColumn('categoria_id', 'Categoria', 'center', '40%');
        $col_valor     = new TDataGridColumn('valor', 'Valor reservado', 'center', '32%');
        $col_ativo     = new TDataGridColumn('ativo', 'Situação', 'center', '20%');

        $col_valor->setTransformer(function($valor) {
            return 'R$ ' . number_format($valor / 100, 2, ',', '.');
        });

        $col_categoria->setTransformer(function($value) {
            if (!$value) {
                return '';
            }
            $categoria = new Categoria($value);
            $bgcolor = $categoria->cor ?? '#999999';
            $nome = htmlspecialchars($categoria->nome, ENT_QUOTES);
            return "<span style='
                background-color: {$bgcolor};
                color: #fff;
                padding: 4px 10px;
                border-radius: 12px;
                font-size: 0.9em;
                font-weight: 600;
                display: inline-block;
                min-width: 80px;
                text-align: center;
            '>{$nome}</span>";
        });

        $col_ativo->setTransformer(function($value) {
            if ($value == 1) {
                return "<span style='color:#2e7d32; font-weight:600;'>Ativa</span>";
            }
            return "<span style='color:#9e9e9e; font-weight:600;'>Inativa</span>";
        });

        $this->datagrid->addColumn($col_id);
        $this->datagrid->addColumn($col_categoria);
        $this->datagrid->addColumn($col_valor);
        $this->datagrid->addColumn($col_ativo);

        $col_id->setAction(new TAction([$this, 'onReload']), ['order' => 'id']);
        $col_valor->setAction(new TAction([$this, 'onReload']), ['order' => 'valor']);

        $action1 = new TDataGridAction([$this, 'onEdit'],   ['key' => '{id}']);
        $action2 = new TDataGridAction([$this, 'onDelete'], ['key' => '{id}']);
        $this->datagrid->addAction($action1, 'Editar', 'far:edit blue');
        $this->datagrid->addAction($action2, 'Excluir', 'far:trash-alt red');

        $this->datagrid->createModel();

        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));

        $this->pageNavigation = new TPageNavigation();
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->enableCounters();

        $panel = new TPanelGroup('<i class="fa-solid fa-piggy-bank fa-2x" style="margin-right: 8px;"></i><b style="font-size: 24px;">Reservas por categoria</b>');
        $panel->add($this->form);
        $panel->add($this->datagrid);
        $panel->addFooter($this->pageNavigation);

        $vbox->add($panel);

        parent::add($vbox);
    }

    public function onEdit($param)
    {
        try {
            if (isset($param['key'])) {
                TTransaction::open($this->database);

                $reserva = new Reserva($param['key']);
                $reserva->valor = Dinheiro::formatoSimples($reserva->valor);

                $this->form->setData($reserva);

                TTransaction::close();
            }
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onSave($param)
    {
        try {
            TTransaction::open($this->database);

            $data = $this->form->getData();
            $this->form->validate();

            // Impede duas reservas para a mesma categoria (evitaria contagem dupla no dashboard)
            $critCheck = new TCriteria;
            $critCheck->add(new TFilter('usuario_id', '=', TSession::getValue('userid')));
            $critCheck->add(new TFilter('categoria_id', '=', $data->categoria_id));
            if (!empty($data->id)) {
                $critCheck->add(new TFilter('id', '!=', $data->id));
            }
            $repoCheck = new TRepository('Reserva');
            if ($repoCheck->count($critCheck) > 0) {
                throw new Exception('Já existe uma reserva para esta categoria.');
            }

            $reserva = new Reserva;
            $reserva->fromArray((array) $data);
            $reserva->valor = Dinheiro::somenteNumeros((string) $data->valor);
            $reserva->ativo = ($data->ativo === '1') ? 1 : 0;

            $reserva->store();

            $this->form->setData($this->form->getData());

            TTransaction::close();

            $this->onReload($param);
            new TMessage('info', 'Reserva salva com sucesso');

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            $this->form->setData($this->form->getData());
            TTransaction::rollback();
        }
    }
}
