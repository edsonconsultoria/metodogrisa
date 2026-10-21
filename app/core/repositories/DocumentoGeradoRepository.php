<?php
// app/core/repositories/DocumentoGeradoRepository.php

require_once __DIR__ . '/../Repository.php';

class DocumentoGeradoRepository extends Repository
{
    protected $table = '104_documentos_gerados';

    /**
     * Busca documentos gerados por um modelo específico, trazendo o nome da pessoa vinculada.
     */
    public function findByModelo(int $modeloId)
    {
        $sql = "SELECT dg.*, p.nome as pessoa_nome 
                FROM `{$this->table}` dg
                LEFT JOIN `101_pessoas` p ON dg.pessoa_id = p.id
                WHERE dg.modelo_id = ?
                ORDER BY dg.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$modeloId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
