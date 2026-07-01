<?php
/**
 * MinecraftServer
 *
 * Controla a instância EC2 do servidor de Minecraft.
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
    const INSTANCE_ID = 'i-0a57e67d17372c828';
    const AWS_PROFILE = 'minha-conta-pessoal';

    protected $form;

    public function __construct()
    {
        parent::__construct();

        $this->form = new BootstrapFormBuilder('form_ec2_start');
        $this->form->setFormTitle('<i class="fa-solid fa-cube" style="color:#5b8731"></i>&nbsp; Servidor Minecraft');

        $status = self::getInstanceStatus(self::INSTANCE_ID);

        $badge = self::getStatusBadge($status['state']);

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

        $this->form->addContent([$card]);

        $btnReload = $this->form->addAction('Atualizar status', new TAction([__CLASS__, 'onReload']), 'fa:sync blue', 'btn_reload_ec2');

        if ($status['state'] === 'stopped')
        {
            $this->form->addAction('Ligar', new TAction([__CLASS__, 'onStart']), 'fa:play green');
        }

        if ($status['state'] === 'running')
        {
            $this->form->addAction('Desligar', new TAction([__CLASS__, 'onStop']), 'fa:power-off red');
        }

        if (in_array($status['state'], ['pending', 'stopping']))
        {
            TScript::create("setTimeout(function(){ document.getElementById('btn_reload_ec2').click(); }, 5000);");
        }

        parent::add($this->form);
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
            'aws ec2 describe-instances --instance-ids %s --profile %s --query "Reservations[0].Instances[0].[State.Name,PublicIpAddress]" --output json 2>&1',
            escapeshellarg($instance_id),
            escapeshellarg(self::AWS_PROFILE)
        );

        $output = shell_exec($command);
        $data   = json_decode($output, true);

        if (!is_array($data))
        {
            return ['state' => 'Erro: ' . trim((string) $output), 'ip' => null];
        }

        return ['state' => $data[0] ?? 'Desconhecido', 'ip' => $data[1] ?? null];
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

            new TMessage('info', 'Comando de desligamento enviado.');
        }
        catch (Exception $e)
        {
            new TMessage('error', $e->getMessage());
        }

        AdiantiCoreApplication::loadPage(__CLASS__);
    }

    public static function onReload($param)
    {
        AdiantiCoreApplication::loadPage(__CLASS__);
    }
}
?>
