<?php
session_start();
include_once('../../conexao/config.php');

if (!isset($_SESSION['usuario_id'])) {
    exit('Acesso negado');
}

$id = $_SESSION['usuario_id'];

$stmt = $conexao->prepare("SELECT u.*, f.nome AS funcao_nome 
FROM usuarios u
LEFT JOIN funcoes f ON u.funcao = f.id
WHERE u.id=?");

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();
?>

<div class="container-fluid mt-4">

<div class="row">

<!-- CARD PERFIL -->
<div class="col-lg-4">

<div class="card shadow-lg border-0 text-center">

<div class="card-body">

<img src="assets/fotoperfil/<?= $usuario['foto'] ?>"
class="rounded-circle shadow"
style="width:140px;height:140px;object-fit:cover">

<h4 class="mt-3 fw-bold">
<?= htmlspecialchars($usuario['postograd']) ?> <?= htmlspecialchars($usuario['nomeguerra']) ?>
</h4>

<p class="text-muted mb-1">
<?= htmlspecialchars($usuario['funcao_nome']) ?>
</p>

<hr>

<div class="text-start small">

<p><strong>Usuário:</strong> <?= htmlspecialchars($usuario['usuario']) ?></p>

<p><strong>Status:</strong> 
<span class="badge bg-success">Ativo</span>
</p>

<p><strong>ID do Militar:</strong> <?= $usuario['id'] ?></p>

</div>

</div>

</div>

</div>

<!-- FORMULÁRIO -->
<div class="col-lg-8">

<div class="card shadow-lg border-0">

<div class="card-header bg-dark text-white">
<i class="fas fa-user-edit"></i> Editar Meu Perfil
</div>

<div class="card-body">

<form id="form-editar-perfil" enctype="multipart/form-data">

<div class="row">

<div class="col-md-6 mb-3">
<label class="form-label fw-semibold">Posto / Graduação</label>
<input type="text"
class="form-control"
name="postograd"
value="<?= htmlspecialchars($usuario['postograd']) ?>"
required>
</div>

<div class="col-md-6 mb-3">
<label class="form-label fw-semibold">Nome de Guerra</label>
<input type="text"
class="form-control"
name="nomeguerra"
value="<?= htmlspecialchars($usuario['nomeguerra']) ?>"
required>
</div>

<div class="col-md-12 mb-3">
<label class="form-label fw-semibold">Nome Completo</label>
<input type="text"
class="form-control"
name="nomecompleto"
value="<?= htmlspecialchars($usuario['nomecompleto']) ?>"
required>
</div>

<div class="col-md-6 mb-3">
<label class="form-label fw-semibold">Usuário</label>
<input type="text"
class="form-control"
name="usuario"
value="<?= htmlspecialchars($usuario['usuario']) ?>"
readonly>
</div>

<div class="col-md-6 mb-3">
<label class="form-label fw-semibold">Alterar Foto</label>
<input type="file"
class="form-control"
name="foto">
</div>

<div class="col-md-12 mb-3">
<label class="form-label fw-semibold">Nova Senha</label>
<input type="password"
class="form-control"
name="senha"
placeholder="Preencha apenas se quiser alterar">
</div>

</div>

<div class="text-end">

<button class="btn btn-success px-4">
<i class="fas fa-save"></i> Salvar Alterações
</button>

</div>

</form>

</div>
</div>
</div>

</div>
</div>