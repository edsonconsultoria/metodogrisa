-- Tabela de telefones adicionais por pessoa (mesmo modelo de pessoas_emails)
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `pessoas_fone` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_pessoa` int(11) NOT NULL,
  `telefone` varchar(30) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `is_padrao` tinyint(1) NOT NULL DEFAULT 0,
  `inclusao` datetime DEFAULT current_timestamp(),
  `alteracao` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pessoas_fone_pessoa` (`id_pessoa`),
  KEY `idx_pessoas_fone_telefone` (`telefone`),
  CONSTRAINT `fk_pessoas_fone_pessoa` FOREIGN KEY (`id_pessoa`) REFERENCES `pessoas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
