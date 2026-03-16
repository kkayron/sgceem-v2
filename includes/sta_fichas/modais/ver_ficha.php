<!-- Modal de Ver Ficha STA -->
<div class="modal fade" id="modalVerFICHA" tabindex="-1" aria-labelledby="modalVerFICHA_Label" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Ver Ficha de Viatura/Equipamento</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body p-4 bg-light">

        <!-- Título (OM) -->
        <h4 class="text-center fw-bold text-dark mb-4" id="tituloOM"></h4>

        <!-- Dados da Viatura -->
        <div class="row g-3 bg-white p-3 rounded shadow-sm mb-4">
            
          <h5 class="fw-bold text-secondary">Dados da Viatura/Equipamento</h5>
              <!-- Foto da viatura -->
        <div class="text-center mb-4">
          <img id="fotoViaturaFicha" src="" alt="Foto da Viatura" class="img-thumbnail shadow-sm" style="max-width: 100%; max-height: 100px; object-fit: cover;">
        </div>
          <div class="col-md-3">
            <label class="form-label">Prefixo</label>
            <p class="form-control-plaintext" id="verf_prefixo"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Chassi</label>
            <p class="form-control-plaintext" id="verf_chassi"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Marca</label>
            <p class="form-control-plaintext" id="verf_marca"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Modelo</label>
            <p class="form-control-plaintext" id="verf_modelo"></p>
          </div>
        </div>

        <!-- Dados Principais -->
        <div class="row g-3 bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary">Dados da Ficha</h5>
          <div class="col-md-4">
            <label class="form-label">Organização Militar</label>
            <p class="form-control-plaintext" id="verf_om"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Data de Abertura</label>
            <p class="form-control-plaintext" id="verf_data_abertura"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Data Prevista para retorno</label>
            <p class="form-control-plaintext" id="verf_data_prevista"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <p class="form-control-plaintext" id="verf_status"></p>
          </div>
        </div>

        <!-- Dados da Missão -->
        <div class="row g-3 bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary">Dados da Missão</h5>
          <div class="col-md-3">
            <label class="form-label">Solicitante</label>
            <p class="form-control-plaintext" id="verf_solicitante"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Motorista</label>
            <p class="form-control-plaintext" id="verf_motorista"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Subunidade</label>
            <p class="form-control-plaintext" id="verf_subunidade"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Natureza</label>
            <p class="form-control-plaintext" id="verf_natureza"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Destino</label>
            <p class="form-control-plaintext" id="verf_destino"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Cidade/UF</label>
            <p class="form-control-plaintext" id="verf_cidade"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Chefe a se Apresentar</label>
            <p class="form-control-plaintext" id="verf_chefe_apresentar"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Local a se Apresentar</label>
            <p class="form-control-plaintext" id="verf_local_apresentar"></p>
          </div>
          <div class="col-md-4">
            <label class="form-label">Horário</label>
            <p class="form-control-plaintext" id="verf_horario_apresentar"></p>
          </div>
        </div>

        <!-- Dados Pós-Emprego -->
        <div class="row g-3 bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-danger">Pós-Emprego da Vtr/Eqp</h5>
          <div class="col-md-3">
            <label class="form-label">Data Saída</label>
            <p class="form-control-plaintext" id="verf_data_saida"></p>
          </div>
          <div class="col-md-2">
            <label class="form-label">Hora Saída</label>
            <p class="form-control-plaintext" id="verf_hora_saida"></p>
          </div>
          <div class="col-md-2">
            <label class="form-label">Odômetro Saída</label>
            <p class="form-control-plaintext" id="verf_odo_saida"></p>
          </div>
          <div class="col-md-3">
            <label class="form-label">Data Retorno</label>
            <p class="form-control-plaintext" id="verf_data_retorno"></p>
          </div>
          <div class="col-md-2">
            <label class="form-label">Hora Retorno</label>
            <p class="form-control-plaintext" id="verf_hora_retorno"></p>
          </div>
          <div class="col-md-2">
            <label class="form-label">Odômetro Retorno</label>
            <p class="form-control-plaintext" id="verf_odo_retorno"></p>
          </div>
          <div class="col-md-12">
            <label class="form-label">Observações Pós-Emprego</label>
            <p class="form-control-plaintext" id="verf_observacoes"></p>
          </div>
        </div>

        <!-- Logs -->
        <div class="bg-white p-3 rounded shadow-sm mb-4">
          <h5 class="fw-bold text-secondary">Histórico de Alterações</h5>
          <div id="verf_logsContainer">
            <p class="text-muted">Nenhum log disponível.</p>
          </div>
        </div>

        <!-- Botão -->
        <div class="text-end">
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>
</div>