-- Tabelas para o sistema de Provas do Método Grisa

CREATE TABLE IF NOT EXISTS `104_questoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` varchar(50) DEFAULT NULL,
  `enunciado` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `104_questoes_opcoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `questao` int(11) NOT NULL,
  `resposta` mediumtext DEFAULT NULL,
  `correta` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `questao` (`questao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `104_provas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `turma` int(11) NOT NULL,
  `data` date DEFAULT NULL,
  `inicio` time DEFAULT NULL,
  `termino` time DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `turma` (`turma`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `104_provas_questoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prova` int(11) NOT NULL,
  `questao` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `prova` (`prova`),
  KEY `questao` (`questao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `104_provas_alunos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prova` int(11) NOT NULL,
  `aluno` int(11) NOT NULL,
  `concluida` tinyint(1) DEFAULT 0,
  `nota` decimal(5,2) DEFAULT NULL,
  `autorizacao_especial` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `prova` (`prova`),
  KEY `aluno` (`aluno`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `104_provas_respostas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prova` int(11) NOT NULL,
  `aluno` int(11) NOT NULL,
  `questao` int(11) NOT NULL,
  `resposta` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prova` (`prova`),
  KEY `aluno` (`aluno`),
  KEY `questao` (`questao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
