<?php
// ===============================
// Página em Construção - Em breve
// ===============================
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

// (Opcional) exigir login
// Se você já tem um valida_login.php, pode incluir aqui em vez disso.
if (!isset($_SESSION['usuario'])) {
  // Se não quiser bloquear, comente este bloco
  header('Location: /index.php');
  exit;
}

$titulo = $_GET['titulo'] ?? 'Módulo em construção';
$detalhe = $_GET['msg'] ?? 'Estamos finalizando os ajustes para disponibilizar este conteúdo.';
$voltar = $_GET['voltar'] ?? ''; // ex: includes/dashboard.php
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($titulo) ?></title>

  <!-- Bootstrap (se já carrega no layout, pode remover daqui) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body { background: #f6f7fb; }
    .soon-wrap { min-height: 70vh; display:flex; align-items:center; justify-content:center; }
    .soon-card { max-width: 720px; }
    .badge-soft {
      background: rgba(13,110,253,.10);
      color: #0d6efd;
      border: 1px solid rgba(13,110,253,.15);
    }
  </style>
</head>

<body>
  <div class="container py-4">

    <div class="soon-wrap">
      <div class="card border-0 shadow-sm soon-card w-100">
        <div class="card-body p-4 p-md-5">

          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <span class="badge badge-soft rounded-pill px-3 py-2">Em breve</span>
            <small class="text-muted">Atualização em andamento</small>
          </div>

          <h3 class="fw-bold mb-2"><?= htmlspecialchars($titulo) ?></h3>
          <p class="text-muted mb-4">
            <?= htmlspecialchars($detalhe) ?>
          </p>

          <div class="alert alert-warning mb-4">
            <div class="d-flex gap-2">
              <div style="font-size:18px;">🚧</div>
              <div>
                <div class="fw-semibold">Página em construção</div>
                <div class="small">
                  Este módulo ainda não está disponível. Se precisar dessa funcionalidade com urgência,
                  informe ao administrador para priorização.
                </div>
              </div>
            </div>
          </div>

          

          <hr class="my-4">


        </div>
      </div>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>