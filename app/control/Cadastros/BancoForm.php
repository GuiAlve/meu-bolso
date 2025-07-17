<?php

use Adianti\Validator\TRequiredValidator;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TForm;
use Adianti\Wrapper\BootstrapFormBuilder;
use Adianti\Widget\Form\TLabel;
use Adianti\Widget\Form\TButton;
use Adianti\Widget\Form\TCombo;
use Adianti\Widget\Form\THidden;
use Adianti\Widget\Form\TUniqueSearch;
use Adianti\Widget\Wrapper\TDBEntry;
use Adianti\Widget\Wrapper\TDBUniqueSearch;

class BancoForm extends TPage
{
    private $form;

    use Adianti\Base\AdiantiStandardFormTrait;

    public function __construct()
    {
        parent::__construct();
        $this->setDatabase('bolso');
        $this->setActiveRecord('Banco');

        parent::setTargetContainer('adianti_right_panel');

        $this->form = new BootstrapFormBuilder('banco_form');
        $this->form->setFormTitle('Cadastro de Banco');
        $this->form->setProperty('style', 'margin:0;border:0');
        $this->form->addHeaderActionLink('Fechar', new TAction([$this, 'onClose']), 'fa:times red');
        $this->form->setClientValidation(true);

        // Campos
        $id             = new TEntry('id');
        $nome           = new TEntry('nome');
        $agencia        = new TEntry('agencia');
        $conta          = new TEntry('conta');

        $id->setEditable(false);
        $nome->addValidation('Nome', new TRequiredValidator);
        $nome->setId('nome');
        $agencia->setId('agencia');
        $conta->setId('conta');

        // Tamanhos
        $id->setSize('30%');
        $nome->setSize('100%');
        $agencia->setSize('50%');
        $conta->setSize('50%');

        // Layout
        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('<b>Nome*</b>')], [$nome]);
        $this->form->addFields([new TLabel('Agência')], [$agencia]);
        $this->form->addFields([new TLabel('Conta')], [$conta]);

        parent::add($this->form);
    }

    public function onSave($param)
    {
        try {
            TTransaction::open('bolso');

            $this->form->validate();

            $banco = new Banco();

            $banco->nome = $param['nome'];
            $banco->ativo = 1;            
            $banco->agencia = $param['agencia'];
            $banco->conta = $param['conta'];

            $banco->store();

            TTransaction::close();

            BancoForm::onClose();

            $pos_action = new TAction(['BancoLista', 'onReload']);

            TScript::create('Template.closeRightPanel()');

            new TMessage('info', 'Banco salvo com sucesso!', $pos_action);

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onEdit($param)
    {
        try {
            TTransaction::open('bolso');

            $data = new Banco($param['id']);

            $this->form->setFormTitle("Banco - " . $data->nome);



            TForm::sendData('banco_form', $data, '', false);

            
            $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');

            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }
    
    public static function onClose()
    {
        TScript::create('Template.closeRightPanel()');
    }

    public function onNovo()
    {
        $this->form->clear();
        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');
    }
}
    