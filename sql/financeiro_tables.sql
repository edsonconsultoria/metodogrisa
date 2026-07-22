-- Schema for the finance-related tables extracted from .base.
-- Run this script on the target MySQL server to build the supporting structure.
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `categoria_financeira` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `categoria` varchar(255) NOT NULL,
  `inclusao` datetime DEFAULT current_timestamp(),
  `alteracao` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_categoria_financeira_tenant` (`tenant_id`),
  CONSTRAINT `fk_categoria_financeira_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `empresas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `nome` varchar(255) NOT NULL,
  `tipo` enum('DINHEIRO','BANCO','CARTAO') NOT NULL,
  `banco` varchar(255) DEFAULT NULL,
  `agencia` varchar(255) DEFAULT NULL,
  `conta` varchar(255) DEFAULT NULL,
  `chave_pix` varchar(255) DEFAULT NULL,
  `inclusao` datetime DEFAULT current_timestamp(),
  `alteracao` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contas_tenant` (`tenant_id`),
  CONSTRAINT `fk_contas_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `empresas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `financeiro` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `vencimento` date NOT NULL,
  `pagamento` date DEFAULT NULL,
  `pessoa` int(11) NOT NULL,
  `descricao` text DEFAULT NULL,
  `categoria` int(11) NOT NULL,
  `conta` int(11) NOT NULL,
  `tipo` enum('Receita','Despesa') NOT NULL,
  `valor` decimal(12,2) NOT NULL,
  `obs` text DEFAULT NULL,
  `comprovante` text DEFAULT NULL,
  `origem` varchar(255) NOT NULL,
  `idorigem` int(11) NOT NULL,
  `inclusao` datetime DEFAULT current_timestamp(),
  `alteracao` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_financeiro_tenant` (`tenant_id`),
  KEY `idx_fk_pessoa` (`pessoa`),
  KEY `idx_fk_categoria` (`categoria`),
  KEY `idx_fk_conta` (`conta`),
  CONSTRAINT `fk_financeiro_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `empresas` (`id`),
  CONSTRAINT `fk_financeiro_pessoa` FOREIGN KEY (`pessoa`) REFERENCES `pessoas` (`id`),
  CONSTRAINT `fk_financeiro_categoria` FOREIGN KEY (`categoria`) REFERENCES `categoria_financeira` (`id`),
  CONSTRAINT `fk_financeiro_conta` FOREIGN KEY (`conta`) REFERENCES `contas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `financeiro_recorrente` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `vencimento` date NOT NULL,
  `pessoa` int(11) NOT NULL,
  `descricao` text DEFAULT NULL,
  `categoria` int(11) NOT NULL,
  `conta` int(11) NOT NULL,
  `tipo` enum('Receita','Despesa') NOT NULL,
  `periodicidade` enum('Semanal','Mensal','Anual') NOT NULL,
  `repeticoes` int(11) DEFAULT NULL,
  `valor` decimal(12,2) NOT NULL,
  `obs` text DEFAULT NULL,
  `inclusao` datetime DEFAULT current_timestamp(),
  `alteracao` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_financeiro_recorrente_tenant` (`tenant_id`),
  KEY `idx_fk_recorrente_pessoa` (`pessoa`),
  KEY `idx_fk_recorrente_categoria` (`categoria`),
  KEY `idx_fk_recorrente_conta` (`conta`),
  CONSTRAINT `fk_recorrente_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `empresas` (`id`),
  CONSTRAINT `fk_recorrente_pessoa` FOREIGN KEY (`pessoa`) REFERENCES `pessoas` (`id`),
  CONSTRAINT `fk_recorrente_categoria` FOREIGN KEY (`categoria`) REFERENCES `categoria_financeira` (`id`),
  CONSTRAINT `fk_recorrente_conta` FOREIGN KEY (`conta`) REFERENCES `contas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transfencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `data` date NOT NULL,
  `conta_origem` int(11) NOT NULL,
  `conta_destino` int(11) NOT NULL,
  `valor` decimal(12,2) NOT NULL,
  `inclusao` datetime DEFAULT current_timestamp(),
  `alteracao` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transfencias_tenant` (`tenant_id`),
  KEY `idx_transfencias_origem` (`conta_origem`),
  KEY `idx_transfencias_destino` (`conta_destino`),
  CONSTRAINT `fk_transfencias_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `empresas` (`id`),
  CONSTRAINT `fk_transfencias_origem` FOREIGN KEY (`conta_origem`) REFERENCES `contas` (`id`),
  CONSTRAINT `fk_transfencias_destino` FOREIGN KEY (`conta_destino`) REFERENCES `contas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
