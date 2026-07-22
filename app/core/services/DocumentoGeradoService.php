<?php
// app/core/services/DocumentoGeradoService.php

require_once __DIR__ . '/../repositories/DocumentoGeradoRepository.php';

class DocumentoGeradoService
{
    private $repository;
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->repository = new DocumentoGeradoRepository($pdo);
    }

    /**
     * Obtém a lista de documentos gerados para um modelo
     */
    public function listarPorModelo(int $modeloId)
    {
        if ($modeloId <= 0) {
            return [];
        }
        return $this->repository->findByModelo($modeloId);
    }
    /**
     * Exclui um documento e seu arquivo físico
     */
    public function excluir(int $id)
    {
        $documento = $this->repository->find($id);
        if (!$documento) {
            return false;
        }

        if ($this->repository->delete($id)) {
            // Remover arquivo físico se existir
            if (!empty($documento['arquivo_docx'])) {
                $filePath = UPLOADS_DIR . '/' . $documento['arquivo_docx'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Gera documentos em lote para todos os alunos de uma turma
     */
    public function gerarDocumentosTurma(int $modeloId, int $turmaId, int $usuarioId = null)
    {
        require_once __DIR__ . '/../../helpers/DocumentProcessor.php';
        require_once __DIR__ . '/../../helpers/texto.php';

        // 1. Buscar dados do modelo
        $stmtM = $this->pdo->prepare("SELECT nome, arquivo_template FROM 104_documentos_modelos WHERE id = ?");
        $stmtM->execute([$modeloId]);
        $modelo = $stmtM->fetch(PDO::FETCH_ASSOC);
        if (!$modelo)
            return ['success' => false, 'error' => 'Modelo não encontrado.'];

        $modeloNome = $modelo['nome'];
        $templateFile = $modelo['arquivo_template'];

        // 2. Buscar alunos da turma
        $stmtA = $this->pdo->prepare("SELECT aluno as pessoa_id FROM 104_alunos WHERE turma = ?");
        $stmtA->execute([$turmaId]);
        $alunos = $stmtA->fetchAll(PDO::FETCH_ASSOC);

        if (empty($alunos))
            return ['success' => false, 'error' => 'Esta turma não possui alunos cadastrados.'];

        $processor = new DocumentProcessor($this->pdo);
        $count = 0;

        foreach ($alunos as $aluno) {
            $pessoaId = (int) $aluno['pessoa_id'];

            // Buscar nome da pessoa para o arquivo
            $stmtP = $this->pdo->prepare("SELECT nome FROM 101_pessoas WHERE id = ?");
            $stmtP->execute([$pessoaId]);
            $pessoaNome = $stmtP->fetchColumn() ?: 'Pessoa';

            $dataHora = date('Ymd_His');
            $titulo = "{$modeloNome}_{$pessoaNome}_{$dataHora}";
            $arquivoDocx = slugify($modeloNome) . '_' . slugify($pessoaNome) . '_' . $dataHora . '.docx';

            // Resolver variáveis
            $variables = $processor->resolveVariables($modeloId, $pessoaId);

            // Dados para o banco
            $data = [
                'modelo_id' => $modeloId,
                'pessoa_id' => $pessoaId,
                'titulo' => $titulo,
                'arquivo_docx' => $arquivoDocx,
                'vars_json' => json_encode($variables, JSON_UNESCAPED_UNICODE),
                'hash' => hash('sha256', uniqid($titulo, true)),
                'created_at' => date('Y-m-d H:i:s'),
                'criado_por' => $usuarioId
            ];

            // Salvar no banco
            $stmtInsert = $this->pdo->prepare("
                INSERT INTO 104_documentos_gerados 
                (modelo_id, pessoa_id, titulo, arquivo_docx, vars_json, hash, created_at, criado_por)
                VALUES (:modelo_id, :pessoa_id, :titulo, :arquivo_docx, :vars_json, :hash, :created_at, :criado_por)
            ");

            if ($stmtInsert->execute($data)) {
                // Gerar arquivo físico
                if (!empty($templateFile)) {
                    $templatePath = UPLOADS_DIR . '/' . $templateFile;
                    $outputPath = UPLOADS_DIR . '/' . $arquivoDocx;
                    $processor->generate($templatePath, $outputPath, $variables);
                }

                // Sincronizar o documento gerado com a pasta de "Documentos Enviados" do aluno
                $this->sincronizarDocumentoAluno($pessoaId, $modeloId, $arquivoDocx, $titulo, $turmaId);

                $count++;
            }
        }

        return ['success' => true, 'count' => $count];
    }

    /**
     * Sincroniza o documento recém-gerado com a pasta de "Documentos Recebidos" do aluno.
     * Remove arquivos gerados anteriormente a partir do mesmo modelo para evitar duplicidade.
     */
    public function sincronizarDocumentoAluno(int $pessoaId, int $modeloId, string $arquivoDocx, string $titulo, int $turmaId = null): bool
    {
        require_once __DIR__ . '/../../helpers/texto.php';
        require_once __DIR__ . '/../../config/log.php';

        // 1. Obter nome do modelo para regras de similaridade
        $stmtM = $this->pdo->prepare("SELECT nome FROM 104_documentos_modelos WHERE id = ?");
        $stmtM->execute([$modeloId]);
        $modeloNome = $stmtM->fetchColumn();
        if (!$modeloNome) {
            return false;
        }

        $slugModelo = slugify($modeloNome);
        $slugModeloUnderscore = str_replace('-', '_', $slugModelo);
        $nomeModeloLower = mb_strtolower($modeloNome, 'UTF-8');

        // 2. Localizar o aluno na tabela 104_alunos
        if ($turmaId > 0) {
            $stmtAlu = $this->pdo->prepare("SELECT id, documentos_recebidos FROM 104_alunos WHERE aluno = ? AND turma = ?");
            $stmtAlu->execute([$pessoaId, $turmaId]);
        } else {
            $stmtAlu = $this->pdo->prepare("SELECT id, documentos_recebidos FROM 104_alunos WHERE aluno = ?");
            $stmtAlu->execute([$pessoaId]);
        }
        $alunos = $stmtAlu->fetchAll(PDO::FETCH_ASSOC);

        if (empty($alunos)) {
            return false;
        }

        foreach ($alunos as $aluno) {
            $docsJson = $aluno['documentos_recebidos'] ?? '';
            $docs = [];
            if (!empty($docsJson)) {
                $decoded = json_decode($docsJson, true);
                if (is_array($decoded)) {
                    $docs = $decoded;
                }
            }

            // 3. Filtrar e remover arquivos similares
            $docsFiltrados = [];
            foreach ($docs as $doc) {
                $name = $doc['name'] ?? '';
                $usrName = $doc['usrName'] ?? '';

                $nameLower = mb_strtolower(basename($name), 'UTF-8');
                $usrNameLower = mb_strtolower($usrName, 'UTF-8');

                $isSimilar = false;
                // Critério 1: Caminho físico contém o slug do modelo
                if ($slugModelo !== '' && (strpos($nameLower, $slugModelo) !== false || strpos($nameLower, $slugModeloUnderscore) !== false)) {
                    $isSimilar = true;
                }
                // Critério 2: Título de exibição contém o nome do modelo
                if (!$isSimilar && $nomeModeloLower !== '' && (strpos($usrNameLower, $nomeModeloLower) !== false || strpos($nameLower, slugify($nomeModeloLower)) !== false)) {
                    $isSimilar = true;
                }

                if (!$isSimilar) {
                    $docsFiltrados[] = $doc;
                }
            }

            // 4. Adicionar o novo documento gerado
            $filePath = UPLOADS_DIR . '/' . $arquivoDocx;
            $fileSize = file_exists($filePath) ? filesize($filePath) : 0;

            $novoDoc = [
                'name' => 'uploads/' . $arquivoDocx,
                'usrName' => $titulo . '.docx',
                'size' => $fileSize,
                'type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];

            $docsFiltrados[] = $novoDoc;

            // 5. Atualizar no banco
            $novoJson = json_encode($docsFiltrados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $stmtUpd = $this->pdo->prepare("UPDATE 104_alunos SET documentos_recebidos = ? WHERE id = ?");
            $stmtUpd->execute([$novoJson, $aluno['id']]);

            log_event($this->pdo, '104_alunos', 'UPDATE', "Sincronizado documento gerado '{$titulo}' em documentos_recebidos do aluno ID {$aluno['id']}");
        }

        return true;
    }
}
