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
        $table = new TTable;

        // meses (1..12) com nomes
        $meses = [
            '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
            '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
            '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
        ];
        $comboMes = new TCombo('mes');
        $comboMes->addItems($meses);
        $comboMes->setSize('150px');
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
        $comboAno->setSize('100px');
        if($ano){
            $comboAno->setValue($ano);
        }


        // botão atualizar
        $btn = new TButton('btn_search');
        $btn->setAction(new TAction([$this, 'onSearch']), 'Atualizar');
        $btn->setImage('fa:search');

        $this->form->setFields([$comboMes, $comboAno, $btn]);

        // layout
        $table->addRowSet(new TLabel('Mês:'), $comboMes);
        $table->addRowSet(new TLabel('Ano:'), $comboAno);
        $table->addRowSet('', $btn);

        $this->form->add($table);

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
        parent::add($this->form);
        
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
