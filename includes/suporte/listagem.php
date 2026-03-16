<?php
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
include_once('../../conexao/config.php');

// (Opcional) exige login
if (!isset($_SESSION['usuario'])) {
  header('Location: /index.php');
  exit;
}

// (Opcional) trava apenas admin/nivel 1/2, ajuste como quiser
$nivel_usuario = (int)($_SESSION['usuario']['nivel'] ?? 3);
// if ($nivel_usuario > 2) { echo "<div class='alert alert-danger'>Acesso negado.</div>"; exit; }

// ==========================
// Filtros GET
// ==========================
$q         = trim($_GET['q'] ?? '');
$assunto   = trim($_GET['assunto'] ?? '');
$estado    = trim($_GET['estado'] ?? ''); // pendente|resolvido|todos
$data_ini  = trim($_GET['data_ini'] ?? '');
$data_fim  = trim($_GET['data_fim'] ?? '');

$filtros = [];
$params  = [];
$tipos   = "";

// Busca geral
if ($q !== '') {
  $filtros[] = "(nome LIKE ? OR email LIKE ? OR assunto LIKE ? OR mensagem LIKE ?)";
  $like = "%{$q}%";
  $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
  $tipos .= "ssss";
}

if ($assunto !== '') {
  $filtros[] = "assunto = ?";
  $params[] = $assunto;
  $tipos .= "s";
}

if ($estado === 'pendente') {
  $filtros[] = "COALESCE(resolvido,0) = 0";
} elseif ($estado === 'resolvido') {
  $filtros[] = "COALESCE(resolvido,0) = 1";
} // 'todos' ou vazio -> não filtra

if ($data_ini !== '') {
  // se você não tem coluna data_criacao, ignore esses filtros
  // (se tiver, adapte para sua coluna ex: created_at)
  $filtros[] = "1=1"; // placeholder
}

if ($data_fim !== '') {
  $filtros[] = "1=1"; // placeholder
}

$where = $filtros ? ("WHERE " . implode(" AND ", $filtros)) : "";

// ==========================
// Paginação
// ==========================
$limite = (isset($_GET['limite']) && is_numeric($_GET['limite'])) ? (int)$_GET['limite'] : 12;
$pagina = (isset($_GET['pagina']) && is_numeric($_GET['pagina']) && $_GET['pagina'] > 0) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// ==========================
// Total
// ==========================
$sqlTotal = "SELECT COUNT(*) AS total FROM suporte $where";
$stmtT = $conexao->prepare($sqlTotal);
if ($params) $stmtT->bind_param($tipos, ...$params);
$stmtT->execute();
$totalRegistros = (int)($stmtT->get_result()->fetch_assoc()['total'] ?? 0);
$stmtT->close();
$totalPaginas = max(1, (int)ceil($totalRegistros / $limite));

// ==========================
// Lista
// ==========================
$sql = "
  SELECT id, nome, email, assunto, mensagem, COALESCE(resolvido,0) AS resolvido
  FROM suporte
  $where
  ORDER BY id DESC
  LIMIT ? OFFSET ?
";
$paramsExec = $params;
$tiposExec  = $tipos . "ii";
$paramsExec[] = $limite;
$paramsExec[] = $offset;

$stmt = $conexao->prepare($sql);
$stmt->bind_param($tiposExec, ...$paramsExec);
$stmt->execute();
$res = $stmt->get_result();

// ==========================
// QueryString (paginação SPA)
/// ==========================
$paramsGET = $_GET;
unset($paramsGET['pagina']);
$queryString = http_build_query($paramsGET);

// helper paginação simples (10 links)
function renderPaginacaoSuporte($pagina, $totalPaginas, $limite, $queryString, $arquivo = 'includes/suporte/listagem.php') {
  if ($totalPaginas <= 1) return '';
  $maxLinks = 10;

  $makeUrl = function($p) use ($arquivo, $queryString, $limite) {
    return "{$arquivo}?{$queryString}&pagina={$p}&limite={$limite}";
  };

  $inicio = max(1, $pagina - (int)floor($maxLinks/2));
  $fim = min($totalPaginas, $inicio + $maxLinks - 1);
  if (($fim - $inicio) < ($maxLinks - 1)) $inicio = max(1, $fim - $maxLinks + 1);

  $html  = '<div class="pagination-wrapper mt-3">';
  $html .= '<nav><ul class="pagination pagination-sm justify-content-center">';

  if ($pagina > 1) {
    $html .= "<li class='page-item'><a class='page-link paginacao-suporte' href='#' data-page='".$makeUrl(1)."'>&laquo;</a></li>";
    $html .= "<li class='page-item'><a class='page-link paginacao-suporte' href='#' data-page='".$makeUrl($pagina-1)."'>&lsaquo;</a></li>";
  }

  if ($inicio > 1) $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";

  for ($i=$inicio; $i<=$fim; $i++) {
    $active = ($i == $pagina) ? 'active' : '';
    $html .= "<li class='page-item {$active}'><a class='page-link paginacao-suporte' href='#' data-page='".$makeUrl($i)."'>{$i}</a></li>";
  }

  if ($fim < $totalPaginas) $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";

  if ($pagina < $totalPaginas) {
    $html .= "<li class='page-item'><a class='page-link paginacao-suporte' href='#' data-page='".$makeUrl($pagina+1)."'>&rsaquo;</a></li>";
    $html .= "<li class='page-item'><a class='page-link paginacao-suporte' href='#' data-page='".$makeUrl($totalPaginas)."'>&raquo;</a></li>";
  }

  $html .= '</ul></nav></div>';
  return $html;
}
?>

<div class="card shadow-sm border-0">
  <div class="card-body">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <h5 class="fw-bold mb-0">Suporte</h5>
      <small class="text-muted">Total: <?= (int)$totalRegistros ?></small>
    </div>

    <!-- Filtros -->
    <form id="form-filtro-suporte" class="row g-2 mb-3">
      <div class="col-12 col-md-4">
        <input type="text" name="q" class="form-control" placeholder="Buscar por nome, email, assunto ou mensagem"
               value="<?= htmlspecialchars($q) ?>">
      </div>

      <div class="col-12 col-md-3">
        <select name="assunto" class="form-select">
          <option value="">Assunto (todos)</option>
          <option <?= $assunto==='Erro no sistema'?'selected':'' ?> value="Erro no sistema">Erro no sistema</option>
          <option <?= $assunto==='Dúvida de uso'?'selected':'' ?> value="Dúvida de uso">Dúvida de uso</option>
          <option <?= $assunto==='Sugestão de melhoria'?'selected':'' ?> value="Sugestão de melhoria">Sugestão de melhoria</option>
          <option <?= $assunto==='Outro'?'selected':'' ?> value="Outro">Outro</option>
        </select>
      </div>

      <div class="col-12 col-md-3">
        <select name="estado" class="form-select">
          <option value="" <?= ($estado===''?'selected':'') ?>>Status (todos)</option>
          <option value="pendente" <?= ($estado==='pendente'?'selected':'') ?>>Pendente</option>
          <option value="resolvido" <?= ($estado==='resolvido'?'selected':'') ?>>Resolvido</option>
        </select>
      </div>

      <div class="col-12 col-md-2 d-grid">
        <button class="btn btn-primary" type="submit">
          Filtrar
        </button>
      </div>
    </form>

    <?php if ($res->num_rows <= 0): ?>
      <div class="alert alert-info mb-0">Nenhuma mensagem encontrada.</div>
    <?php else: ?>

      <div class="row g-3">
        <?php while ($s = $res->fetch_assoc()): ?>
          <?php
            $isResolvido = ((int)$s['resolvido'] === 1);
          ?>
          <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100 shadow-sm border-0">
              <div class="card-body">

                <div class="d-flex align-items-start justify-content-between gap-2">
                  <div>
                    <div class="fw-bold mb-1"><?= htmlspecialchars($s['assunto']) ?></div>
                    <div class="small text-muted">
                      <div><?= htmlspecialchars($s['nome']) ?></div>
                      <div><?= htmlspecialchars($s['email']) ?></div>
                    </div>
                  </div>

                  <span class="badge <?= $isResolvido ? 'bg-success' : 'bg-warning text-dark' ?>">
                    <?= $isResolvido ? 'Resolvido' : 'Pendente' ?>
                  </span>
                </div>

                <hr>

                <div class="small" style="white-space:pre-wrap;">
                  <?= nl2br(htmlspecialchars($s['mensagem'])) ?>
                </div>

                <hr>

                <div class="d-flex justify-content-end gap-2">
                  <?php if ($isResolvido): ?>
                    <button class="btn btn-sm btn-outline-warning btn-toggle-resolvido"
                            data-id="<?= (int)$s['id'] ?>" data-valor="0">
                      <i class="fas fa-undo me-1"></i> Reabrir
                    </button>
                  <?php else: ?>
                    <button class="btn btn-sm btn-outline-success btn-toggle-resolvido"
                            data-id="<?= (int)$s['id'] ?>" data-valor="1">
                      <i class="fas fa-check me-1"></i> Resolver
                    </button>
                  <?php endif; ?>
                </div>

              </div>
            </div>
          </div>
        <?php endwhile; ?>
      </div>

      <?= renderPaginacaoSuporte($pagina, $totalPaginas, $limite, $queryString) ?>

    <?php endif; ?>

  </div>
</div>

<script>
// FILTRO (SPA)
(function(){
  const form = document.getElementById('form-filtro-suporte');
  if (!form) return;

  form.addEventListener('submit', function(e){
    e.preventDefault();
    const qs = new URLSearchParams(new FormData(form)).toString();
    // se você usa SPA com carregarPagina:
    if (typeof carregarPagina === 'function') {
      carregarPagina('includes/suporte/listagem.php?' + qs);
    } else {
      window.location.href = 'includes/suporte/listagem.php?' + qs;
    }
  });
})();

// PAGINAÇÃO (SPA)
document.addEventListener('click', function(e){
  const a = e.target.closest('.paginacao-suporte');
  if (!a) return;
  e.preventDefault();
  const url = a.getAttribute('data-page');
  if (typeof carregarPagina === 'function') carregarPagina(url);
  else window.location.href = url;
});

// TOGGLE RESOLVIDO
document.addEventListener('click', function(e){
  const btn = e.target.closest('.btn-toggle-resolvido');
  if (!btn) return;

  const id = btn.getAttribute('data-id');
  const valor = btn.getAttribute('data-valor'); // 1 resolve | 0 reabre

  const fd = new FormData();
  fd.append('id', id);
  fd.append('resolvido', valor);

  fetch('includes/suporte/toggle_resolvido.php', {
    method: 'POST',
    body: fd,
    credentials: 'same-origin'
  })
  .then(res => res.text())
  .then(text => {
    console.log("📌 Resposta bruta do servidor (debug):", text);

    let data;
    try { data = JSON.parse(text); }
    catch (err) {
      swal({
        title: "Erro!",
        text: "Resposta inválida do servidor. Veja o console (F12 → Console).",
        icon: "error",
        button: { text: "Fechar", className: "btn btn-danger" }
      });
      return;
    }

    if (data.status === 'sucesso') {
      swal({
        title: "OK!",
        text: data.mensagem || "Atualizado!",
        icon: "success",
        button: { text: "OK", className: "btn btn-success" }
      }).then(() => {
        // recarrega a própria listagem mantendo querystring atual
        if (typeof carregarPagina === 'function') {
          carregarPagina('includes/suporte/listagem.php?<?= htmlspecialchars($queryString) ?>&pagina=<?= (int)$pagina ?>&limite=<?= (int)$limite ?>');
        } else {
          window.location.reload();
        }
      });
    } else {
      swal({
        title: "Erro!",
        text: data.mensagem || "Erro ao atualizar.",
        icon: "error",
        button: { text: "Fechar", className: "btn btn-danger" }
      });
    }
  })
  .catch(err => {
    swal({
      title: "Erro!",
      text: "Erro de rede: " + err.message,
      icon: "error",
      button: { text: "Fechar", className: "btn btn-danger" }
    });
  });
});
</script>