<?php
// app/modules/provas/form.php

$_TABLE = '104_provas';
$_LOOKUPS = [
    'turma' => ['table' => '104_turmas', 'label' => 'nome']
];

$_STATIC_OPTIONS = [
    'tipo' => [
        'Verificação Parcial Escrita 1' => 'Verificação Parcial Escrita 1',
        'Verificação Parcial Escrita 2' => 'Verificação Parcial Escrita 2',
        'Verificação Parcial Escrita 3' => 'Verificação Parcial Escrita 3'
    ]
];

$_FIELD_ORDER = ['id', 'turma', 'tipo', 'data', 'inicio', 'termino'];

$_FORM_LAYOUT = [
    'turma' => 6,
    'tipo' => 6,
    'data' => 4,
    'inicio' => 4,
    'termino' => 4
];

require __DIR__ . '/../generic/form.php';

if ($id > 0):
    $stmt = $pdo->prepare("
        SELECT pq.*, q.enunciado, q.tipo as questao_tipo 
        FROM 104_provas_questoes pq 
        JOIN 104_questoes q ON q.id = pq.questao 
        WHERE pq.prova = ? 
        ORDER BY pq.id
    ");
    $stmt->execute([$id]);
    $questoes = $stmt->fetchAll();
    ?>
    <?php
    // Buscar questões disponíveis (do mesmo tipo e que ainda não estão na prova)
    $stmtAvail = $pdo->prepare("
        SELECT id, enunciado 
        FROM 104_questoes 
        WHERE tipo = ? 
        AND id NOT IN (SELECT questao FROM 104_provas_questoes WHERE prova = ?)
        ORDER BY RAND()
    ");
    $stmtAvail->execute([$record['tipo'], $id]);
    $disponiveis = $stmtAvail->fetchAll();
    ?>
    <div class="mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Questões da Prova</h5>
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addQuestaoModal">
                <i class="bi bi-plus-lg"></i> Vincular Questão
            </button>
        </div>

        <!-- Modal para Vincular Questão -->
        <div class="modal fade" id="addQuestaoModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <form action="index.php?m=104_provas_questoes&a=save" method="post">
                    <input type="hidden" name="prova" value="<?php echo $id; ?>">
                    <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars("index.php?m=104_provas&a=form&id=$id"); ?>">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Vincular Nova Questão</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small mb-3">Exibindo questões do tipo: <strong><?php echo htmlspecialchars($record['tipo']); ?></strong></p>
                            <div class="mb-3">
                                <label class="form-label">Selecione a Questão</label>
                                <select name="questao" class="form-select select2-basic" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($disponiveis as $dq): ?>
                                        <option value="<?php echo $dq['id']; ?>">
                                            [ID: <?php echo $dq['id']; ?>] <?php echo substr(strip_tags($dq['enunciado']), 0, 150); ?>...
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Adicionar à Prova</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Questão (Enunciado)</th>
                            <th style="width: 150px;">Tipo</th>
                            <th style="width: 100px;" class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($questoes)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">Nenhuma questão vinculada a esta prova.</td>
                            </tr>
                        <?php else: ?>
                            <?php $count = 1; foreach ($questoes as $q): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo $count++; ?>º</td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 600px;">
                                            <?php echo strip_tags($q['enunciado']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark"><?php echo htmlspecialchars($q['questao_tipo']); ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="index.php?m=104_provas_questoes&a=delete&id=<?php echo $q['id']; ?>&return=<?php echo urlencode("index.php?m=104_provas&a=form&id=$id"); ?>"
                                                class="btn btn-outline-danger" title="Remover da Prova"
                                                onclick="return confirm('Tem certeza que deseja remover esta questão da prova?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
