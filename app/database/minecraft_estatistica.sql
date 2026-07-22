-- Estatísticas acumuladas por jogador do servidor Minecraft (vanilla).
-- Espelha os arquivos world/stats/<uuid>.json (valores vitalícios).
-- categoria = ex. 'minecraft:mined'; chave = ex. 'minecraft:diamond_ore'.
-- Rodar na base `bolso`.
CREATE TABLE IF NOT EXISTS `minecraft_estatistica` (
    `id`            INT NOT NULL AUTO_INCREMENT,
    `jogador_uuid`  VARCHAR(36) NOT NULL,
    `categoria`     VARCHAR(64) NOT NULL,
    `chave`         VARCHAR(128) NOT NULL,
    `valor`         BIGINT NOT NULL DEFAULT 0,
    `usuario_id`    INT NULL,
    `atualizado_em` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_mcstat_jogador` (`usuario_id`, `jogador_uuid`),
    UNIQUE KEY `uq_mcstat` (`usuario_id`, `jogador_uuid`, `categoria`, `chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
