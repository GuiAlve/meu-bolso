-- Sessões de uso do servidor Minecraft (EC2).
-- Registra início/fim, duração e custo estimado de cada vez que o servidor fica ligado.
-- Rodar na base `bolso`.
CREATE TABLE IF NOT EXISTS `minecraft_sessao` (
    `id`                INT NOT NULL AUTO_INCREMENT,
    `instance_id`       VARCHAR(32) NULL,
    `hora_inicio`       DATETIME NOT NULL,
    `hora_fim`          DATETIME NULL,
    `duracao_segundos`  INT NULL,               -- duração da sessão em segundos
    `custo_estimado`    DECIMAL(10,4) NULL,      -- custo estimado em US$
    `custo_hora`        DECIMAL(10,4) NULL,      -- tarifa US$/hora usada no cálculo
    `usuario_id`        INT NULL,
    `created_at`        DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_mcsessao_usuario`  (`usuario_id`),
    KEY `idx_mcsessao_instance` (`instance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
