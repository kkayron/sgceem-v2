<?php
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

// Proteção básica
if (!isset($_SESSION['usuario'])) {
    header('Location: /index.php');
    exit;
}

include_once("../../conexao/config.php");

$usuario = $_SESSION['usuario'];
$nome_guerra = $_SESSION['nomeguerra'];
$posto_grad = $_SESSION['postograd'];
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Suporte</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body { background: #f6f7fb; }
    .support-card { max-width: 720px; margin:auto; }
  </style>
</head>

<body>
<div class="container py-4">

  <div class="card shadow-sm border-0 support-card">
    <div class="card-body p-4">

      <h4 class="fw-bold mb-2">Suporte do Sistema</h4>
      <p class="text-muted mb-4">
        Utilize o formulário abaixo para relatar problemas, erros ou sugestões.
      </p>

      <?php if (!empty($_GET['ok'])): ?>
        <div class="alert alert-success">
          ✅ Mensagem enviada com sucesso! Nossa equipe irá analisar.
        </div>
      <?php elseif (!empty($_GET['erro'])): ?>
        <div class="alert alert-danger">
          ❌ Erro ao enviar a mensagem. Tente novamente.
        </div>
      <?php endif; ?>

      <form id="form-suporte" novalidate>

  <div class="mb-3">
    <label class="form-label">Nome</label>
    <input type="text" class="form-control"
      value="<?= htmlspecialchars(trim(($nome_guerra ?? '') . ' - ' . ($posto_grad ?? ''), ' -') ?: 'Não identificado') ?>"
      disabled>
    <!-- Se quiser enviar no POST também -->
    <input type="hidden" name="nome" value="<?= htmlspecialchars(trim(($nome_guerra ?? '') . ' - ' . ($posto_grad ?? ''), ' -') ?: 'Não identificado') ?>">
  </div>

  <div class="mb-3">
    <label class="form-label">E-mail</label>
    <input type="email" class="form-control"
      name="email"
      value=""
      placeholder="Digite seu e-mail para respostas"
      required>
  </div>

  <div class="mb-3">
    <label class="form-label">Assunto</label>
    <select name="assunto" class="form-select" required>
      <option value="">Selecione</option>
      <option value="Erro no sistema">Erro no sistema</option>
      <option value="Dúvida de uso">Dúvida de uso</option>
      <option value="Sugestão de melhoria">Sugestão de melhoria</option>
      <option value="Outro">Outro</option>
    </select>
  </div>

  <div class="mb-3">
    <label class="form-label">Mensagem</label>
    <textarea name="mensagem" class="form-control" rows="5" required
      placeholder="Descreva o problema ou dúvida com o máximo de detalhes possível..."></textarea>
  </div>

  <div class="d-flex gap-2 justify-content-end">
    <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
      Voltar
    </button>
    <button type="submit" class="btn btn-primary">
      Enviar mensagem
    </button>
  </div>

</form>

    </div>
  </div>

</div>
</body>
    <script>
    // SUPORTE - ENVIAR MENSAGEM
(function () {
  const form = document.getElementById('form-suporte');
  if (!form) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(form);

    fetch('includes/suporte/enviar_suporte.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
    .then(res => res.text())
    .then(text => {
      console.log("📌 Resposta bruta do servidor (debug):", text);

      let data;
      try {
        data = JSON.parse(text);
      } catch (err) {
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
          title: "Sucesso!",
          text: data.mensagem || "Mensagem enviada com sucesso!",
          icon: "success",
          button: { text: "OK", className: "btn btn-success" }
        }).then(() => {
          form.reset();
        });
      } else {
        swal({
          title: "Erro!",
          text: data.mensagem || "Erro ao enviar mensagem.",
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
})();
    </script>
</html>