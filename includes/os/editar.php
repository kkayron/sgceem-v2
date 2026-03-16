<div class="container my-3 border p-4 bg-white rounded shadow-sm" id="form-editar-os">
  <h4 class="text-center fw-bold">6º BEC</h4>

  <div class="row g-2 mb-3">
    <div class="col-md-2">
      <label class="form-label">OS nº</label>
      <input type="text" class="form-control" name="os_numero" readonly>
    </div>
    <div class="col-md-3">
      <label class="form-label">Data de Abertura</label>
      <input type="date" class="form-control" name="data_abertura">
    </div>
    <div class="col-md-4">
      <label class="form-label">Solicitante</label>
      <input type="text" class="form-control" name="solicitante">
    </div>
    <div class="col-md-3">
      <label class="form-label">Autorização do Serviço</label>
      <input type="text" class="form-control" name="autorizacao_servico">
    </div>
  </div>

  <div class="row g-2 mb-3">
    <div class="col-md-3">
      <label class="form-label">Prefixo EQP/VTR</label>
      <input type="text" class="form-control" name="prefixo">
    </div>
    <div class="col-md-2">
      <label class="form-label">ODO/HOR</label>
      <input type="number" class="form-control" name="odometro">
    </div>
    <div class="col-md-3">
      <label class="form-label">Tipo MNT</label>
      <select class="form-select" name="tipo_mnt">
        <option>Manutenção Corretiva</option>
        <option>Manutenção Preventiva</option>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Falhas Apresentadas/Serviço Solicitado</label>
      <textarea class="form-control" rows="1" name="falhas_solicitadas"></textarea>
    </div>
  </div>

  <hr class="my-3">

  <!-- 1. Falhas -->
  <h5 class="fw-bold">1. Falhas Identificadas Durante MNT</h5>
  <div id="falhasContainer" class="mb-2"></div>
  <button type="button" class="btn btn-info btn-sm mb-3" onclick="adicionarFalha()">Adicionar falha</button>

  <!-- 2. Pessoal -->
  <h5 class="fw-bold">2. Pessoal Utilizado na Manutenção</h5>
  <div id="pessoalContainer" class="mb-2"></div>
  <button type="button" class="btn btn-info btn-sm mb-3" onclick="adicionarPessoal()">Adicionar pessoal</button>

  <!-- 3. Serviços -->
  <h5 class="fw-bold">3. Serviços Realizados na Manutenção</h5>
  <div id="servicosContainer" class="mb-2"></div>
  <button type="button" class="btn btn-info btn-sm mb-3" onclick="adicionarServico()">Adicionar serviço</button>

  <!-- 4. Materiais -->
  <h5 class="fw-bold">4. Materiais/Peças Utilizados</h5>
  <div id="materiaisContainer" class="mb-2"></div>
  <button type="button" class="btn btn-info btn-sm mb-3" onclick="adicionarMaterial()">Adicionar material/peça</button>

  <hr>

  <!-- Preventiva -->
  <div class="form-check form-check-inline">
    <input class="form-check-input" type="checkbox" id="manutencaoPreventiva" name="manutencao_preventiva">
    <label class="form-check-label" for="manutencaoPreventiva">É um serviço de manutenção preventiva?</label>
  </div>

  <div id="dadosPreventiva" class="border p-3 mt-2 bg-light" style="display: none;">
    <div class="mb-2">
      <label class="form-label">Quilometragem ou horas para próxima manutenção preventiva</label>
      <select class="form-select" name="proxima_mnt">
        <option>5000km</option>
        <option>10000km</option>
        <option>2500km</option>
        <option>200hrs</option>
        <option>250hrs</option>
        <option>500hrs</option>
        <option>150hrs</option>
        <option>100hrs</option>
      </select>
    </div>
    <div>
      <label class="form-label">Quais foram as trocas realizadas e quantidade:</label>
      <textarea class="form-control" rows="2" name="trocas_realizadas"></textarea>
    </div>
  </div>

  <!-- Botões -->
  <div class="text-end mt-4">
    <button type="button" class="btn btn-success">Salvar alterações</button>
    <button type="button" class="btn btn-primary">Encerrar OS</button>
    <button type="button" class="btn btn-info">Imprimir OS</button>
  </div>
</div>
