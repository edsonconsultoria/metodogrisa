<?php
if (!current_user_is_at_least_admin()) { echo '<div class="alert alert-danger">Acesso negado.</div>'; return; }
$inicio = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['inicio'] ?? '') ? $_GET['inicio'] : date('Y-m-01');
$fim = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fim'] ?? '') ? $_GET['fim'] : date('Y-m-t');
$stmt=$pdo->prepare("SELECT tipo,status,COUNT(*) quantidade,SUM(valor) total FROM fin_lancamentos_caixa WHERE data_movimento BETWEEN ? AND ? GROUP BY tipo,status");
$stmt->execute([$inicio,$fim]); $grupos=$stmt->fetchAll();
$totais=['receita'=>0,'despesa'=>0,'transferencia'=>0,'pendente'=>0];
foreach($grupos as $g){if($g['status']==='pago')$totais[$g['tipo']]=(float)$g['total'];if($g['status']==='pendente')$totais['pendente']+=(float)$g['total'];}
$stmt=$pdo->prepare("SELECT c.nome,c.cor,l.tipo,SUM(l.valor) total FROM fin_lancamentos_caixa l JOIN fin_categorias c ON c.id=l.categoria_id WHERE l.status='pago' AND l.data_movimento BETWEEN ? AND ? GROUP BY c.id,l.tipo ORDER BY total DESC");
$stmt->execute([$inicio,$fim]); $porCategoria=$stmt->fetchAll();
$stmt=$pdo->prepare("SELECT c.nome,COALESCE(SUM(CASE WHEN l.tipo='receita' AND l.status='pago' THEN l.valor WHEN l.tipo='despesa' AND l.status='pago' THEN -l.valor ELSE 0 END),0) movimento FROM fin_contas c LEFT JOIN fin_lancamentos_caixa l ON l.conta_id=c.id AND l.data_movimento BETWEEN ? AND ? GROUP BY c.id ORDER BY c.nome");
$stmt->execute([$inicio,$fim]); $porConta=$stmt->fetchAll();
$saldo=$totais['receita']-$totais['despesa'];
?>
<div class="container-fluid py-4">
 <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><div><h2 class="h4 fw-bold mb-1">Relatórios financeiros</h2><p class="text-muted mb-0">Fluxo de caixa, categorias, contas e cartões.</p></div><a href="<?php echo BASE_URL;?>index.php?m=financeiro&a=dashboard" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Dashboard</a></div>
 <div class="card border-0 shadow-sm mb-4"><div class="card-body"><form class="row g-3 align-items-end"><input type="hidden" name="m" value="financeiro"><input type="hidden" name="a" value="relatorios"><div class="col-12 col-md-4"><label class="form-label">Início</label><input type="date" name="inicio" value="<?php echo htmlspecialchars($inicio);?>" class="form-control"></div><div class="col-12 col-md-4"><label class="form-label">Fim</label><input type="date" name="fim" value="<?php echo htmlspecialchars($fim);?>" class="form-control"></div><div class="col-12 col-md-4"><button class="btn btn-primary w-100">Atualizar relatório</button></div></form></div></div>
 <div class="row g-3 mb-4"><?php foreach([['Receitas',$totais['receita'],'success'],['Despesas',$totais['despesa'],'danger'],['Saldo',$saldo,$saldo>=0?'primary':'danger'],['Pendentes',$totais['pendente'],'warning']] as $c):?><div class="col-12 col-sm-6 col-xl-3"><div class="card border-0 shadow-sm"><div class="card-body"><span class="text-muted small"><?php echo $c[0];?></span><div class="fs-4 fw-bold text-<?php echo $c[2];?>">R$ <?php echo number_format($c[1],2,',','.');?></div></div></div></div><?php endforeach;?></div>
 <div class="row g-4">
  <div class="col-12 col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><h5 class="fw-bold">Por categoria</h5><div class="table-responsive"><table class="table"><thead><tr><th>Categoria</th><th>Tipo</th><th class="text-end">Total</th></tr></thead><tbody><?php foreach($porCategoria as $r):?><tr><td><?php echo htmlspecialchars($r['nome']);?></td><td><?php echo ucfirst($r['tipo']);?></td><td class="text-end">R$ <?php echo number_format((float)$r['total'],2,',','.');?></td></tr><?php endforeach;?></tbody></table></div></div></div></div>
  <div class="col-12 col-xl-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><h5 class="fw-bold">Movimento por conta</h5><div class="table-responsive"><table class="table"><thead><tr><th>Conta</th><th class="text-end">Movimento líquido</th></tr></thead><tbody><?php foreach($porConta as $r):?><tr><td><?php echo htmlspecialchars($r['nome']);?></td><td class="text-end">R$ <?php echo number_format((float)$r['movimento'],2,',','.');?></td></tr><?php endforeach;?></tbody></table></div></div></div></div>
 </div>
</div>
