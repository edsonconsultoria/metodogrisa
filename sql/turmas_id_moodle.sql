-- Adiciona campo id_moodle à tabela 104_turmas
-- para armazenar o ID do curso no Moodle (EAD Método Grisa)
ALTER TABLE `104_turmas` ADD COLUMN `id_moodle` INT(11) NULL DEFAULT NULL COMMENT 'ID do curso no Moodle (eadmetodogrisa.com.br)' AFTER `faculdade`;
