SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `fin_contas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(100) NOT NULL,
  `tipo_conta` ENUM('corrente','poupanca','investimento','dinheiro','outros') NOT NULL DEFAULT 'corrente',
  `banco` VARCHAR(50) NULL,
  `agencia` VARCHAR(20) NULL,
  `numero_conta` VARCHAR(30) NULL,
  `chave_pix` VARCHAR(100) NULL,
  `status` ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fin_contas_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_categorias` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `categoria_pai_id` BIGINT UNSIGNED NULL,
  `nome` VARCHAR(100) NOT NULL,
  `tipo` ENUM('receita','despesa') NOT NULL,
  `cor` VARCHAR(7) NULL,
  `icone` VARCHAR(50) NULL,
  `status` ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fin_categorias_tipo_status` (`tipo`, `status`),
  CONSTRAINT `fk_fin_categorias_pai` FOREIGN KEY (`categoria_pai_id`) REFERENCES `fin_categorias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_lancamentos_caixa` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conta_id` BIGINT UNSIGNED NOT NULL,
  `categoria_id` BIGINT UNSIGNED NOT NULL,
  `pessoa_id` INT(11) NULL,
  `descricao` VARCHAR(255) NOT NULL,
  `tipo` ENUM('receita','despesa','transferencia') NOT NULL,
  `valor` DECIMAL(12,2) NOT NULL,
  `data_movimento` DATE NOT NULL,
  `data_vencimento` DATE NULL,
  `status` ENUM('pago','pendente','cancelado') NOT NULL DEFAULT 'pago',
  `recorrente` TINYINT(1) NOT NULL DEFAULT 0,
  `frequencia` ENUM('diario','semanal','mensal','anual') NULL,
  `tipo_recorrencia` ENUM('fixo','parcelado') NULL,
  `parcela_atual` INT UNSIGNED NOT NULL DEFAULT 1,
  `total_parcelas` INT UNSIGNED NULL,
  `lancamento_pai_id` BIGINT UNSIGNED NULL,
  `observacoes` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fin_caixa_conta_data` (`conta_id`, `data_movimento`),
  KEY `idx_fin_caixa_categoria` (`categoria_id`),
  KEY `idx_fin_caixa_pessoa` (`pessoa_id`),
  KEY `idx_fin_caixa_status_vencimento` (`status`, `data_vencimento`),
  KEY `idx_fin_caixa_pai` (`lancamento_pai_id`),
  CONSTRAINT `fk_fin_caixa_conta` FOREIGN KEY (`conta_id`) REFERENCES `fin_contas` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_fin_caixa_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `fin_categorias` (`id`) ON UPDATE CASCADE,
  -- pessoa_id referencia 101_pessoas.id logicamente, pois a tabela legada usa engine Aria e não aceita FK InnoDB.
  CONSTRAINT `fk_fin_caixa_pai` FOREIGN KEY (`lancamento_pai_id`) REFERENCES `fin_lancamentos_caixa` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_fin_caixa_valor` CHECK (`valor` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
