-- Tabela de reservas por categoria (orçamento mensal)
-- Rodar na base `bolso`.
CREATE TABLE IF NOT EXISTS `reserva` (
    `id`           INT NOT NULL AUTO_INCREMENT,
    `categoria_id` INT NOT NULL,
    `valor`        BIGINT NOT NULL DEFAULT 0,     -- valor reservado em centavos
    `ativo`        TINYINT(1) NOT NULL DEFAULT 1,
    `usuario_id`   INT NULL,
    `created_at`   DATETIME NULL,
    `updated_at`   DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `idx_reserva_usuario`   (`usuario_id`),
    KEY `idx_reserva_categoria` (`categoria_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
