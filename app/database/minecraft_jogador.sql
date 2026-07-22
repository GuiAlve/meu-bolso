-- Jogadores do servidor Minecraft (identificados pelo UUID).
-- Rodar na base `bolso`.
CREATE TABLE IF NOT EXISTS `minecraft_jogador` (
    `id`            INT NOT NULL AUTO_INCREMENT,
    `uuid`          VARCHAR(36) NOT NULL,
    `nome`          VARCHAR(32) NULL,
    `usuario_id`    INT NULL,
    `atualizado_em` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_mcjogador` (`usuario_id`, `uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
