<?php

use Adianti\Registry\TSession;

class DashboardReceitaDespesas extends TPage
{
    public function __construct($param = null)
    {
        parent::__construct();

        $mes = $param['mes'] ?? date('m');
        $ano = $param['ano'] ?? date('Y');

        if($mes AND $ano){
            $inicio = DateTime::createFromFormat('Y-m-d', "{$ano}-{$mes}-01")->setTime(0,0,0);
            $fim = (clone $inicio)->modify('last day of this month')->setTime(23,59,59);
        }else{
            $inicio = (new DateTime('first day of this month'))->setTime(0,0,0);
            $fim    = (new DateTime('last day of this month'))->setTime(23,59,59);
        }

        $this->form = new TForm('form_dashboard');

        // meses (1..12) com nomes
        $meses = [
            '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
            '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
            '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
        ];
        $comboMes = new TCombo('mes');
        $comboMes->addItems($meses);
        $comboMes->setSize('100%');
        if($mes){
            $comboMes->setValue($mes);
        }

        // anos (ex.: -5 .. +1 em relação ao atual)
        $anoAtual = (int) date('Y');
        $anos = [];
        for ($y = $anoAtual - 5; $y <= $anoAtual + 1; $y++) {
            $anos[(string)$y] = (string)$y;
        }
        $comboAno = new TCombo('ano');
        $comboAno->addItems($anos);
        $comboAno->setSize('100%');
        if($ano){
            $comboAno->setValue($ano);
        }

        // botão atualizar
        $btn = new TButton('btn_search');
        $btn->setAction(new TAction([$this, 'onSearch']), 'Atualizar');
        $btn->setImage('fa:search');
        $btn->class = 'btn btn-primary w-100';

        $this->form->setFields([$comboMes, $comboAno, $btn]);

        // layout do filtro (responsivo, em card no topo)
        $mkLabel = function($texto) {
            $lbl = new TElement('label');
            $lbl->style = 'font-size:0.8rem; color:#666; display:block; margin-bottom:3px;';
            $lbl->add($texto);
            return $lbl;
        };

        $colMes = new TElement('div');
        $colMes->class = 'col-6 col-md-3 col-lg-2';
        $colMes->add($mkLabel('Mês'));
        $colMes->add($comboMes);

        $colAno = new TElement('div');
        $colAno->class = 'col-6 col-md-3 col-lg-2';
        $colAno->add($mkLabel('Ano'));
        $colAno->add($comboAno);

        $colBtn = new TElement('div');
        $colBtn->class = 'col-12 col-md-3 col-lg-2 mt-2 mt-md-0';
        $colBtn->add($btn);

        $filterRow = new TElement('div');
        $filterRow->class = 'row g-2 align-items-end';
        $filterRow->add($colMes);
        $filterRow->add($colAno);
        $filterRow->add($colBtn);

        $filterCard = new TElement('div');
        $filterCard->class = 'card p-2 shadow-sm';
        $filterCard->style = 'margin: 0 10px 12px 10px;';
        $filterCard->add($filterRow);

        $this->form->add($filterCard);

        TTransaction::open('bolso');

        // Total Receitas
        $repoR = new TRepository('Receita');
        $critR = new TCriteria;
        $critR->add(new TFilter('usuario_id', '=', TSession::getValue('userid')));
        $critR->add(new TFilter('data_hora', '>=', $inicio->format('Y-m-d H:i:s')));
        $critR->add(new TFilter('data_hora', '<=', $fim->format('Y-m-d H:i:s')));
        $receitas = $repoR->load($critR);
        $totalReceitas = array_sum(array_map(fn($r) => (int) $r->valor, $receitas));

        // Total Despesas
        $repoD = new TRepository('Despesa');
        $critD = new TCriteria;
        $critD->add(new TFilter('usuario_id', '=', TSession::getValue('userid')));
        $critD->add(new TFilter('data_hora', '>=', $inicio->format('Y-m-d H:i:s')));
        $critD->add(new TFilter('data_hora', '<=', $fim->format('Y-m-d H:i:s')));
        $despesas = $repoD->load($critD);
        $totalDespesas = array_sum(array_map(fn($d) => (int) $d->valor, $despesas));

        // Saldo
        $saldo = $totalReceitas - $totalDespesas;

        // Cache de categorias para evitar múltiplas consultas (N+1)
        $catCache = [];
        $getCategoria = function($id) use (&$catCache) {
            if (!isset($catCache[$id])) {
                $catCache[$id] = new Categoria($id);
            }
            return $catCache[$id];
        };

        // Agrupa despesas por categoria (valor e cor) para o gráfico
        $group      = [];
        $groupColor = [];
        foreach ($despesas as $d) {
            $cat  = $getCategoria($d->categoria_id);
            $nome = $cat->nome ?? 'Sem categoria';
            $group[$nome]      = ($group[$nome] ?? 0) + (int) $d->valor;
            $groupColor[$nome] = $cat->cor ?? '#999999';
        }

        // ordena da maior para a menor fatia
        arsort($group);

        $dataPie   = [['Categoria', 'Total']];
        $pieColors = [];
        foreach ($group as $nome => $val) {
            $dataPie[]   = [$nome, round($val/100, 2)];
            $pieColors[] = $groupColor[$nome];
        }

        // Total usado por categoria no mês (base para as reservas)
        $usadoPorCategoria = [];
        foreach ($despesas as $d) {
            $usadoPorCategoria[$d->categoria_id] = ($usadoPorCategoria[$d->categoria_id] ?? 0) + (int) $d->valor;
        }

        // Reservas ativas do usuário
        $repoRes = new TRepository('Reserva');
        $critRes = new TCriteria;
        $critRes->add(new TFilter('usuario_id', '=', TSession::getValue('userid')));
        $critRes->add(new TFilter('ativo', '=', 1));
        $reservas = $repoRes->load($critRes);

        $totalReservado       = 0;
        $totalUsadoReserva    = 0;
        $totalReservaNaoUsada = 0; // parte reservada ainda não gasta (não conta despesa já lançada)
        $reservaItens         = [];

        foreach ($reservas as $res) {
            $cat   = $getCategoria($res->categoria_id);
            $usado = $usadoPorCategoria[$res->categoria_id] ?? 0;
            $restante = (int) $res->valor - $usado;

            $totalReservado       += (int) $res->valor;
            $totalUsadoReserva    += $usado;
            $totalReservaNaoUsada += max(0, $restante);

            $item = new stdClass;
            $item->nome      = $cat->nome ?? 'Sem categoria';
            $item->cor       = $cat->cor ?? '#999999';
            $item->reservado = (int) $res->valor;
            $item->usado     = $usado;
            $item->restante  = $restante;
            $item->pct       = $res->valor > 0 ? min(100, round($usado / $res->valor * 100)) : 0;
            $reservaItens[]  = $item;
        }

        TTransaction::close();

        // Saldo livre = saldo do mês menos a parte reservada AINDA NÃO gasta.
        // O que já foi usado da reserva já está descontado em Despesas, então
        // subtrair só o não-utilizado evita contar a despesa em dobro.
        $saldoLivre = $saldo - $totalReservaNaoUsada;

        // Cards de totais (usa o helper self::card)
        $cardsPrincipais =
            self::card('Receitas (mês)', $totalReceitas, 'green') .
            self::card('Despesas (mês)', $totalDespesas, 'red') .
            self::card('Saldo Restante', $saldo, 'blue') .
            self::card('Disponível após reservas', $saldoLivre, $saldoLivre < 0 ? '#e53935' : '#6a1b9a');

        $htmlContent = "<div class='row g-2 g-md-3'>{$cardsPrincipais}</div>";

        // Renderiza texto estático
        $panel = new TPanelGroup;
        $panel->add($htmlContent);

        // Bloco de reservas (só aparece se houver reservas ativas)
        if (!empty($reservas)) {
            $totalRestanteReserva = $totalReservado - $totalUsadoReserva;
            $restanteGeralColor   = $totalRestanteReserva < 0 ? '#e53935' : 'green';

            $cardsReserva =
                self::card('Total Reservado (mês)', $totalReservado, '#6a1b9a', 'col-6 col-md-4') .
                self::card('Usado da Reserva', $totalUsadoReserva, 'red', 'col-6 col-md-4') .
                self::card('Reserva Restante', $totalRestanteReserva, $restanteGeralColor, 'col-12 col-md-4');

            $panel->add("<div class='row g-2 g-md-3' style='margin-top:8px;'>{$cardsReserva}</div>");

            // Datagrid nativa das reservas por categoria
            $gridReservas = new BootstrapDatagridWrapper(new TDataGrid);
            $gridReservas->width = '100%';

            $fmtDinheiro = function($valor) {
                return 'R$ ' . number_format($valor / 100, 2, ',', '.');
            };

            $col_categoria = new TDataGridColumn('nome', 'Categoria', 'center');
            $col_reservado = new TDataGridColumn('reservado', 'Reservado', 'center');
            $col_usado     = new TDataGridColumn('usado', 'Usado', 'center');
            $col_restante  = new TDataGridColumn('restante', 'Restante', 'center');
            $col_consumo   = new TDataGridColumn('pct', 'Consumo', 'center');

            $col_categoria->setTransformer(function($value, $object) {
                $cor  = $object->cor ?? '#999999';
                $nome = htmlspecialchars($value, ENT_QUOTES);
                return "<span style='background-color:{$cor}; color:#fff; padding:3px 10px; border-radius:12px; font-size:0.85em; font-weight:600;'>{$nome}</span>";
            });

            $col_reservado->setTransformer($fmtDinheiro);
            $col_usado->setTransformer($fmtDinheiro);

            $col_restante->setTransformer(function($value) {
                $cor = $value < 0 ? '#e53935' : '#2e7d32';
                return "<span style='color:{$cor}; font-weight:600;'>R$ " . number_format($value / 100, 2, ',', '.') . "</span>";
            });

            $col_consumo->setTransformer(function($pct, $object) {
                $barColor = ($object->restante < 0) ? '#e53935' : '#43a047';
                return "<div style='background:#eee; border-radius:8px; height:10px; width:140px; overflow:hidden;'>
                            <div style='width:{$pct}%; background:{$barColor}; height:10px;'></div>
                        </div>";
            });

            $gridReservas->addColumn($col_categoria);
            $gridReservas->addColumn($col_reservado);
            $gridReservas->addColumn($col_usado);
            $gridReservas->addColumn($col_restante);
            $gridReservas->addColumn($col_consumo);

            $gridReservas->createModel();

            foreach ($reservaItens as $item) {
                $gridReservas->addItem($item);
            }

            $cardGrid = new TElement('div');
            $cardGrid->class = 'card p-3 shadow-sm';
            $cardGrid->style = 'margin-top:15px; overflow-x:auto;';

            $tituloGrid = new TElement('h5');
            $tituloGrid->style = 'margin-bottom:10px;';
            $tituloGrid->add('Reservas por categoria');

            $cardGrid->add($tituloGrid);
            $cardGrid->add($gridReservas);

            $panel->add($cardGrid);
        }

        // Card do gráfico de despesas por categoria
        $chartCard = new TElement('div');
        $chartCard->class = 'card p-3 shadow-sm';
        $chartCard->style = 'margin-top:20px; overflow:hidden; max-width:100%;';

        $chartTitle = new TElement('h5');
        $chartTitle->style = 'margin-bottom:10px;';
        $chartTitle->add('Despesas por categoria');
        $chartCard->add($chartTitle);

        if (count($group) > 0) {
            $chart = new THtmlRenderer('app/resources/dashboard_despesas_pie.html');
            $chart->enableSection('main', [
                'data'   => json_encode($dataPie),
                'colors' => json_encode($pieColors),
                'width'  => '100%',
                'height' => '400px',
                'uniqid' => uniqid()
            ]);
            $chartCard->add($chart);
        } else {
            $empty = new TElement('div');
            $empty->style = 'text-align:center; color:#9e9e9e; padding:40px 0;';
            $empty->add('Sem despesas lançadas neste período.');
            $chartCard->add($empty);
        }

        // Container
        $container = new TVBox;
        $container->style = 'width: 100%; max-width: 100%; box-sizing: border-box; padding: 10px; overflow-x: hidden;';
        $container->add($panel);
        $container->add($chartCard);

        parent::add($this->form);
        parent::add($container);

    }

    /**
     * Monta um card de total (título + valor em R$) dentro de uma coluna do grid.
     *
     * @param string $titulo        Rótulo exibido no topo do card
     * @param int    $valorCentavos Valor em centavos
     * @param string $cor           Cor do valor (nome ou hex)
     * @param string $colClass      Classes de coluna do Bootstrap
     */
    private static function card($titulo, $valorCentavos, $cor, $colClass = 'col-6 col-md-3')
    {
        $valor = number_format($valorCentavos / 100, 2, ',', '.');

        return "
            <div class='{$colClass}'>
                <div class='card p-2 text-center shadow-sm h-100'>
                    <div style='font-size: 0.95rem; color: #666;'>{$titulo}</div>
                    <div style='font-size: 1.2rem; color: {$cor}; font-weight: bold;'>R$ {$valor}</div>
                </div>
            </div>";
    }

    public function onSearch($param)
    {
        //echo var_dump($param);

                    AdiantiCoreApplication::loadPage(
        __CLASS__, // ou 'DashboardReceitaDespesas'
        '', // ou null se quiser só o construtor
        [
            'mes'  => $param['mes'],
            'ano'  => $param['ano']
        ]
    );
    }
}
