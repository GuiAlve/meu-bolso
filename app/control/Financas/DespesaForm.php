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

class DespesaForm extends TPage
{
    private $form;

    use Adianti\Base\AdiantiStandardFormTrait;

    public function __construct()
    {
        parent::__construct();

        $this->setDatabase('bolso');
        $this->setActiveRecord('Despesa');

        parent::setTargetContainer('adianti_right_panel');

        $this->form = new BootstrapFormBuilder('form_despesa');

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
        $this->form->addFields([new TLabel('Data'), $data_hora]);
        $this->form->addFields([new TLabel('Parcelas'), $parcelas]);


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

            $despesa = new Despesa($param['key']);

            if ($despesa->parcelamento_registro) {
                $parcelas = Despesa::where('parcelamento_registro', '=', $despesa->parcelamento_registro)
                            ->orderBy('data_hora')->load();

                $despesa->valor = Dinheiro::formatoSimples(array_sum(array_map(function($p) {
                    return $p->valor;
                }, $parcelas)));

                $despesa->parcelas = count($parcelas);

                // Pega os dados comuns da primeira parcela
                $primeira = $parcelas[0];
                $despesa->descricao = $primeira->descricao;
                $despesa->categoria_id = $primeira->categoria_id;
                $despesa->banco_id = $primeira->banco_id;
                $despesa->data_hora = TDate::date2br($primeira->data_hora);
                $despesa->observacoes = $primeira->observacoes;
                $despesa->id = $primeira->id;

            } else {
                $despesa->id = $despesa->id;
                $despesa->valor = Dinheiro::formatoSimples($despesa->valor);
                $despesa->data_hora = TDate::date2br($despesa->data_hora);
            }

            TForm::sendData('form_despesa', $despesa, '', false);
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

            // Se está editando: apagar despesas antigas
            if (!empty($data->id)) {
                $despesa_antiga = new Despesa($data->id);

                if (!empty($despesa_antiga->parcelamento_registro)) {
                    // Apaga todas as despesas do parcelamento
                    $despesas = Despesa::where('parcelamento_registro', '=', $despesa_antiga->parcelamento_registro)->load();
                    foreach ($despesas as $old) {
                        $old->delete();
                    }
                } else {
                    $despesa_antiga->delete();
                }
            }

            if (empty($data->data_hora)) {
                $data->data_hora = date('Y-m-d H:i:s');
            }

            if ($data->parcelas > 1) {
                    $data_inicial = new DateTime($data->data_hora);
                    $valor_total  = Dinheiro::somenteNumeros($data->valor);
                    $parcelas     = (int) $data->parcelas;              

                    $valor_parcela = intval($valor_total / $parcelas);
                    $resto         = $valor_total % $parcelas;              

                    // ID único do parcelamento
                    $uuid = uniqid('', true);               

                    for ($i = 0; $i < $parcelas; $i++) {
                        $despesa = new Despesa();
                        $despesa->fromArray((array) $data);
                        unset($despesa->id);                

                        
                        if ($i == 0) {
                            // 1ª parcela: data exatamente como veio do form
                            $data_parcela = clone $data_inicial;
                        } else {
                            // Demais: primeiro dia dos meses subsequentes
                            $data_parcela = (clone $data_inicial)->modify("first day of +{$i} month");
                        }               

                        $despesa->data_hora = $data_parcela->format('Y-m-d H:i:s');             

                        // valor da parcela
                        $despesa->valor = $valor_parcela;
                        if ($i == $parcelas - 1) {
                            $despesa->valor += $resto;
                        }               

                        $despesa->parcela              = ($i + 1) . "/{$parcelas}";
                        $despesa->parcelamento_registro = $uuid;                

                        $despesa->store();
                    }
                } else {

                $despesa = new Despesa();
                $despesa->fromArray((array) $data);

                $despesa->valor = Dinheiro::somenteNumeros($despesa->valor);

                $despesa->store();
            }

            TTransaction::close();

            $pos_action = new TAction(['DespesasLista', 'onReload']);
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