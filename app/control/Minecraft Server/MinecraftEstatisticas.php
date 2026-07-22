<?php

use Adianti\Registry\TSession;
use Adianti\Database\TTransaction;
use Adianti\Database\TRepository;
use Adianti\Database\TCriteria;
use Adianti\Database\TFilter;

/**
 * MinecraftEstatisticas
 *
 * Dashboard com as estatísticas acumuladas (vitalícias) de cada jogador do
 * servidor Minecraft vanilla. Os dados são coletados sob demanda via AWS SSM,
 * lendo os arquivos world/stats/<uuid>.json da instância, e persistidos na base
 * (para continuarem visíveis mesmo com o servidor desligado).
 *
 * @author  Guilherme Muller
 */
class MinecraftEstatisticas extends TPage
{
    const DATABASE = 'bolso';

    // Caminho do servidor Minecraft na instância EC2 (ajuste conforme sua instalação).
    const MC_SERVER_DIR = '/opt/minecraft';
    const MC_WORLD      = 'mundo-migos';

    // Opcional: bucket S3 para a saída do SSM (contorna o limite de ~24 KB do
    // StandardOutputContent). Vazio = lê a saída inline (gzip/base64), suficiente
    // para poucos jogadores. Preencha o nome do bucket para usar o modo S3.
    const MC_STATS_BUCKET = '';
    const MC_STATS_PREFIX = 'minecraft-stats';

    public function __construct()
    {
        parent::__construct();

        [$players, $stats] = $this->loadData();

        $selected = isset($_GET['jogador']) ? (string) $_GET['jogador'] : null;
        if (empty($selected) && !empty($players))
        {
            $selected = $players[0]->uuid;
        }

        $vbox = new TVBox;
        $vbox->style = 'width:100%';
        $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));

        $panel = new TPanelGroup('<i class="fa-solid fa-chart-simple" style="margin-right:8px; color:#5b8731;"></i><b style="font-size:18px;">Estatísticas dos jogadores</b>');
        $panel->addHeaderActionLink('<b>Atualizar estatísticas</b>', new TAction([__CLASS__, 'onCollect'], ['register_state' => 'false']), 'fa:rotate green');

        if (empty($players))
        {
            $vazio = new TElement('div');
            $vazio->style = 'padding:24px; text-align:center; color:#888;';
            $vazio->add('<i class="fa-solid fa-database" style="font-size:32px; display:block; margin-bottom:10px;"></i>');
            $vazio->add('Nenhuma estatística coletada ainda. Ligue o servidor e clique em <b>Atualizar estatísticas</b>.');
            $panel->add($vazio);
        }
        else
        {
            $panel->add($this->buildServerTotals($players, $stats));
            $panel->add($this->buildPlayerSelector($players, $selected));
            $panel->add($this->buildPlayerDetail($selected, $stats, $players));
        }

        $vbox->add($panel);
        parent::add($vbox);
    }

    /**
     * Carrega jogadores e estatísticas do usuário logado para a memória.
     * @return array [players[], stats[uuid][categoria][chave] = valor]
     */
    private function loadData()
    {
        $players = [];
        $stats   = [];

        try
        {
            TTransaction::open(self::DATABASE);

            $uid = TSession::getValue('userid');

            $critJ = new TCriteria();
            $critJ->add(new TFilter('usuario_id', '=', $uid));
            $critJ->setProperty('order', 'nome');
            $players = (new TRepository('MinecraftJogador'))->load($critJ);

            $critS = new TCriteria();
            $critS->add(new TFilter('usuario_id', '=', $uid));
            $rows = (new TRepository('MinecraftEstatistica'))->load($critS);

            foreach ($rows as $r)
            {
                $stats[$r->jogador_uuid][$r->categoria][$r->chave] = (int) $r->valor;
            }

            TTransaction::close();
        }
        catch (Exception $e)
        {
            TTransaction::rollbackAll();
        }

        return [$players, $stats];
    }

    /**
     * Cards com os totais do servidor (somando todos os jogadores).
     */
    private function buildServerTotals($players, $stats)
    {
        $tempoTicks = 0;
        $mined      = 0;
        $killed     = 0;

        foreach ($stats as $cats)
        {
            $tempoTicks += $cats['minecraft:custom']['minecraft:play_time'] ?? 0;
            $mined      += array_sum($cats['minecraft:mined']  ?? []);
            $killed     += array_sum($cats['minecraft:killed'] ?? []);
        }

        $wrap = new TElement('div');
        $wrap->style = 'display:flex; flex-wrap:wrap; gap:16px; margin-bottom:12px;';

        $wrap->add(self::summaryCard('fa-users', '#a069c3', 'Jogadores', number_format(count($players), 0, ',', '.')));
        $wrap->add(self::summaryCard('fa-clock', '#478fca', 'Tempo total jogado', self::formatTicks($tempoTicks)));
        $wrap->add(self::summaryCard('fa-cubes', '#8a6d3b', 'Blocos minerados', number_format($mined, 0, ',', '.')));
        $wrap->add(self::summaryCard('fa-skull', '#dc3545', 'Mobs mortos', number_format($killed, 0, ',', '.')));

        return $wrap;
    }

    /**
     * Combo para escolher o jogador exibido no detalhamento.
     */
    private function buildPlayerSelector($players, $selected)
    {
        $items = [];
        foreach ($players as $p)
        {
            $items[$p->uuid] = $p->nome;
        }

        $combo = new TCombo('jogador');
        $combo->addItems($items);
        $combo->setValue($selected);
        $combo->setSize('260px');
        $combo->setChangeAction(new TAction([__CLASS__, 'onSelecionarJogador']));

        $box = new THBox;
        $box->style = 'margin: 4px 0 14px; gap:10px; align-items:center;';
        $lbl = new TLabel('Jogador:');
        $lbl->setFontColor('#333');
        $box->add($lbl);
        $box->add($combo);

        // o combo precisa pertencer a um TForm (por causa do setValue/changeAction)
        $form = new TForm('form_mc_stats');
        $form->add($box);
        $form->setFields([$combo]);

        return $form;
    }

    /**
     * Detalhamento do jogador selecionado (cards + tabelas de blocos e mobs).
     */
    private function buildPlayerDetail($uuid, $stats, $players)
    {
        $wrap = new TElement('div');

        if (empty($uuid) || empty($stats[$uuid]))
        {
            $wrap->add('<div style="padding:16px; color:#888;">Sem dados para este jogador.</div>');
            return $wrap;
        }

        $cats = $stats[$uuid];

        $tempo     = $cats['minecraft:custom']['minecraft:play_time']   ?? 0;
        $mortes    = $cats['minecraft:custom']['minecraft:deaths']      ?? 0;
        $caminhou  = $cats['minecraft:custom']['minecraft:walk_one_cm'] ?? 0;
        $minedTot  = array_sum($cats['minecraft:mined']  ?? []);
        $killedTot = array_sum($cats['minecraft:killed'] ?? []);

        $cardRow = new TElement('div');
        $cardRow->style = 'display:flex; flex-wrap:wrap; gap:16px; margin:8px 0 4px;';
        $cardRow->add(self::summaryCard('fa-clock',           '#478fca', 'Tempo jogado',     self::formatTicks($tempo)));
        $cardRow->add(self::summaryCard('fa-skull-crossbones','#dc3545', 'Mortes',           number_format($mortes, 0, ',', '.')));
        $cardRow->add(self::summaryCard('fa-skull',           '#6c757d', 'Mobs mortos',      number_format($killedTot, 0, ',', '.')));
        $cardRow->add(self::summaryCard('fa-cubes',           '#8a6d3b', 'Blocos minerados', number_format($minedTot, 0, ',', '.')));
        $cardRow->add(self::summaryCard('fa-person-walking',  '#28a745', 'Distância a pé',   number_format($caminhou / 100000, 1, ',', '.') . ' km'));
        $wrap->add($cardRow);

        $wrap->add($this->buildBreakdown('Minérios e blocos minerados', $cats['minecraft:mined']  ?? [], 20));
        $wrap->add($this->buildBreakdown('Mobs mortos',                 $cats['minecraft:killed'] ?? [], 20));

        return $wrap;
    }

    /**
     * Monta uma tabela (top N, ordem decrescente) para um mapa chave => valor.
     */
    private function buildBreakdown($titulo, $map, $limite)
    {
        arsort($map);
        $map = array_slice($map, 0, $limite, true);

        $datagrid = new BootstrapDatagridWrapper(new TDataGrid);
        $datagrid->width = '100%';

        $c1 = new TDataGridColumn('nome',  'Item',       'left');
        $c2 = new TDataGridColumn('valor', 'Quantidade', 'right', '30%');
        $c2->setTransformer(function($v) {
            return number_format($v, 0, ',', '.');
        });

        $datagrid->addColumn($c1);
        $datagrid->addColumn($c2);
        $datagrid->createModel();

        if (empty($map))
        {
            $o = new stdClass;
            $o->nome  = '<span style="color:#999;">Nenhum registro</span>';
            $o->valor = 0;
            $datagrid->addItem($o);
        }
        else
        {
            foreach ($map as $chave => $valor)
            {
                $o = new stdClass;
                $o->nome  = self::prettify($chave);
                $o->valor = $valor;
                $datagrid->addItem($o);
            }
        }

        $panel = new TPanelGroup($titulo);
        $panel->add($datagrid);
        $panel->getBody()->style = 'overflow-x:auto;';

        return $panel;
    }

    /**
     * Recarrega o dashboard exibindo o jogador escolhido no combo.
     */
    public static function onSelecionarJogador($param)
    {
        AdiantiCoreApplication::loadPage(__CLASS__, null, ['jogador' => $param['jogador'] ?? '']);
    }

    /**
     * Coleta as estatísticas da instância via SSM e persiste na base.
     */
    public static function onCollect($param)
    {
        try
        {
            $resumo = self::collectStats();
            new TMessage('info', "Estatísticas atualizadas: {$resumo['jogadores']} jogador(es) e {$resumo['estatisticas']} métricas.");
        }
        catch (Exception $e)
        {
            TTransaction::rollbackAll();
            new TMessage('error', $e->getMessage());
        }

        AdiantiCoreApplication::loadPage(__CLASS__);
    }

    /**
     * Executa a coleta remota via SSM e grava o snapshot.
     * @return array ['jogadores' => n, 'estatisticas' => n]
     */
    private static function collectStats()
    {
        set_time_limit(90);

        // 1. a instância precisa estar ligada para ler os arquivos
        $state = self::getInstanceState();
        if ($state !== 'running')
        {
            throw new Exception('O servidor precisa estar LIGADO para coletar as estatísticas (estado atual: ' . $state . ').');
        }

        // 2. comando remoto: despeja cada stats/<uuid>.json e o usercache.json
        $statsDir  = self::MC_SERVER_DIR . '/' . self::MC_WORLD . '/stats';
        $usercache = self::MC_SERVER_DIR . '/usercache.json';

        // A saída é comprimida (gzip) e codificada em base64 para caber no limite
        // de ~24 KB do StandardOutputContent do SSM; o PHP descomprime depois.
        $remote = 'D=' . escapeshellarg($statsDir) . '; C=' . escapeshellarg($usercache) . '; '
                . '{ for f in "$D"/*.json; do [ -e "$f" ] || continue; echo "===UUID:$(basename "$f" .json)==="; cat "$f"; echo; done; '
                . 'echo "===USERCACHE==="; if [ -e "$C" ]; then cat "$C"; else echo "[]"; fi; } | gzip -c | base64 -w0';

        // 3. dispara o comando via SSM (opcionalmente com saída no S3, sem limite)
        $s3flags = '';
        if (self::MC_STATS_BUCKET !== '')
        {
            $s3flags = ' --output-s3-bucket-name ' . escapeshellarg(self::MC_STATS_BUCKET)
                     . ' --output-s3-key-prefix ' . escapeshellarg(self::MC_STATS_PREFIX);
        }

        $send = 'aws ssm send-command'
              . ' --instance-ids ' . escapeshellarg(MinecraftServer::INSTANCE_ID)
              . ' --document-name "AWS-RunShellScript"'
              . ' --parameters ' . escapeshellarg(json_encode(['commands' => [$remote]]))
              . $s3flags
              . ' --profile ' . escapeshellarg(MinecraftServer::AWS_PROFILE)
              . ' --query "Command.CommandId" --output text 2>&1';

        $commandId = trim((string) shell_exec($send));

        if (!preg_match('/^[0-9a-fA-F\-]{36}$/', $commandId))
        {
            throw new Exception('Falha ao enviar comando SSM: ' . $commandId);
        }

        // 4. aguarda a conclusão
        $status = '';
        $stdout = '';
        $stderr = '';

        for ($i = 0; $i < 15; $i++)
        {
            usleep(1500000); // 1,5s

            $inv = shell_exec('aws ssm get-command-invocation'
                 . ' --command-id ' . escapeshellarg($commandId)
                 . ' --instance-id ' . escapeshellarg(MinecraftServer::INSTANCE_ID)
                 . ' --profile ' . escapeshellarg(MinecraftServer::AWS_PROFILE)
                 . ' --query "[Status,StandardOutputContent,StandardErrorContent]" --output json 2>&1');

            $r = json_decode($inv, true);
            if (is_array($r))
            {
                $status = $r[0] ?? '';
                $stdout = $r[1] ?? '';
                $stderr = $r[2] ?? '';

                if (in_array($status, ['Success', 'Failed', 'Cancelled', 'TimedOut'], true))
                {
                    break;
                }
            }
        }

        if ($status !== 'Success')
        {
            throw new Exception('SSM retornou status "' . $status . '". ' . trim((string) $stderr));
        }

        // no modo S3, a saída completa está no bucket (StandardOutputContent vem cortado)
        if (self::MC_STATS_BUCKET !== '')
        {
            $stdout = self::readS3Output($commandId);
        }

        return self::parseAndStore($stdout);
    }

    /**
     * Baixa a saída completa do comando gravada no S3 (modo sem limite de 24 KB).
     */
    private static function readS3Output($commandId)
    {
        $prefix = trim(self::MC_STATS_PREFIX, '/') . '/' . $commandId . '/' . MinecraftServer::INSTANCE_ID . '/';

        // localiza a chave que termina em /stdout
        $ls = shell_exec('aws s3 ls '
            . escapeshellarg('s3://' . self::MC_STATS_BUCKET . '/' . $prefix) . ' --recursive'
            . ' --profile ' . escapeshellarg(MinecraftServer::AWS_PROFILE) . ' 2>&1');

        $key = null;
        foreach (preg_split('/\r?\n/', (string) $ls) as $line)
        {
            if (preg_match('#(\S*/stdout)\s*$#', $line, $m))
            {
                $key = $m[1];
                break;
            }
        }

        if (empty($key))
        {
            throw new Exception('Saída do comando não encontrada no S3 (bucket/permissão?).');
        }

        $content = shell_exec('aws s3 cp '
            . escapeshellarg('s3://' . self::MC_STATS_BUCKET . '/' . $key) . ' -'
            . ' --profile ' . escapeshellarg(MinecraftServer::AWS_PROFILE) . ' 2>/dev/null');

        return (string) $content;
    }

    /**
     * Consulta o estado atual da instância (running, stopped, ...).
     */
    private static function getInstanceState()
    {
        $cmd = 'aws ec2 describe-instances'
             . ' --instance-ids ' . escapeshellarg(MinecraftServer::INSTANCE_ID)
             . ' --profile ' . escapeshellarg(MinecraftServer::AWS_PROFILE)
             . ' --query "Reservations[0].Instances[0].State.Name" --output text 2>&1';

        return trim((string) shell_exec($cmd));
    }

    /**
     * Faz o parse da saída remota e grava o snapshot (substitui o anterior).
     */
    private static function parseAndStore($stdout)
    {
        // a saída vem como base64(gzip(conteúdo)); descomprime. Se não for o caso
        // (ex.: base64/gzip indisponível), mantém o texto original como fallback.
        $raw = base64_decode(trim($stdout), true);
        if ($raw !== false)
        {
            $plain = @gzdecode($raw);
            if ($plain !== false)
            {
                $stdout = $plain;
            }
        }

        $parts     = explode('===USERCACHE===', $stdout, 2);
        $statsPart = $parts[0] ?? '';
        $cachePart = $parts[1] ?? '[]';

        // mapa uuid => nome a partir do usercache
        $nameByUuid = [];
        $usercache  = json_decode(trim($cachePart), true);
        if (is_array($usercache))
        {
            foreach ($usercache as $u)
            {
                if (isset($u['uuid'], $u['name']))
                {
                    $nameByUuid[strtolower($u['uuid'])] = $u['name'];
                }
            }
        }

        $chunks = preg_split('/===UUID:([0-9a-fA-F\-]{36})===/', $statsPart, -1, PREG_SPLIT_DELIM_CAPTURE);

        $uid       = TSession::getValue('userid');
        $agora     = date('Y-m-d H:i:s');
        $countJog  = 0;
        $countStat = 0;

        TTransaction::open(self::DATABASE);

        // limpa o snapshot anterior do usuário
        $crit = new TCriteria();
        $crit->add(new TFilter('usuario_id', '=', $uid));
        (new TRepository('MinecraftEstatistica'))->delete($crit);
        (new TRepository('MinecraftJogador'))->delete($crit);

        for ($i = 1; $i < count($chunks); $i += 2)
        {
            $uuid = strtolower(trim($chunks[$i]));
            $json = json_decode(trim($chunks[$i + 1] ?? ''), true);

            if (!is_array($json) || empty($json['stats']))
            {
                continue;
            }

            $jog = new MinecraftJogador;
            $jog->uuid          = $uuid;
            $jog->nome          = $nameByUuid[$uuid] ?? substr($uuid, 0, 8);
            $jog->usuario_id    = $uid;
            $jog->atualizado_em = $agora;
            $jog->store();
            $countJog++;

            foreach ($json['stats'] as $categoria => $chaves)
            {
                if (!is_array($chaves))
                {
                    continue;
                }

                foreach ($chaves as $chave => $valor)
                {
                    $st = new MinecraftEstatistica;
                    $st->jogador_uuid  = $uuid;
                    $st->categoria     = $categoria;
                    $st->chave         = $chave;
                    $st->valor         = (int) $valor;
                    $st->usuario_id    = $uid;
                    $st->atualizado_em = $agora;
                    $st->store();
                    $countStat++;
                }
            }
        }

        TTransaction::close();

        return ['jogadores' => $countJog, 'estatisticas' => $countStat];
    }

    /**
     * Torna uma chave como "minecraft:diamond_ore" legível ("Diamond Ore").
     */
    private static function prettify($chave)
    {
        $k = preg_replace('/^minecraft:/', '', (string) $chave);
        $k = str_replace('_', ' ', $k);
        return ucwords($k);
    }

    /**
     * Formata ticks de jogo (20 ticks/seg) como "Xh Ymin".
     */
    private static function formatTicks($ticks)
    {
        $segundos = (int) ($ticks / 20);
        $h = intdiv($segundos, 3600);
        $m = intdiv($segundos % 3600, 60);

        if ($h > 0)
        {
            return sprintf('%dh %02dmin', $h, $m);
        }
        return sprintf('%dmin', $m);
    }

    /**
     * Renderiza um card de resumo (ícone + rótulo + valor).
     */
    private static function summaryCard($icon, $color, $label, $value)
    {
        $card = new TElement('div');
        $card->style = 'flex:1; min-width:200px; padding:18px 20px; border-radius:8px; background:#f8f9fa; border:1px solid #e3e6ea; display:flex; align-items:center; gap:16px;';

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
}
