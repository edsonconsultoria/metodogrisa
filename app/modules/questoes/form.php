<?php
$_TABLE = '104_questoes';
$_STATIC_OPTIONS = [
    'tipo' => [
        'Verificação Parcial Escrita 1' => 'Verificação Parcial Escrita 1',
        'Verificação Parcial Escrita 2' => 'Verificação Parcial Escrita 2',
        'Verificação Parcial Escrita 3' => 'Verificação Parcial Escrita 3'
    ]
];
$_HTML_FIELDS = ['enunciado'];
include __DIR__ . '/../generic/form.php';

if ($id > 0):
    $stmt = $pdo->prepare("SELECT * FROM 104_questoes_opcoes WHERE questao = ? ORDER BY id");
    $stmt->execute([$id]);
    $opcoes = $stmt->fetchAll();
    ?>
    <?php
    // Criar o URL de retorno para as sub-ações de opções, preservando o estado atual (incluindo filtros da lista)
    $passThrough = $_GET;
    $subFormReturn = "index.php?" . http_build_query($passThrough);
    ?>
    <div class="mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Opções da Questão</h5>
            <a href="index.php?m=104_questoes_opcoes&a=form&questao=<?php echo $id; ?>&redirect_to=<?php echo urlencode($subFormReturn); ?>"
                class="btn btn-success btn-sm">
                <i class="bi bi-plus-lg"></i> Adicionar Opção
            </a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th>Resposta</th>
                            <th style="width: 100px;" class="text-center">Correta</th>
                            <th style="width: 120px;" class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($opcoes)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">Nenhuma opção cadastrada.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($opcoes as $op): ?>
                                <tr>
                                    <td><?php echo $op['id']; ?></td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 500px;">
                                            <?php echo strip_tags($op['resposta']); ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($op['correta']): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-lg"></i> Sim</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Não</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="index.php?m=104_questoes_opcoes&a=form&id=<?php echo $op['id']; ?>&redirect_to=<?php echo urlencode($subFormReturn); ?>"
                                                class="btn btn-outline-primary" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="index.php?m=104_questoes_opcoes&a=delete&id=<?php echo $op['id']; ?>&return=<?php echo urlencode($subFormReturn); ?>"
                                                class="btn btn-outline-danger" title="Excluir"
                                                onclick="return confirm('Tem certeza que deseja excluir esta opção?')">
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