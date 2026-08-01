<?php

use Adianti\Registry\TSession;
use Adianti\Control\TPage;
use Adianti\Control\TAction;
use Adianti\Widget\Base\TElement;
use Adianti\Widget\Base\TScript;
use Adianti\Wrapper\BootstrapFormBuilder;

/**
 * SistemaAtualizar
 *
 * Atualiza o código da aplicação executando `git pull origin main` na raiz do
 * projeto (constante PATH), sem necessidade de acessar a VM manualmente.
 *
 * @version    8.1
 * @package    control
 * @subpackage admin
 * @author     Guilherme Muller
 */
class SistemaAtualizar extends TPage
{
    // Branch de referência para o pull.
    const BRANCH = 'main';

    // URL HTTPS pública do repositório. Usada no pull para dispensar SSH/chave de
    // deploy no usuário do web server (o remote SSH continua para os pushes).
    const REPO_HTTPS = 'https://github.com/GuiAlve/meu-bolso.git';

    protected $form;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_sistema_atualizar');
        $this->form->setFormTitle('<i class="fa-solid fa-code-branch" style="color:#478fca"></i>&nbsp; Atualizar sistema');

        $info = self::getGitInfo();

        $card = new TElement('div');
        $card->style = 'padding:20px; border-radius:8px; background:#f8f9fa; border:1px solid #e3e6ea; margin-bottom:16px;';

        $card->add(self::infoRow('Branch',        $info['branch']));
        $card->add(self::infoRow('Commit atual',  $info['commit']));
        $card->add(self::infoRow('Atualizado em', $info['date']));

        $this->form->addContent([$card]);

        // resultado da última execução (guardado em sessão e limpo após exibir)
        $resultado = TSession::getValue(__CLASS__ . '_resultado');
        if (!empty($resultado))
        {
            TSession::setValue(__CLASS__ . '_resultado', null);

            $ok  = $resultado['ok'];
            $cor = $ok ? '#28a745' : '#dc3545';
            $ico = $ok ? 'fa-circle-check' : 'fa-triangle-exclamation';
            $ttl = $ok ? 'Atualização concluída' : 'Falha ao atualizar';

            $box = new TElement('div');
            $box->style = "padding:16px 20px; border-radius:8px; margin-bottom:16px; background:{$cor}1a; border:1px solid {$cor}55;";
            $box->add("<div style='font-weight:600; color:{$cor}; margin-bottom:8px;'><i class='fa-solid {$ico}'></i> " . $ttl . '</div>');
            $box->add("<pre style='margin:0; white-space:pre-wrap; word-break:break-word; font-family:monospace; font-size:13px; color:#333;'>"
                . htmlspecialchars($resultado['output']) . '</pre>');

            $this->form->addContent([$box]);
        }

        $this->form->addAction('Atualizar agora', new TAction([__CLASS__, 'onUpdate']), 'fa:download green');

        $vbox = new TVBox;
        $vbox->style = 'width:100%';
        $vbox->add(new TXMLBreadCrumb('menu.xml', __CLASS__));
        $vbox->add($this->form);

        parent::add($vbox);
    }

    /**
     * Executa `git pull origin main` na raiz do projeto e guarda o resultado
     * na sessão para exibição após o reload da página.
     */
    public static function onUpdate($param)
    {
        // Puxa pela URL HTTPS pública (sem SSH). 2>&1 captura erros para exibição.
        $command = sprintf(
            '%s pull %s %s 2>&1',
            self::gitPrefix(),
            escapeshellarg(self::REPO_HTTPS),
            escapeshellarg(self::BRANCH)
        );
        $output  = shell_exec($command);
        $output  = trim((string) $output);

        // heurística de sucesso: git pull não imprime "error/fatal" quando dá certo.
        $ok = ($output !== '')
            && (stripos($output, 'fatal') === false)
            && (stripos($output, 'error:') === false)
            && (stripos($output, 'Permission denied') === false);

        TSession::setValue(__CLASS__ . '_resultado', [
            'ok'     => $ok,
            'output' => $output !== '' ? $output : '(sem saída do comando)',
        ]);

        AdiantiCoreApplication::loadPage(__CLASS__);
    }

    /**
     * Coleta branch atual, último commit e data via git.
     */
    private static function getGitInfo()
    {
        $git = self::gitPrefix();

        $branch = trim((string) shell_exec(sprintf('%s rev-parse --abbrev-ref HEAD 2>&1', $git)));
        $commit = trim((string) shell_exec(sprintf('%s log -1 --pretty=format:%%h\\ %%s 2>&1', $git)));
        $date   = trim((string) shell_exec(sprintf('%s log -1 --date=format:%%d/%%m/%%Y\\ %%H:%%M --pretty=format:%%cd 2>&1', $git)));

        return [
            'branch' => $branch ?: '—',
            'commit' => $commit ?: '—',
            'date'   => $date   ?: '—',
        ];
    }

    /**
     * Prefixo comum dos comandos git: aponta para a raiz do projeto (-C) e
     * marca o diretório como seguro (safe.directory), evitando o erro de
     * "dubious ownership" quando o repo pertence a outro usuário (ex.: o PHP
     * roda como www-data e os arquivos são de outro dono).
     */
    private static function gitPrefix()
    {
        $repo = escapeshellarg(PATH);
        return sprintf('git -c safe.directory=%s -C %s', $repo, $repo);
    }

    private static function infoRow($label, $value)
    {
        return '<div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #e9ecef;">'
            . '<span style="color:#888; text-transform:uppercase; font-size:12px; letter-spacing:.5px;">' . htmlspecialchars($label) . '</span>'
            . '<span style="font-weight:600; font-family:monospace; color:#333; text-align:right;">' . htmlspecialchars($value) . '</span>'
            . '</div>';
    }
}
