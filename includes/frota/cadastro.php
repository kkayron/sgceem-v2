<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
include_once('../../conexao/config.php');

require_once '../api/seguranca_cadastrar.php';

$permissoes = verificarPermissao(2);

$pode_cadastrar = $permissoes['cadastrar'];
$pode_editar    = $permissoes['editar'];
$pode_deletar   = $permissoes['deletar'];
$pode_importar  = $permissoes['importar'];
?>

<style>
  .foto-preview {
    width: 110px;
    height: 110px;
    object-fit: cover;
    border-radius: 50%;
    border: 3px solid #ddd;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
  }
  .section-title {
    background-color: #f8f9fa;
    border-radius: 10px;
    font-weight: bold;
    text-align: center;
    padding: 8px;
    margin-top: 30px;
  }
</style>

<div class="container py-4">
  <div class="page-inner">
    <div class="d-flex justify-content-between align-items-center py-3">
      <h3 class="fw-bold mb-0">Cadastro de Viatura / Equipamento</h3>
      
      <?php if ($pode_importar): ?>
      <button type="button" class="btn btn-success d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalImportarViaturas">
        <i class="fas fa-file-import"></i> Importar Planilha
      </button>
      <?php endif; ?>
    </div>

    <div class="card shadow-sm border-0">
      <div class="card-body">
        <form id="formCadastrarFrota" class="row g-3" enctype="multipart/form-data">

          <!-- FOTO -->
          <div class="text-center mb-4">
            <img id="previewFotoCapa" src="https://cdn-icons-png.flaticon.com/512/3600/3600953.png" alt="Foto da Viatura" class="foto-preview">
            <p class="mt-2 small text-muted">Enviar foto</p>
            <input type="file" name="foto_capa" id="fotoCapa" class="form-control" accept="image/*" onchange="previewImagemCapa(event)">
          </div>

          <!-- SEÇÃO 1 -->
          <div class="col-12 section-title">
            <i class="fas fa-car me-2"></i> Informações Básicas do Ativo
          </div>

          <div class="col-md-6">
            <label class="form-label">Tipo do ativo</label>
            <select name="ativo" id="ativo" class="form-select" required>
              <option value="">Selecione...</option>
              <?php
              $sqlTipos = "SELECT id, abreviatura, descricao, tipo FROM config_tiposvtreqp ORDER BY descricao";
              $resultTipos = $conexao->query($sqlTipos);
              while ($tipo = $resultTipos->fetch_assoc()):
              ?>
                <option value="<?= $tipo['abreviatura'] ?>">
                  <?= htmlspecialchars($tipo['abreviatura'] . ' - ' . $tipo['descricao'] . ' (' . $tipo['tipo'] . ')') ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Tipo</label>
            <select name="tipo" id="tipo" class="form-select" required>
              <option value="">Selecione...</option>
              <option value="Eqp">Equipamento</option>
              <option value="Vtr">Viatura</option>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Prefixo Velho</label>
            <input type="text" name="prefixo_velho" class="form-control">
          </div>

          <div class="col-md-6">
            <label class="form-label">Prefixo SGA</label>
            <input type="text" name="prefixo_sga" class="form-control">
          </div>

          <div class="col-md-6">
            <label class="form-label">Nome SIOC</label>
            <input type="text" name="nome_sioc" class="form-control">
          </div>

          <div class="col-md-6">
            <label class="form-label">Nº Patrimônio</label>
            <input type="text" name="nmr_patrimonio" class="form-control">
          </div>

          <div class="col-md-6">
            <label class="form-label">Nº EB</label>
            <input type="text" name="nmr_eb" class="form-control">
          </div>

          <div class="col-md-6">
            <label class="form-label">Chassi</label>
            <input type="text" name="chassi" class="form-control">
          </div>

          <div class="col-md-6">
            <label class="form-label">Acervo</label>
            <input type="text" name="acervo" class="form-control">
          </div>

          <div class="col-md-6">
            <label class="form-label">Ano</label>
            <input type="text" name="ano" class="form-control">
          </div>

          <div class="col-md-6">
            <label class="form-label">Marca</label>
            <select name="marca" id="marca" class="form-select" required>
              <option value="">Selecione...</option>
              <?php
              $sqlMarcas = "SELECT id, marca FROM config_marcas ORDER BY marca";
              $resMarcas = $conexao->query($sqlMarcas);
              while ($marca = $resMarcas->fetch_assoc()):
              ?>
                <option value="<?= $marca['id'] ?>"><?= htmlspecialchars($marca['marca']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Modelo</label>
            <select name="modelo" id="modelo" class="form-select" required>
              <option value="">Selecione uma marca primeiro</option>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Capacidade do Tanque</label>
            <input type="text" name="capacidade_tanque" class="form-control">
          </div>

          <div class="col-md-6">
            <label class="form-label">Consumo</label>
            <input type="text" name="consumo" class="form-control">
          </div>

          <div class="col-md-6">
            <label class="form-label">Ordem Fragmentária</label>
            <input type="text" name="ordem_fragmentaria" class="form-control">
          </div>

          <div class="col-md-6">
            <label class="form-label">Placa</label>
            <input type="text" name="placa" class="form-control">
          </div>

          <div class="col-md-6">
            <label class="form-label">Subunidade</label>
            <input type="text" name="subunidade" class="form-control">
          </div>

          <div class="col-md-6">
            <label class="form-label">Renavam</label>
            <input type="text" name="renavam" class="form-control">
          </div>

          <div class="col-md-6">
            <label class="form-label">Trem</label>
            <input type="text" name="trem" class="form-control">
          </div>
            
            <?php
// ============================
// BATALHÕES QUE O USUÁRIO PODE VISUALIZAR
// ============================
$id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;

// OM nível 1 (acesso total)
$sqlNivel = "SELECT nivel FROM organizacoes_militares WHERE id = $id_om_usuario";
$resNivel = $conexao->query($sqlNivel);
$nivelUsuario = $resNivel->fetch_assoc()['nivel'] ?? 0;

if ($nivelUsuario == 1) {
    // Nível 1 vê todas as OM
    $sqlBatalhoes = "SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY nome";
} else {
    // Demais níveis veem apenas as OM relacionadas
    $sqlBatalhoes = "
        SELECT om.id, om.nome, om.abreviatura
        FROM organizacoes_militares om
        WHERE om.id = $id_om_usuario
        OR om.id IN (
            SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = $id_om_usuario
        )
        ORDER BY om.nome
    ";
}
$resBatalhoes = $conexao->query($sqlBatalhoes);
?>

<div class="col-md-6">
  <label class="form-label">Batalhão</label>
  <select name="batalhao" id="batalhao_importacao" class="form-select mb-3" required>
  <option value="">Selecione...</option>
  <?php while ($bat = $resBatalhoes->fetch_assoc()): ?>
    <option value="<?= $bat['id'] ?>" <?= ($bat['id']==$id_om_usuario)?'selected':'' ?>>
      <?= htmlspecialchars($bat['abreviatura'] . ' - ' . $bat['nome']) ?>
    </option>
  <?php endwhile; ?>
</select>

</div>


          <!-- SEÇÃO 2 -->
          <div class="col-12 section-title">
            <i class="fas fa-info-circle me-2"></i> Informações Temporárias do Ativo
          </div>

          <div class="col-md-6">
            <label class="form-label">Status / Confiabilidade</label>
            <select name="confiabilidade" class="form-select" required>
              <option value="">Selecione...</option>
              <option value="Confiável">Confiável</option>
              <option value="Não confiável">Não confiável</option>
              <option value="Emprestado">Emprestado</option>
              <option value="Em processo de descarga">Em processo de descarga</option>
              <option value="Descarregado">Descarregado</option>
              <option value="Desfeita (Leiloada ou Recolhida)">Desfeita (Leiloada ou Recolhida)</option>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Disponibilidade</label>
            <select name="disponibilidade" class="form-select" required>
              <option value="">Selecione...</option>
              <option value="Disponível">Disponível</option>
              <option value="Disponível com restrição">Disponível com restrição</option>
              <option value="Indisponível">Indisponível</option>
            </select>
          </div>
            <?php
// ID e nível da OM do usuário logado
$om_id = $_SESSION['usuario']['batalhao'] ?? 0;
$om_nivel = $_SESSION['usuario']['nivel'] ?? 1;

// ---------------------------
// DEFINIÇÃO DOS BATALHÕES PERMITIDOS
// ---------------------------
$batalhoesPermitidos = [];

if ($om_nivel == 1) {
    // Nível 1 vê todos, inclusive NULL (se existir)
    $queryBatalhoes = "SELECT id FROM organizacoes_militares";
    $result = $conexao->query($queryBatalhoes);
    while ($r = $result->fetch_assoc()) {
        $batalhoesPermitidos[] = (int)$r['id'];
    }
} elseif ($om_nivel == 2) {
    // Nível 2: própria OM + subordinadas
    $queryBatalhoes = $conexao->prepare("
        SELECT id_om_menor AS id FROM organizacoes_militares_sub WHERE id_om_maior = ?
        UNION SELECT ? AS id
    ");
    $queryBatalhoes->bind_param('ii', $om_id, $om_id);
    $queryBatalhoes->execute();
    $result = $queryBatalhoes->get_result();
    while ($r = $result->fetch_assoc()) {
        $batalhoesPermitidos[] = (int)$r['id'];
    }
} else {
    // Nível 3: apenas própria OM
    $batalhoesPermitidos[] = (int)$om_id;
}

// Remove duplicados e inválidos
$batalhoesPermitidos = array_values(array_unique(array_filter($batalhoesPermitidos, fn($v) => $v > 0)));
            
$destinos = [];

if (!empty($batalhoesPermitidos)) {

    // Monta placeholders: "?, ?, ?, ..."
    $placeholders = implode(',', array_fill(0, count($batalhoesPermitidos), '?'));

    // Tipos do bind (todos inteiros)
    $types = str_repeat('i', count($batalhoesPermitidos));

$sql = "
    SELECT 
        d.id,
        d.destino,
        d.batalhao,
        om.nome AS nome_batalhao
    FROM config_destinos d
    LEFT JOIN organizacoes_militares om 
        ON om.id = d.batalhao
    WHERE d.batalhao IN ($placeholders)
    ORDER BY om.nome ASC, d.destino ASC
";

    $stmt = $conexao->prepare($sql);

    // bind_param precisa de referência
    $params = array_merge([$types], $batalhoesPermitidos);
    $tmp = [];
    foreach ($params as $k => $v) $tmp[$k] = &$params[$k];
    call_user_func_array([$stmt, 'bind_param'], $tmp);

    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $destinos[] = $row;
    }
}
?>
 <div class="form-group">
    <label>Destino</label>
    <select name="destino" class="form-control" required>
        <option value="">Selecione o destino</option>

        <?php foreach ($destinos as $d): ?>
            <option value="<?= htmlspecialchars($d['destino']) ?>">
                <?= htmlspecialchars($d['destino']) ?>
                <?php if (!empty($d['nome_batalhao'])): ?>
                    — <?= htmlspecialchars($d['nome_batalhao']) ?>
                <?php endif; ?>
            </option>
        <?php endforeach; ?>

    </select>
</div>

          <div class="col-md-6">
            <label class="form-label">Missão</label>
            <input type="text" name="missao" class="form-control">
          </div>

          <div class="col-md-6">
            <label class="form-label">Emprego Atual</label>
            <input type="text" name="emprego_atual" class="form-control">
          </div>

          <div class="col-md-12">
            <label class="form-label">Observação EncMat</label>
            <textarea name="obs_encmat" rows="2" class="form-control"></textarea>
          </div>

          <?php if ($pode_cadastrar): ?>
          <div class="col-12 text-end mt-3">
           <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <button type="submit" class="btn btn-success px-4">
              <i class="fas fa-save me-1"></i> Cadastrar Viatura/Equipamento
            </button>
          </div>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>
</div>

          <?php if ($pode_importar): ?>
<!-- Modal de Importação -->
<div class="modal fade" id="modalImportarViaturas" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formImportarFrota" method="POST" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Importar Planilha de Viaturas / Equipamentos</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
<?php
          // ============================
          // MESMA LÓGICA DE BATALHÕES QUE O USUÁRIO PODE VER
          // ============================
          $id_om_usuario = $_SESSION['usuario']['batalhao'] ?? 0;

          $sqlNivel = "SELECT nivel FROM organizacoes_militares WHERE id = $id_om_usuario";
          $resNivel = $conexao->query($sqlNivel);
          $nivelUsuario = $resNivel->fetch_assoc()['nivel'] ?? 0;

          if ($nivelUsuario == 1) {
              $sqlBatalhoes = "SELECT id, nome, abreviatura FROM organizacoes_militares ORDER BY nome";
          } else {
              $sqlBatalhoes = "
                  SELECT om.id, om.nome, om.abreviatura
                  FROM organizacoes_militares om
                  WHERE om.id = $id_om_usuario
                  OR om.id IN (
                      SELECT id_om_menor FROM organizacoes_militares_sub WHERE id_om_maior = $id_om_usuario
                  )
                  ORDER BY om.nome
              ";
          }
          $resBatalhoes = $conexao->query($sqlBatalhoes);
          ?>

          <!-- SELECIONAR BATALHÃO -->
          <label class="form-label">Selecione o Batalhão das Viaturas</label>
          <select name="batalhao" id="batalhao_importacao" class="form-select mb-3" required>
            <option value="">Selecione...</option>
            <?php while ($bat = $resBatalhoes->fetch_assoc()): ?>
              <option value="<?= $bat['id'] ?>">
                <?= htmlspecialchars($bat['abreviatura'] . ' - ' . $bat['nome']) ?>
              </option>
            <?php endwhile; ?>
          </select>

          <label class="form-label">Selecione a planilha (.xlsx)</label>
          <input type="file" name="arquivo" id="arquivo" accept=".xlsx" class="form-control mb-3" required>

          <a href="includes/frota/Cadastrar_Frota.xlsx" class="btn btn-link p-0">
            📥 Baixar modelo de planilha
          </a>
        </div>
        <div class="modal-footer">
         <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
          <button type="submit" class="btn btn-primary">Importar</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
<script>
    window.funcaoInicializacao = 'inicializarCadastroVtrEqp';
</script>
<script>
function previewImagemCapa(event) {
  const preview = document.getElementById('previewFotoCapa');
  preview.src = URL.createObjectURL(event.target.files[0]);
}

document.getElementById('marca').addEventListener('change', function() {
  const marcaId = this.value;
  const modeloSelect = document.getElementById('modelo');
  modeloSelect.innerHTML = '<option value="">Carregando...</option>';

  if (!marcaId) {
    modeloSelect.innerHTML = '<option value="">Selecione uma marca primeiro</option>';
    return;
  }

  fetch('includes/frota/buscar_modelos_cadastro.php?marca_id=' + marcaId)
    .then(res => res.json())
    .then(data => {
      modeloSelect.innerHTML = '<option value="">Selecione...</option>';
      data.forEach(modelo => {
        const option = document.createElement('option');
        option.value = modelo.id;
        option.textContent = modelo.nome_modelo;
        modeloSelect.appendChild(option);
      });
    })
    .catch(() => modeloSelect.innerHTML = '<option value="">Erro ao carregar modelos</option>');
});
</script>
<script>
$('.select2').select2({
    placeholder: "Selecione o destino",
    width: '100%'
});
</script>
