-- Rotina temporaria para migrar telefones da tabela pessoas para pessoas_fone
-- Uso: execute este script uma vez no banco icontrol_icontrole.

DROP PROCEDURE IF EXISTS tmp_migrar_telefones_pessoas_fone;
DELIMITER $$

CREATE PROCEDURE tmp_migrar_telefones_pessoas_fone()
BEGIN
  INSERT INTO pessoas_fone (id_pessoa, telefone, descricao, is_padrao)
  SELECT
    p.id,
    REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(p.telefone), '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '/', ''), '+', ''), ',', ''), ';', ''), ':', '') AS telefone,
    'Migrado de pessoas.telefone' AS descricao,
    CASE
      WHEN EXISTS (
        SELECT 1
        FROM pessoas_fone pf_exist
        WHERE pf_exist.id_pessoa = p.id
      ) THEN 0
      ELSE 1
    END AS is_padrao
  FROM pessoas p
  WHERE p.telefone IS NOT NULL
    AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(p.telefone), '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '/', ''), '+', ''), ',', ''), ';', ''), ':', '') <> ''
    AND NOT EXISTS (
      SELECT 1
      FROM pessoas_fone pf_dup
      WHERE pf_dup.id_pessoa = p.id
        AND pf_dup.telefone = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(p.telefone), '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '/', ''), '+', ''), ',', ''), ';', ''), ':', '')
    );
END$$

DELIMITER ;

CALL tmp_migrar_telefones_pessoas_fone();
DROP PROCEDURE IF EXISTS tmp_migrar_telefones_pessoas_fone;

-- Conferencia rapida:
-- SELECT COUNT(*) AS total_migrados FROM pessoas_fone WHERE descricao = 'Migrado de pessoas.telefone';
