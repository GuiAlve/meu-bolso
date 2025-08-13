<?php

use Adianti\Control\TPage;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Registry\TSession;
use Adianti\Widget\Form\TDate;
use Adianti\Widget\Form\TFile;
use Adianti\Widget\Form\TNumeric;
use Adianti\Widget\Wrapper\TDBCombo;
use Adianti\Widget\Wrapper\TDBUniqueSearch;

class DespesaRecorrenteForm extends TPage
{
    private $form;

    use Adianti\Base\AdiantiStandardFormTrait;

    public function __construct()
    {
        parent::__construct();

        $this->setDatabase('bolso');
        $this->setActiveRecord('DespesaRecorrente');

        parent::setTargetContainer('adianti_right_panel');

        $this->form = new BootstrapFormBuilder('form_despesa_recorrente');

        // Campos
        $id            = new THidden('id');
        $valor         = new TEntry('valor');
        $descricao     = new TEntry('descricao');
        $criteria = new TCriteria();
        $criteria->add(new TFilter('usuario_id', '=', TSession::getValue('userid')));
        $categoria     = new TDBCombo('categoria_id', 'bolso', 'Categoria', 'id', 'nome', '', $criteria);
        $data_hora     = new TDate('data_hora');
        $criteria->add(new TFilter('ativo', '=', '1'));
        $banco         = new TDBCombo('banco_id', 'bolso', 'Banco', 'id', 'nome', '', $criteria);
        $parcelas      = new TNumeric('parcelas', '', '', '');           // nº de parcelas

        $valor->setNumericMask(2, ',', '.', false);

        $valor->setSize('100%');
        $valor->setProperty('autocomplete', 'off');

        $descricao->setSize('100%');
        $descricao->setProperty('autocomplete', 'off');

        $categoria->setSize('100%');

        $banco->setSize('100%');

        $parcelas->setSize('100%');

        $data_hora->setSize('100%');
        $data_hora->setMask('dd/mm/yyyy');
        $data_hora->setDatabaseMask('yyyy/mm/dd');

        $this->form->addFields([$id]);
        $this->form->addFields([new TLabel('Valor (R$)', '#000', '14px', ''), $valor], [new TLabel('Categoria'), $categoria]);
        $this->form->addFields([new TLabel('Descrição'), $descricao]);
        $this->form->addFields([new TLabel('Banco'), $banco]);
        $this->form->addFields([new TLabel('Data de lançamento'), $data_hora]);

        $this->form->addHeaderActionLink('Fechar', new TAction([$this, 'onClose']), 'fa:times red');

        // Botões
        $btn_save = $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
        $btn_save->class = 'btn btn-sm btn-success';


        parent::add($this->form);
    }

    public function onEdit($param)
    {
        try {
            TTransaction::open($this->database);

            $despesa = new DespesaRecorrente($param['key']);

            $despesa->valor = Dinheiro::formatoSimples($despesa->valor);
            $despesa->data_hora = TDate::date2br($despesa->data_hora);
    
            TForm::sendData('form_despesa_recorrente', $despesa, '', false);
            TTransaction::close();

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onNovo($param)
    {

    }

    public function onSave($param)
    {
        try {
            TTransaction::open($this->database);

            $data = $this->form->getData();
            $this->form->validate();


            if (empty($data->data_hora)) {
                $data->data_hora = date('Y-m-d H:i:s');
            }

            $despesa = new DespesaRecorrente();
            $despesa->fromArray((array) $data);

            $despesa->valor = Dinheiro::somenteNumeros($despesa->valor);
            $despesa->store();
            
            TTransaction::close();

            $pos_action = new TAction(['DespesasRecorrentesLista', 'onReload']);
            new TMessage('info', 'Registro salvo com sucesso', $pos_action);
            TScript::create('Template.closeWindow()');

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }


    public static function onClose($param)
    {
        TScript::create("Template.closeRightPanel()");
    }


}