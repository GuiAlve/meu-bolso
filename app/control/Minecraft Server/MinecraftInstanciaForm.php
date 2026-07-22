<?php

use Adianti\Registry\TSession;
use Adianti\Database\TTransaction;
use Adianti\Database\TRepository;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Validator\TRequiredValidator;
use Adianti\Widget\Form\TEntry;
use Adianti\Widget\Form\TForm;
use Adianti\Widget\Form\TLabel;
use Adianti\Wrapper\BootstrapFormBuilder;

/**
 * MinecraftInstanciaForm
 *
 * Cortina lateral para cadastrar o tipo de instância EC2 e a tarifa (US$/hora)
 * usada para estimar o custo das sessões do servidor Minecraft.
 */
class MinecraftInstanciaForm extends TPage
{
    private $form;

    use Adianti\Base\AdiantiStandardFormTrait;

    public function __construct()
    {
        parent::__construct();
        $this->setDatabase('bolso');
        $this->setActiveRecord('MinecraftInstancia');

        parent::setTargetContainer('adianti_right_panel');

        $this->form = new BootstrapFormBuilder('minecraft_instancia_form');
        $this->form->setFormTitle('<i class="fa-solid fa-server" style="color:#5b8731"></i>&nbsp; Instância EC2');
        $this->form->setProperty('style', 'margin:0;border:0');
        $this->form->addHeaderActionLink('Fechar', new TAction([$this, 'onClose']), 'fa:times red');
        $this->form->setClientValidation(true);

        $id         = new TEntry('id');
        $tipo       = new TEntry('tipo');
        $custo_hora = new TEntry('custo_hora');

        $id->setEditable(false);
        $id->setSize('30%');
        $tipo->setSize('100%');
        $tipo->setMaxLength(64);
        $tipo->setId('tipo');
        $tipo->setProperty('placeholder', 'ex: t3.medium');
        $custo_hora->setSize('100%');
        $custo_hora->setNumericMask(4, '.', ',', false);
        $custo_hora->setProperty('autocomplete', 'off');

        $tipo->addValidation('Tipo de instância', new TRequiredValidator);
        $custo_hora->addValidation('Custo por hora', new TRequiredValidator);

        $this->form->addFields([new TLabel('ID')], [$id]);
        $this->form->addFields([new TLabel('<b>Tipo de instância*</b>')], [$tipo]);
        $this->form->addFields([new TLabel('<b>Custo por hora (US$)*</b>')], [$custo_hora]);

        $this->form->addAction('Salvar', new TAction([$this, 'onSave']), 'fa:save green');

        parent::add($this->form);
    }

    /**
     * Carrega a configuração já cadastrada pelo usuário (se houver).
     */
    public function onEdit($param)
    {
        try {
            TTransaction::open('bolso');

            $instancia = self::getUserInstancia();

            if ($instancia) {
                TForm::sendData('minecraft_instancia_form', $instancia, false, false);
            }

            TTransaction::close();
        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    public function onSave($param)
    {
        try {
            TTransaction::open('bolso');

            $this->form->validate();
            $data = $this->form->getData();

            // Mantém uma única configuração por usuário
            $instancia = self::getUserInstancia();
            if (!$instancia) {
                $instancia = new MinecraftInstancia;
            }

            // Máscara US-style (dec '.', milhar ','): remove milhares antes de converter
            $instancia->tipo       = $data->tipo;
            $instancia->custo_hora = (float) str_replace(',', '', (string) $data->custo_hora);
            $instancia->usuario_id = TSession::getValue('userid');
            $instancia->store();

            TTransaction::close();

            $pos_action = new TAction(['MinecraftServer', 'onReload']);
            TScript::create('Template.closeRightPanel()');
            new TMessage('info', 'Instância salva com sucesso!', $pos_action);

        } catch (Exception $e) {
            new TMessage('error', $e->getMessage());
            TTransaction::rollback();
        }
    }

    /**
     * Retorna o registro de configuração do usuário logado, ou null.
     * Deve ser chamado dentro de uma transação aberta.
     */
    private static function getUserInstancia()
    {
        $criteria = new TCriteria();
        $criteria->add(new TFilter('usuario_id', '=', TSession::getValue('userid')));
        $criteria->setProperty('order', 'id');
        $criteria->setProperty('direction', 'desc');

        $repo    = new TRepository('MinecraftInstancia');
        $objects = $repo->load($criteria);

        return $objects[0] ?? null;
    }

    public static function onClose()
    {
        TScript::create('Template.closeRightPanel()');
    }
}
