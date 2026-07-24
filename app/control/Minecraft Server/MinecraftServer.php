<?php

use Adianti\Registry\TSession;
use Adianti\Database\TTransaction;
use Adianti\Database\TRepository;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;
use Adianti\Widget\Form\TDate;

/**
 * MinecraftServer
 *
 * Controla a instância EC2 do servidor de Minecraft e lista o histórico de sessões.
 *
 * @version    8.1
 * @package    control
 * @subpackage admin
 * @author     Guilherme Muller
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-template
 */
class MinecraftServer extends TPage
{
    use Adianti\Base\AdiantiStandardListTrait;

    const INSTANCE_ID = 'i-0a57e67d17372c828';
    const AWS_PROFILE = 'minha-conta-pessoal';
    const DATABASE    = 'bolso';

    // Tarifa padrão da instância EC2, em US$ por hora (usada se não houver cadastro).
    const COST_PER_HOUR = 0.10;

    protected $form;
    protected $datagrid;
    protected $controlForm;
    protected $pageNavigation;
    protected $totalSegundos = 0;
    protected $totalCusto    = 0.0;

    public function __construct()
    {
        parent::__construct();

        $this->setDatabase(self::DATABASE);
        $this->setActiveRecord('MinecraftSessao');

        // datagrid geral: exibe as sessões de todos os usuários
        $criteria = new TCriteria();
        $this->setCriteria($criteria);

        $this->setDefaultOrder('hora_inicio', 'desc');
        $this->setLimit(20);

        // filtros do cabeçalho da datagrid: intervalo de datas (de/até) sobre a data de início
        $this->addFilterField('hora_inicio', '>=', 'f_inicio', function($value) {
            return $value . ' 00:00:00';
        });
        $this->addFilterField('hora_inicio', '<=', 'f_fim', function($value) {
            return $value . ' 23:59:59';
        });

        // filtros de período (mês/ano) baseados na data de início
        $this->addFilterField('MONTH(hora_inicio)', '=', 'filtro_mes');
        $this->addFilterField('YEAR(hora_inicio)',  '=', 'filtro_ano');

        // por padrão, mostra apenas o mês atual
        $filtros = TSession::getValue(__CLASS__.'_filter_data');
        if (empty($filtros->filtro_mes) AND empty($filtros->filtro_ano))
        {
            $criteria->add(new TFilter('MONTH(hora_inicio)', '=', date('n')));
            $criteria->add(new TFilter('YEAR(hora_inicio)',  '=', date('Y')));
            $this->setCriteria($criteria);
        }

        // calcula duração e custo (proporcional a segundos/minutos) para cada linha
        $this->setTransformer(function($objects) {
            $rate = self::getConfiguredRate();
            $nomes = [];

            foreach ((array) $objects as $sessao) {
                $inicioTs = strtotime($sessao->hora_inicio);

                // nome do usuário dono da sessão (com cache para evitar buscas repetidas)
                $uid = $sessao->usuario_id;
                if ($uid && !isset($nomes[$uid])) {
                    $user = SystemUser::find($uid);
                    $nomes[$uid] = $user ? $user->name : '—';
                }
                $sessao->usuario_nome = $uid ? $nomes[$uid] : '—';

                if (!empty($sessao->hora_fim)) {
                    $segundos = (int) $sessao->duracao_segundos;
                    $custo    = (float) $sessao->custo_estimado;
                } else {
                    // sessão ainda aberta: estima até o horário atual
                    $segundos = max(0, time() - $inicioTs);
                    $custo    = ($segundos / 3600) * $rate;
                }

                $sessao->tempo_fmt = self::formatDuration($segundos);
                $sessao->custo_fmt = 'US$ ' . number_format($custo, 4, '.', ',');

                // acumula para os totalizadores no fim da datagrid
                $this->totalSegundos += $segundos;
                $this->totalCusto    += $custo;
            }
        });

        // linha de totais no rodapé da datagrid
        $this->setAfterLoadCallback(function($datagrid) {
            $tfoot = new TElement('tfoot');
            $tfoot->{'class'} = 'tdatagrid_footer';

            $row = new TElement('tr');
            $row->{'style'} = 'font-weight:bold; background:#f8f9fa;';
            $tfoot->add($row);
            $datagrid->add($tfoot);

            $tdLabel = new TElement('td');
            $tdLabel->{'colspan'} = 3;
            $tdLabel->{'style'}   = 'text-align:right;';
            $tdLabel->add('Total');

            $tdTempo = new TElement('td');
            $tdTempo->{'style'} = 'text-align:center;';
            $tdTempo->add(self::formatDuration($this->totalSegundos));

            $tdCusto = new TElement('td');
            $tdCusto->{'style'} = 'text-align:right;';
            $tdCusto->add('US$ ' . number_format($this->totalCusto, 4, '.', ','));

            $row->add($tdLabel);
            $row->add($tdTempo);
            $row->add($tdCusto);
        });

        $this->buildControlForm();
        $this->buildDatagrid();

        $this->pageNavigation = new TPageNavigation();
        $this->pageNavigation->setAction(new TAction([$this, 'onReload']));
        $this->pageNavigation->enableCounters();

        $panel = new TPanelGroup('<i class="fa-solid fa-clock-rotate-left" style="margin-right:8px;"></i><b style="font-size:18px;">Histórico de sessões</b>');
        $panel->addHeaderActionLink('<b>Configurar instância</b>', new TAction(['MinecraftInstanciaForm', 'onEdit'], ['register_state' => 'false']), 'fa:server green');
        $panel->add($this->form);
        $panel->addFooter($this->pageNavigation);
        $panel->getBody()->style = 'overflow-x:auto;';

        $vbox = new TVBox;
        $vbox->style = 'width:100%';
        $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $vbox->add($this->controlForm);
        $vbox->add($panel);

        parent::add($vbox);
    }

    /**
     * Monta o card de controle da instância EC2 (status, IP e botões ligar/desligar).
     */
    private function buildControlForm()
    {
        $this->controlForm = new BootstrapFormBuilder('form_ec2_start');
        $this->controlForm->setFormTitle('<i class="fa-solid fa-cube" style="color:#5b8731"></i>&nbsp; Servidor Minecraft');

        $status = self::getInstanceStatus(self::INSTANCE_ID);

        // check de status: se a instância está desligada, não pode haver sessão "Em andamento"
        self::reconcileSessions($status);

        $badge  = self::getStatusBadge($status['state']);

        $card = new TElement('div');
        $card->style = 'padding:20px; border-radius:8px; background:#f8f9fa; border:1px solid #e3e6ea; margin-bottom:16px;';

        $row = new TElement('div');
        $row->style = 'display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;';

        $statusBlock = new TElement('div');
        $statusBlock->add('<div style="font-size:12px; color:#888; text-transform:uppercase; letter-spacing:.5px;">Status</div>');
        $statusBlock->add($badge);

        $ipBlock = new TElement('div');
        $ipBlock->style = 'text-align:right;';
        $ipBlock->add('<div style="font-size:12px; color:#888; text-transform:uppercase; letter-spacing:.5px;">IP público</div>');
        $ipBlock->add('<div style="font-size:22px; font-weight:600; font-family:monospace;">' . htmlspecialchars($status['ip'] ?: '—') . '</div>');

        $row->add($statusBlock);
        $row->add($ipBlock);
        $card->add($row);

        $this->controlForm->addContent([$card]);

        $this->controlForm->addAction('Atualizar status', new TAction([__CLASS__, 'onRefreshStatus']), 'fa:sync blue', 'btn_reload_ec2');

        if ($status['state'] === 'stopped')
        {
            $this->controlForm->addAction('Ligar', new TAction([__CLASS__, 'onStart']), 'fa:play green');
        }

        if ($status['state'] === 'running')
        {
            $this->controlForm->addAction('Desligar', new TAction([__CLASS__, 'onStop']), 'fa:power-off red');
        }

        if (in_array($status['state'], ['pending', 'stopping']))
        {
            TScript::create("setTimeout(function(){ document.getElementById('btn_reload_ec2').click(); }, 5000);");
        }
    }

    /**
     * Monta a datagrid (estilo Adianti) com a linha de filtros no cabeçalho.
     */
    private function buildDatagrid()
    {
        $this->datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $this->datagrid->width = '100%';

        $col_usuario = new TDataGridColumn('usuario_nome', 'Usuário',        'left',   '20%');
        $col_inicio  = new TDataGridColumn('hora_inicio',  'Início',         'center', '20%');
        $col_fim     = new TDataGridColumn('hora_fim',     'Fim',            'center', '20%');
        $col_tempo   = new TDataGridColumn('tempo_fmt',    'Tempo',          'center', '20%');
        $col_custo   = new TDataGridColumn('custo_fmt',    'Custo estimado', 'right',  '20%');

        $formatDate = function($value) {
            if (empty($value)) {
                return "<span style='color:#fd7e14; font-weight:600;'>Em andamento</span>";
            }
            $ts = strtotime($value);
            return $ts ? date('d/m/Y H:i', $ts) : htmlspecialchars($value);
        };

        $col_inicio->setTransformer($formatDate);
        $col_fim->setTransformer($formatDate);

        $col_inicio->setAction(new TAction([$this, 'onReload']), ['order' => 'hora_inicio']);
        $col_fim->setAction(new TAction([$this, 'onReload']),    ['order' => 'hora_fim']);

        $this->datagrid->addColumn($col_usuario);
        $this->datagrid->addColumn($col_inicio);
        $this->datagrid->addColumn($col_fim);
        $this->datagrid->addColumn($col_tempo);
        $this->datagrid->addColumn($col_custo);

        $this->datagrid->createModel();

        // formulário que envolve a datagrid (necessário para os filtros)
        $this->form = new TForm('form_search_minecraft_sessao');
        $this->form->setProperty('onkeydown', 'if(event.keyCode == 13) { event.preventDefault(); return false; }');

        // combos de período (mês/ano)
        $filtro_mes = new TCombo('filtro_mes');
        $filtro_mes->setSize('100%');
        $filtro_mes->addItems([
            '1'=>'Jan', '2'=>'Fev', '3'=>'Mar', '4'=>'Abr',
            '5'=>'Mai', '6'=>'Jun', '7'=>'Jul', '8'=>'Ago',
            '9'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'
        ]);
        $filtro_mes->setChangeAction(new TAction([$this, 'onSearch'], ['static' => '1']));

        $filtro_ano = new TCombo('filtro_ano');
        $filtro_ano->setSize('100%');
        $anos = [];
        for ($i = date('Y')+1; $i >= date('Y')-5; $i--) {
            $anos[$i] = $i;
        }
        $filtro_ano->addItems($anos);
        $filtro_ano->setChangeAction(new TAction([$this, 'onSearch'], ['static' => '1']));

        // cards de resumo do período selecionado
        $this->form->add($this->buildSummaryCards());

        // linha com os filtros de período
        $box_periodo = new THBox;
        $box_periodo->style = 'margin: 6px 0 12px; gap: 10px; align-items: end;';
        $lblMes = new TLabel('Mês:');   $lblMes->setFontColor('#333');
        $lblAno = new TLabel('Ano:');   $lblAno->setFontColor('#333');
        $box_periodo->add($lblMes);
        $box_periodo->add($filtro_mes);
        $box_periodo->add($lblAno);
        $box_periodo->add($filtro_ano);
        $this->form->add($box_periodo);

        $this->form->add($this->datagrid);

        // campos de filtro do cabeçalho (por data exata)
        $f_inicio = new TDate('f_inicio');
        $f_fim    = new TDate('f_fim');

        $f_inicio->setProperty('title', 'Sessões a partir desta data');
        $f_fim->setProperty('title', 'Sessões até esta data');

        foreach ([$f_inicio, $f_fim] as $filtro) {
            $filtro->setMask('dd/mm/yyyy');
            $filtro->setDatabaseMask('yyyy-mm-dd');
            $filtro->setSize('100%');
            $filtro->tabindex = -1;
            $filtro->setExitAction(new TAction([$this, 'onSearch'], ['static' => '1']));
            $filtro->setChangeAction(new TAction([$this, 'onSearch'], ['static' => '1']));
        }

        // linha de filtros dentro do cabeçalho da datagrid
        $tr = new TElement('tr');
        $this->datagrid->prependRow($tr);

        $tr->add(TElement::tag('td', ''));
        $tr->add(TElement::tag('td', $f_inicio));
        $tr->add(TElement::tag('td', $f_fim));
        $tr->add(TElement::tag('td', ''));
        $tr->add(TElement::tag('td', ''));

        $this->form->addField($filtro_mes);
        $this->form->addField($filtro_ano);
        $this->form->addField($f_inicio);
        $this->form->addField($f_fim);

        // preenche os filtros; se for a carga inicial, seleciona o mês/ano atuais
        $filtros = TSession::getValue(__CLASS__.'_filter_data');
        if (empty($filtros)) {
            $filtros = new stdClass;
            $filtros->filtro_mes = date('n');
            $filtros->filtro_ano = date('Y');
        }
        $this->form->setData($filtros);
    }

    /**
     * Monta os cards de resumo (tempo e custo) do período (mês/ano) selecionado.
     */
    private function buildSummaryCards()
    {
        [$mes, $ano] = $this->getSelectedPeriod();

        $totalSegundos = 0;
        $totalCusto    = 0.0;

        try
        {
            TTransaction::open(self::DATABASE);

            $rate = self::getConfiguredRate();

            // resumo geral: soma o tempo e custo de todos os usuários no período
            $criteria = new TCriteria();
            $criteria->add(new TFilter('MONTH(hora_inicio)', '=', $mes));
            $criteria->add(new TFilter('YEAR(hora_inicio)',  '=', $ano));

            $repo    = new TRepository('MinecraftSessao');
            $sessoes = $repo->load($criteria);

            foreach ($sessoes as $sessao)
            {
                if (!empty($sessao->hora_fim))
                {
                    $totalSegundos += (int) $sessao->duracao_segundos;
                    $totalCusto    += (float) $sessao->custo_estimado;
                }
                else
                {
                    $seg = max(0, time() - strtotime($sessao->hora_inicio));
                    $totalSegundos += $seg;
                    $totalCusto    += ($seg / 3600) * $rate;
                }
            }

            TTransaction::close();
        }
        catch (Exception $e)
        {
            TTransaction::rollbackAll();
        }

        $periodo = self::monthName($mes) . ' / ' . $ano;

        $wrap = new TElement('div');
        $wrap->style = 'display:flex; flex-wrap:wrap; gap:16px; margin-bottom:8px;';

        $wrap->add(self::summaryCard(
            'fa-clock', '#478fca', 'Tempo de uso — ' . $periodo, self::formatDuration($totalSegundos)
        ));
        $wrap->add(self::summaryCard(
            'fa-dollar-sign', '#28a745', 'Custo estimado — ' . $periodo, 'US$ ' . number_format($totalCusto, 4, '.', ',')
        ));

        return $wrap;
    }

    /**
     * Renderiza um card de resumo individual.
     */
    private static function summaryCard($icon, $color, $label, $value)
    {
        $card = new TElement('div');
        $card->style = 'flex:1; min-width:220px; padding:18px 20px; border-radius:8px; background:#f8f9fa; border:1px solid #e3e6ea; display:flex; align-items:center; gap:16px;';

        $iconBox = new TElement('div');
        $iconBox->style = "width:48px; height:48px; border-radius:8px; display:flex; align-items:center; justify-content:center; background:{$color}1a; color:{$color}; font-size:22px; flex-shrink:0;";
        $iconBox->add("<i class=\"fa-solid {$icon}\"></i>");

        $textBox = new TElement('div');
        $textBox->add('<div style="font-size:12px; color:#888; text-transform:uppercase; letter-spacing:.5px;">' . htmlspecialchars($label) . '</div>');
        $textBox->add('<div style="font-size:24px; font-weight:700; color:#333;">' . htmlspecialchars($value) . '</div>');

        $card->add($iconBox);
        $card->add($textBox);

        return $card;
    }

    /**
     * Retorna [mes, ano] selecionados nos filtros, ou o mês/ano atuais por padrão.
     */
    private function getSelectedPeriod()
    {
        $filtros = TSession::getValue(__CLASS__.'_filter_data');
        $mes = !empty($filtros->filtro_mes) ? (int) $filtros->filtro_mes : (int) date('n');
        $ano = !empty($filtros->filtro_ano) ? (int) $filtros->filtro_ano : (int) date('Y');

        return [$mes, $ano];
    }

    /**
     * Nome do mês em português.
     */
    private static function monthName($mes)
    {
        $nomes = [
            1=>'Janeiro', 2=>'Fevereiro', 3=>'Março',     4=>'Abril',
            5=>'Maio',    6=>'Junho',     7=>'Julho',     8=>'Agosto',
            9=>'Setembro',10=>'Outubro',  11=>'Novembro', 12=>'Dezembro'
        ];

        return $nomes[(int) $mes] ?? (string) $mes;
    }

    /**
     * Retorna a tarifa (US$/hora) configurada pelo usuário, ou a constante padrão.
     * Deve ser chamado dentro de uma transação aberta.
     */
    private static function getConfiguredRate()
    {
        $criteria = new TCriteria();
        $criteria->add(new TFilter('usuario_id', '=', TSession::getValue('userid')));
        $criteria->setProperty('order', 'id');
        $criteria->setProperty('direction', 'desc');

        $repo    = new TRepository('MinecraftInstancia');
        $objects = $repo->load($criteria);

        if (!empty($objects) && $objects[0]->custo_hora !== null)
        {
            return (float) $objects[0]->custo_hora;
        }

        return self::COST_PER_HOUR;
    }

    /**
     * Formata uma duração em segundos como "Xh Ymin", "Ymin Zs" ou "Zs".
     */
    private static function formatDuration($segundos)
    {
        $segundos = max(0, (int) $segundos);
        $h = intdiv($segundos, 3600);
        $m = intdiv($segundos % 3600, 60);
        $s = $segundos % 60;

        if ($h > 0)
        {
            return sprintf('%dh %02dmin', $h, $m);
        }
        if ($m > 0)
        {
            return sprintf('%dmin %02ds', $m, $s);
        }
        return sprintf('%ds', $s);
    }

    private static function getStatusBadge($state)
    {
        $map = [
            'running'  => ['#28a745', 'fa-circle-check', 'Online'],
            'stopped'  => ['#6c757d', 'fa-circle-stop',  'Offline'],
            'pending'  => ['#fd7e14', 'fa-circle-notch fa-spin', 'Iniciando'],
            'stopping' => ['#fd7e14', 'fa-circle-notch fa-spin', 'Desligando'],
        ];

        [$color, $icon, $label] = $map[$state] ?? ['#dc3545', 'fa-triangle-exclamation', $state];

        return sprintf(
            '<div style="display:inline-flex; align-items:center; gap:8px; font-size:22px; font-weight:600; color:%s;"><i class="fa-solid %s"></i> %s</div>',
            $color,
            $icon,
            htmlspecialchars($label)
        );
    }

    private static function getInstanceStatus($instance_id)
    {
        $command = sprintf(
            'aws ec2 describe-instances --instance-ids %s --profile %s --query "Reservations[0].Instances[0].[State.Name,PublicIpAddress,StateTransitionReason,LaunchTime]" --output json 2>&1',
            escapeshellarg($instance_id),
            escapeshellarg(self::AWS_PROFILE)
        );

        $output = shell_exec($command);
        $data   = json_decode($output, true);

        if (!is_array($data))
        {
            return ['state' => 'Erro: ' . trim((string) $output), 'ip' => null, 'reason' => null, 'launch' => null];
        }

        return [
            'state'  => $data[0] ?? 'Desconhecido',
            'ip'     => $data[1] ?? null,
            'reason' => $data[2] ?? null,
            'launch' => $data[3] ?? null,
        ];
    }

    /**
     * Fecha todas as sessões abertas da instância, gravando fim, duração e custo.
     *
     * @param int|null $fimTsPreferido Horário de término (timestamp). Se ausente ou
     *                                 anterior ao início, usa o horário atual.
     * @param int      $minIdleSeconds Só fecha sessões iniciadas há mais de N segundos
     *                                 (evita fechar sessão recém-aberta por corrida na AWS).
     */
    private static function closeOpenSessions($fimTsPreferido = null, $minIdleSeconds = 0)
    {
        try
        {
            TTransaction::open(self::DATABASE);

            $criteria = new TCriteria();
            $criteria->add(new TFilter('usuario_id',  '=', TSession::getValue('userid')));
            $criteria->add(new TFilter('instance_id', '=', self::INSTANCE_ID));
            $criteria->add(new TFilter('hora_fim', 'IS', NULL));
            $criteria->setProperty('order', 'hora_inicio');
            $criteria->setProperty('direction', 'desc');

            $repo    = new TRepository('MinecraftSessao');
            $sessoes = $repo->load($criteria);

            foreach ($sessoes as $sessao)
            {
                $inicioTs = strtotime($sessao->hora_inicio);

                // ignora sessões muito recentes (proteção contra corrida de status)
                if ($minIdleSeconds > 0 && (time() - $inicioTs) < $minIdleSeconds)
                {
                    continue;
                }

                $fimTs    = ($fimTsPreferido && $fimTsPreferido >= $inicioTs) ? $fimTsPreferido : time();
                $segundos = max(0, $fimTs - $inicioTs);
                $tarifa   = ($sessao->custo_hora !== null) ? (float) $sessao->custo_hora : self::COST_PER_HOUR;

                $sessao->hora_fim         = date('Y-m-d H:i:s', $fimTs);
                $sessao->duracao_segundos = $segundos;
                // custo proporcional ao tempo ligado (segundos), não só por hora cheia
                $sessao->custo_estimado   = round(($segundos / 3600) * $tarifa, 4);
                $sessao->store();
            }

            TTransaction::close();
        }
        catch (Exception $e)
        {
            TTransaction::rollbackAll();
        }
    }

    /**
     * Reconcilia as sessões com o estado real da instância:
     *  - desligada  => fecha sessões que ficaram "Em andamento";
     *  - ligada     => se não houver sessão aberta (ex.: ligada fora do app),
     *                  abre uma usando o LaunchTime real da AWS.
     */
    private static function reconcileSessions($status)
    {
        $state = $status['state'] ?? '';

        $downStates = ['stopped', 'stopping', 'terminated', 'shutting-down'];
        $upStates   = ['running', 'pending'];

        if (in_array($state, $downStates, true))
        {
            // fecha órfãs iniciadas há mais de 2 min (evita corrida logo após "Ligar")
            self::closeOpenSessions(self::resolveStopTimestamp($status), 120);
        }
        elseif (in_array($state, $upStates, true))
        {
            // instância ligada: garante que exista uma sessão aberta
            self::openSessionFromLaunch($status);
        }
        // status desconhecido/erro: não mexe nas sessões
    }

    /**
     * Abre uma sessão para uma instância que já está ligada e não possui sessão
     * aberta (ex.: ligada pelo console da AWS), usando o LaunchTime como início.
     */
    private static function openSessionFromLaunch($status)
    {
        try
        {
            TTransaction::open(self::DATABASE);

            $criteria = new TCriteria();
            $criteria->add(new TFilter('usuario_id',  '=', TSession::getValue('userid')));
            $criteria->add(new TFilter('instance_id', '=', self::INSTANCE_ID));
            $criteria->add(new TFilter('hora_fim', 'IS', NULL));

            $repo = new TRepository('MinecraftSessao');

            if ($repo->count($criteria) == 0)
            {
                $launchTs = self::resolveLaunchTimestamp($status);

                $sessao = new MinecraftSessao;
                $sessao->instance_id = self::INSTANCE_ID;
                $sessao->hora_inicio = date('Y-m-d H:i:s', $launchTs ?: time());
                $sessao->custo_hora  = self::getConfiguredRate();
                $sessao->usuario_id  = TSession::getValue('userid');
                $sessao->store();
            }

            TTransaction::close();
        }
        catch (Exception $e)
        {
            TTransaction::rollbackAll();
        }
    }

    /**
     * Extrai o horário de parada (timestamp local) do StateTransitionReason da AWS.
     * Ex.: "User initiated (2026-07-22 15:30:00 GMT)". Retorna null se não encontrar.
     */
    private static function resolveStopTimestamp($status)
    {
        $reason = $status['reason'] ?? '';

        if (preg_match('/\((\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) GMT\)/', (string) $reason, $m))
        {
            try
            {
                $dt = new DateTime($m[1], new DateTimeZone('UTC'));
                $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
                return $dt->getTimestamp();
            }
            catch (Exception $e)
            {
                return null;
            }
        }

        return null;
    }

    /**
     * Converte o LaunchTime da AWS (ISO 8601 em UTC, ex.: "2026-07-22T15:30:00+00:00")
     * para timestamp no fuso local. Retorna null se ausente/inválido.
     */
    private static function resolveLaunchTimestamp($status)
    {
        $launch = $status['launch'] ?? '';

        if (empty($launch))
        {
            return null;
        }

        try
        {
            $dt = new DateTime((string) $launch);
            $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
            return $dt->getTimestamp();
        }
        catch (Exception $e)
        {
            return null;
        }
    }

    public static function onStart($param)
    {
        try
        {
            $command = sprintf(
                'aws ec2 start-instances --instance-ids %s --profile %s 2>&1',
                escapeshellarg(self::INSTANCE_ID),
                escapeshellarg(self::AWS_PROFILE)
            );
            $output  = shell_exec($command);

            $data = json_decode($output, true);

            if (!is_array($data))
            {
                throw new Exception($output);
            }

            // A sessão é aberta automaticamente pelo reconcileSessions() no próximo
            // carregamento, usando o LaunchTime real da instância como início.
            new TMessage('info', 'Comando de início enviado. O servidor pode levar alguns minutos para ficar disponível.');
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
        }

        AdiantiCoreApplication::loadPage(__CLASS__);
    }

    public static function onStop($param)
    {
        try
        {
            $command = sprintf(
                'aws ec2 stop-instances --instance-ids %s --profile %s 2>&1',
                escapeshellarg(self::INSTANCE_ID),
                escapeshellarg(self::AWS_PROFILE)
            );
            $output = shell_exec($command);

            $data = json_decode($output, true);

            if (!is_array($data))
            {
                throw new Exception($output);
            }

            self::closeOpenSessions();

            new TMessage('info', 'Comando de desligamento enviado.');
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
        }

        AdiantiCoreApplication::loadPage(__CLASS__);
    }

    /**
     * Recarrega a página inteira (reconsulta o status da EC2 na AWS).
     */
    public static function onRefreshStatus($param)
    {
        AdiantiCoreApplication::loadPage(__CLASS__);
    }
}
?>
