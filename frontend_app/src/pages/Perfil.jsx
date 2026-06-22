import { useState, useRef } from 'react';
import { motion } from 'framer-motion';
import Swal from 'sweetalert2';
import { User, Camera, Shield, Save, Lock } from 'lucide-react';

export default function Perfil({ userData }) {
  const [form, setForm] = useState({
    postograd: userData?.postograd || '',
    nomeguerra: userData?.nomeguerra || '',
    nomecompleto: userData?.nomecompleto || '',
    senha: ''
  });
  
  const [fotoFile, setFotoFile] = useState(null);
  const [previewUrl, setPreviewUrl] = useState(userData?.foto ? `/assets/fotoperfil/${userData.foto}` : `/assets/fotoperfil/default.png`);
  const [saving, setSaving] = useState(false);
  const fileInputRef = useRef(null);

  const postos = ['Gen','Cel','TC','Maj','Capitão','1º Ten','2º Ten','Asp Of','Sten','1º Sgt','2º Sgt','3º Sgt','Cb','Sd'];

  const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      setFotoFile(file);
      setPreviewUrl(URL.createObjectURL(file));
    }
  };

  const handleSave = async () => {
    if (!form.nomeguerra || !form.postograd) {
      return Swal.fire('Atenção', 'Preencha o Posto/Graduação e o Nome de Guerra.', 'warning');
    }

    setSaving(true);
    const formData = new FormData();
    formData.append('postograd', form.postograd);
    formData.append('nomeguerra', form.nomeguerra);
    formData.append('nomecompleto', form.nomecompleto);
    if (form.senha) formData.append('senha', form.senha);
    if (fotoFile) formData.append('foto', fotoFile);

    try {
      const res = await fetch('/api/v1/me/perfil', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('sgceem_token')}`
        },
        body: formData
      });
      const data = await res.json();
      
      if (data.status === 'sucesso') {
        Swal.fire({ icon: 'success', title: 'Perfil Atualizado', text: 'Suas informações foram salvas com sucesso!', timer: 2000, showConfirmButton: false });
        
        if (data.usuario) {
          localStorage.setItem('sgceem_user', JSON.stringify(data.usuario));
          window.dispatchEvent(new CustomEvent('db_updated'));
          setTimeout(() => window.location.reload(), 1500); 
        }
      } else {
        Swal.fire('Erro', data.mensagem || 'Falha ao salvar', 'error');
      }
    } catch (err) {
      Swal.fire('Erro', 'Falha na comunicação com o servidor.', 'error');
    } finally {
      setSaving(false);
    }
  };

  return (
    <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="page-inner">
      <div className="d-flex align-items-center mb-4 pt-3">
        <User size={32} className="me-3 text-primary" />
        <div>
          <h3 className="fw-bold mb-0">Meu Perfil</h3>
          <p className="mb-0 text-muted">Gerencie suas informações pessoais e credenciais de acesso</p>
        </div>
      </div>

      <div className="row">
        {/* Coluna da Foto */}
        <div className="col-md-4 mb-4">
          <div className="card shadow-sm border-0 rounded-4 h-100">
            <div className="card-body text-center p-4 d-flex flex-column align-items-center justify-content-center">
              <div className="position-relative mb-4">
                <img 
                  src={previewUrl} 
                  alt="Avatar" 
                  className="rounded-circle shadow" 
                  style={{ width: '180px', height: '180px', objectFit: 'cover', border: '5px solid #fff' }}
                />
                <button 
                  className="btn btn-primary rounded-circle position-absolute shadow" 
                  style={{ bottom: '5px', right: '15px', width: '45px', height: '45px', padding: 0 }}
                  onClick={() => fileInputRef.current.click()}
                  title="Trocar Foto"
                >
                  <Camera size={20} />
                </button>
                <input 
                  type="file" 
                  accept="image/png, image/jpeg, image/jpg" 
                  className="d-none" 
                  ref={fileInputRef} 
                  onChange={handleFileChange} 
                />
              </div>
              <h5 className="fw-bold mb-1">{form.postograd} {form.nomeguerra}</h5>
              <span className="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-medium">
                {userData?.role_name || 'Usuário'}
              </span>
              <p className="text-muted mt-3 small px-3">
                Formatos suportados: JPG, PNG. Tamanho recomendado: 500x500px.
              </p>
            </div>
          </div>
        </div>

        {/* Coluna dos Dados */}
        <div className="col-md-8 mb-4">
          <div className="card shadow-sm border-0 rounded-4">
            <div className="card-header bg-white border-0 pt-4 pb-2">
              <h5 className="fw-bold mb-0 d-flex align-items-center">
                <Shield size={20} className="me-2 text-primary" /> Informações do Usuário
              </h5>
            </div>
            <div className="card-body p-4">
              <div className="row g-3">
                <div className="col-md-4">
                  <label className="form-label fw-bold">Posto / Graduação</label>
                  <select 
                    className="form-select bg-light border-0" 
                    value={form.postograd} 
                    onChange={e => setForm({...form, postograd: e.target.value})}
                  >
                    <option value="">Selecione...</option>
                    {postos.map(p => <option key={p} value={p}>{p}</option>)}
                  </select>
                </div>
                <div className="col-md-8">
                  <label className="form-label fw-bold">Nome de Guerra</label>
                  <input 
                    type="text" 
                    className="form-control bg-light border-0" 
                    value={form.nomeguerra} 
                    onChange={e => setForm({...form, nomeguerra: e.target.value})} 
                  />
                </div>
                <div className="col-md-12">
                  <label className="form-label fw-bold">Nome Completo</label>
                  <input 
                    type="text" 
                    className="form-control bg-light border-0" 
                    value={form.nomecompleto} 
                    onChange={e => setForm({...form, nomecompleto: e.target.value})} 
                  />
                </div>
              </div>

              <hr className="my-4 border-light" />

              <h5 className="fw-bold mb-3 d-flex align-items-center">
                <Lock size={20} className="me-2 text-danger" /> Segurança
              </h5>
              <div className="row g-3">
                <div className="col-md-12">
                  <label className="form-label fw-bold">Nova Senha</label>
                  <input 
                    type="password" 
                    className="form-control bg-light border-0" 
                    placeholder="Deixe em branco para manter a senha atual"
                    value={form.senha} 
                    onChange={e => setForm({...form, senha: e.target.value})} 
                  />
                  <small className="text-muted mt-1 d-block">Caso não queira alterar, não preencha este campo.</small>
                </div>
              </div>

              <div className="text-end mt-4 pt-3 border-top">
                <button 
                  className="btn btn-primary px-4 py-2 rounded-3 fw-bold shadow-sm d-flex align-items-center ms-auto" 
                  onClick={handleSave}
                  disabled={saving}
                >
                  {saving ? <div className="spinner-border spinner-border-sm me-2"></div> : <Save size={18} className="me-2" />}
                  {saving ? 'Salvando...' : 'Salvar Alterações'}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </motion.div>
  );
}
