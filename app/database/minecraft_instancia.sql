-- Configuração do tipo de instância EC2 e sua tarifa (US$/hora).
-- Usada para estimar o custo das sessões do servidor Minecraft.
-- Rodar na base `bolso`.
CREATE TABLE IF NOT EXISTS `minecraft_instancia` (
    `id`          INT NOT NULL AUTO_INCREMENT,
    `tipo`        VARCHAR(64) NOT NULL,           -- ex: t3.medium
    `custo_hora`  DECIMAL(10,4) NOT NULL DEFAULT 0, -- tarifa em US$/hora
    `usuario_id`  INT NULL,
    `created_at`  DATETIME NULL,
    `updated_at`  DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_mcinstancia_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
