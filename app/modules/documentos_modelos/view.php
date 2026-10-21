<?php
// app/modules/documentos_modelos/view.php

require_once __DIR__ . '/../../core/CrudEngine.php';
require_once __DIR__ . '/../../helpers/texto.php';

$tableName = '104_documentos_modelos';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$engine = new CrudEngine($pdo, $tableName);

$record = $engine->getRecord($id);
if (!$record) {
    die("Modelo não encontrado.");
}

$baseHref = BASE_URL . 'index.php';
$formatPtBrDateTime = function ($datetime) {
    if (!$datetime || $datetime === '0000-00-00 00:00:00')
        return '—';
    return date('d/m/Y H:i', strtotime($datetime));
};
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Visualizar Modelo de Documento</h4>
    <div class="d-flex gap-2">
        <a href="<?php echo $baseHref; ?>?m=documentos_modelos&a=form&id=<?php echo $id; ?>"
            class="btn btn-primary btn-sm">
            <i class="bi bi-pencil"></i> Editar
        </a>
        <a href="<?php echo $baseHref; ?>?m=documentos_modelos&a=list"
            class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-8">
                <label class="text-muted small d-block mb-1">Nome</label>
                <div class="fw-bold fs-5">
                    <?php echo htmlspecialchars($record['nome']); ?>
                </div>
            </div>

            <div class="col-md-4">
                <label class="text-muted small d-block mb-1">Status</label>
                <div>
                    <span class="badge <?php echo $record['ativo'] ? 'bg-success' : 'bg-secondary'; ?>">
                        <?php echo $record['ativo'] ? 'Ativo' : 'Inativo'; ?>
                    </span>
                </div>
            </div>

            <div class="col-md-12">
                <label class="text-muted small d-block mb-1">Descrição</label>
                <div class="fw-bold">
                    <?php echo htmlspecialchars($record['descricao'] ?: '—'); ?>
                </div>
            </div>

            <div class="col-md-6">
                <label class="text-muted small d-block mb-1">Criado em</label>
                <div class="fw-bold">
                    <?php echo $formatPtBrDateTime($record['created_at']); ?>
                </div>
            </div>

            <div class="col-md-6">
                <label class="text-muted small d-block mb-1">Última atualização</label>
                <div class="fw-bold">
                    <?php echo $formatPtBrDateTime($record['updated_at']); ?>
                </div>
            </div>

            <div class="col-md-12">
                <label class="text-muted small d-block mb-1">Arquivo Template (Word)</label>
                <div class="p-3 bg-light border rounded d-flex justify-content-between align-items-center">
                    <span class="fw-bold font-monospace">
                        <i class="bi bi-file-earmark-word text-primary"></i>
                        <?php echo htmlspecialchars($record['arquivo_template']); ?>
                    </span>
                    <a href="<?php echo UPLOADS_URL . '/' . $record['arquivo_template']; ?>" target="_blank"
                        class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-download"></i> Baixar Template
                    </a>
                </div>
            </div>
        </div>

        <!-- Sub-cadastro: Variáveis -->
        <hr class="my-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Variáveis do Modelo</h5>
            <a href="<?php echo $baseHref; ?>?m=documentos_modelos_variaveis&a=form&modelo_id=<?php echo $id; ?>"
                class="btn btn-outline-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Adicionar Variável
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover border">
                <thead class="table-light text-muted small uppercase">
                    <tr>
                        <th>Variável</th>
                        <th>Origem</th>
                        <th>Valor / Query</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php
                    $stmtV = $pdo->prepare("SELECT * FROM 104_documentos_modelos_variaveis WHERE modelo_id = ? ORDER BY variavel");
                    $stmtV->execute([$id]);
                    $variaveis = $stmtV->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($variaveis)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-3 text-muted">Nenhuma variável cadastrada.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($variaveis as $v): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($v['variavel']); ?></td>
                                <td><?php echo strtoupper($v['origem']); ?></td>
                                <td class="font-monospace small" style="white-space: pre-wrap; word-break: break-all;">
                                    <?php echo htmlspecialchars($v['valor']); ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $v['ativo'] ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $v['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="<?php echo $baseHref; ?>?m=documentos_modelos_variaveis&a=form&id=<?php echo $v['id']; ?>"
                                        class="btn btn-link btn-sm p-0 m-0" title="Editar"><i class="bi bi-pencil"></i></a>
                                    <a href="<?php echo $baseHref; ?>?m=documentos_modelos_variaveis&a=delete&id=<?php echo $v['id']; ?>"
                                        class="btn btn-link btn-sm p-0 m-0 ms-1 text-danger" title="Excluir"
                                        onclick="return confirm('Excluir esta variável?')"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>