<?php
/**
 * LancamentoRapido
 *
 * Tela simples com dois botões grandes para lançar Despesa ou Receita.
 * Cada botão abre o formulário correspondente no painel lateral.
 *
 * @version    8.1
 * @package    control
 * @subpackage Financas
 * @author     Guilherme Muller
 */
class LancamentoRapido extends TPage
{
    public function __construct()
    {
        parent::__construct();

        $container = new TElement('div');
        $container->style = 'max-width:520px; margin:24px auto; display:flex; flex-direction:column; gap:16px; padding:0 12px;';

        $container->add($this->createButton(
            'DespesaForm',
            'Nova Despesa',
            'fa-minus',
            '#dc3545'
        ));

        $container->add($this->createButton(
            'ReceitasForm',
            'Nova Receita',
            'fa-plus',
            '#198754'
        ));

        parent::add($container);
    }

    /**
     * Cria um botão grande que abre o formulário no painel lateral.
     */
    private function createButton($class, $label, $icon, $color)
    {
        $button = new TElement('a');
        $button->generator = 'adianti';
        $button->href      = "index.php?class={$class}&method=onNovo&register_state=false";
        $button->style     = "display:flex; align-items:center; gap:18px; padding:22px 24px; "
                           . "border-radius:12px; background:{$color}; color:#fff; text-decoration:none; "
                           . "font-size:20px; font-weight:600; box-shadow:0 3px 10px rgba(0,0,0,.15);";

        $iconEl = new TElement('span');
        $iconEl->style = 'width:48px; height:48px; border-radius:50%; background:rgba(255,255,255,.2); '
                       . 'display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0;';
        $iconEl->add("<i class=\"fa-solid {$icon}\"></i>");

        $button->add($iconEl);
        $button->add($label);

        return $button;
    }
}
