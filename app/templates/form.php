<?php
// app/modules/importar/form.php

require_once __DIR__ . '/../../config/config.php';

$baseHref = BASE_URL . 'index.php';

// Tipos de importação disponíveis
$tipos_importacao = [
    'pessoas' => 'Pessoas',
    // Adicione outros tipos aqui no futuro
];

log_event($pdo, 'importar', 'ACESSO', 'Acesso ao formulário de importação');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Importar Dados</h4>
  <a href="<?php echo $baseHref; ?>" class="btn btn-outline-secondary btn-sm">Voltar</a>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post" action="<?php echo $baseHref; ?>?m=importar&a=process" enctype="multipart/form-data">
      <div class="row g-3">
        <div class="col-md-6">
          <label for="tipo" class="form-label">Tipo de Importação</label>
          <select id="tipo" name="tipo" class="form-select" required>
            <?php foreach ($tipos_importacao as $valor => $nome): ?>
              <option value="<?php echo $valor; ?>"><?php echo htmlspecialchars($nome); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label for="arquivo" class="form-label">Arquivo (CSV, TXT, etc.)</label>
          <input type="file" id="arquivo" name="arquivo" class="form-control" required>
        </div>
      </div>
      <div class="mt-3 text-end">
        <button type="submit" class="btn btn-primary">Processar Importação</button>
      </div>
    </form>
  </div>
</div>