<?php

use Adianti\Registry\TSession;

class DashboardReceitaDespesas extends TPage
{
    public function __construct()
    {
        parent::__construct();

        // Calcula períodos do mês atual
        $inicio = (new DateTime('first day of this month'))->setTime(0,0,0);
        $fim    = (new DateTime('last day of this month'))->setTime(23,59,59);

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

        // Dados para pie chart por categoria de despesa
        $dataPie = [];
        $dataPie[] = ['Categoria', 'Total'];
        $repoCat = new TRepository('Despesa');
        $critCat = clone $critD;
        $rows = TTransaction::get(); // assume can raw SQL? fallback manual
        // Vamos agrupar manualmente
        $group = [];
        foreach ($despesas as $d) {
            $cat = new Categoria($d->categoria_id);
            $nome = $cat->nome ?? 'Sem categoria';
            $group[$nome] = ($group[$nome] ?? 0) + $d->valor;
        }
        foreach ($group as $nome => $val) {
            $dataPie[] = [$nome, round($val/100, 2)];
        }

        TTransaction::close();

        // Monta HTML
        $tpl = "
        <div class='d-flex flex-wrap justify-content-between gap-3'>
            <div class='card flex-fill p-3 text-center shadow-sm' style='min-width: 200px;'>
                <h5>Receitas (mês)</h5>
                <div style='font-size: 1.5rem; color: green; font-weight: bold;'>R$ {{receitas}}</div>
            </div>
            <div class='card flex-fill p-3 text-center shadow-sm' style='min-width: 200px;'>
                <h5>Despesas (mês)</h5>
                <div style='font-size: 1.5rem; color: red; font-weight: bold;'>R$ {{despesas}}</div>
            </div>
            <div class='card flex-fill p-3 text-center shadow-sm' style='min-width: 200px;'>
                <h5>Saldo Restante</h5>
                <div style='font-size: 1.5rem; color: blue; font-weight: bold;'>R$ {{saldo}}</div>
            </div>
        </div>
        ";

        // Substitui valores
        $htmlContent = str_replace(
            ['{{receitas}}','{{despesas}}','{{saldo}}'],
            [number_format($totalReceitas/100,2,',','.'), number_format($totalDespesas/100,2,',','.'), number_format($saldo/100,2,',','.')],
            $tpl
        );

        // Renderiza texto estático
        $panel = new TPanelGroup;
        $panel->add($htmlContent);

        // Pie chart
        $chart = new THtmlRenderer('app/resources/google_pie_chart.html');
        $chart->enableSection('main', [
            'data'   => json_encode($dataPie),
            'width'  => '100%',
            'height' => '400px',
            'title'  => 'Despesas por Categoria',
            'uniqid' => uniqid()
        ]);

        // Container
        $container = new TVBox;
        $container->style = 'width: 100%; padding: 10px;';
        $container->add($panel);
        $container->add($chart);

        parent::add($container);
    }
}
