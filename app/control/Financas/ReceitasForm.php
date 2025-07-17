<?php

use Adianti\Widget\Form\TDate;

class ReceitasForm extends TPage
{
    private $form;

    use Adianti\Base\AdiantiStandardFormTrait;

    public function __construct()
    {
        parent::__construct();
        parent::setTargetContainer('adianti_right_panel');

        $this->setDatabase('bolso');
        $this->setActiveRecord('Receita');

        $this->form = new BootstrapFormBuilder('form_receita');

        // Campos
        $id         = new THidden('id');
        $valor      = new TEntry('valor');
        $descricao  = new TEntry('descricao');
        $categoria  = new TDBCombo('categoria_id', 'bolso', 'CategoriaReceita', 'id', 'nome');
        //$cartao     = new TDBCombo('cartao_id', 'bolso', 'Cartao', 'id', 'nome');
        $data_hora  = new TDate('data_hora');
        $obs        = new TText('observacoes');

        $valor->setNumericMask(2, ',', '.', false);

        // Labels e tamanhos
        $valor->setSize('100%');
        $valor->setProperty('autocomplete', 'off');

        $descricao->setSize('100%');
        $descricao->setProperty('autocomplete', 'off');

        $categoria->setSize('100%');
        //$cartao->setSize('100%');

        $data_hora->setSize('100%');
        $data_hora->setMask('dd/mm/yyyy');
        $data_hora->setDatabaseMask('yyyy/mm/dd');

        $obs->setSize('100%', 70);
        $obs->setProperty('autocomplete', 'off');

        $this->form->addFields([$id]);
        $this->form->addFields([new TLabel('Valor (R$)', '#000', '14px', ''), $valor], [new TLabel('Categoria'), $categoria]);
        $this->form->addFields([new TLabel('Descrição'), $descricao]);
        //$this->form->addFields([new TLabel('Cartão'), $cartao]);
        $this->form->addFields([new TLabel('Data'), $data_hora]);
        $this->form->addFields([new TLabel('Observações'), $obs]);

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

        $data = new Receita($param['key']);

        $data->valor = Dinheiro::formatoSimples($data->valor);
        $data->data_hora = TDate::date2br($data->data_hora);

        TForm::sendData('form_receita', $data, '', false);

        }catch (Exception $e) {
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

            $Receita = new Receita();

            $Receita->fromArray( (array) $data );

            $Receita->valor = Dinheiro::somenteNumeros($Receita->valor);

            if (!$Receita->data_hora) {
                $dth = new DateTime();
                $Receita->data_hora = $dth->format('Y-m-d H:i:s');
            }

            $Receita->store();

            $this->form->setData($Receita);

            TTransaction::close();

            $pos_action = new TAction(['ReceitasLista', 'onReload']);

            new TMessage('info', 'Registro salvo com sucesso', $pos_action);

            TScript::create('Template.closeWindow()');

            // Fecha a janela e recarrega a lista

            
        }catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

        public static function onClose($param)
    {
        TScript::create("Template.closeRightPanel()");
    }

}