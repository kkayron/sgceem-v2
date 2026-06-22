import { useState, useEffect } from 'react';
import Swal from 'sweetalert2';

export default function Login({ onLoginSuccess }) {
  const [isLoginTab, setIsLoginTab] = useState(true);
  
  // Login State
  const [usuario, setUsuario] = useState('');
  const [senha, setSenha] = useState('');
  
  // Cadastro State
  const [nomeguerra, setNomeguerra] = useState('');
  const [postograd, setPostograd] = useState('');
  const [roleId, setRoleId] = useState('');
  const [senhaCadastro, setSenhaCadastro] = useState('');
  const [roles, setRoles] = useState([]);
  
  const [erro, setErro] = useState('');
  const [sucesso, setSucesso] = useState('');
  const [loading, setLoading] = useState(false);

  const postos = [
    'Sd', 'Cb', '3º Sgt', '2º Sgt', '1º Sgt', 'S Ten',
    'Asp Of', '2º Ten', '1º Ten', 'Cap', 'Maj', 'Ten Cel', 'Cel', 'Gen', 'Civil'
  ];

  useEffect(() => {
    // Carregar funções se estiver na aba de cadastro
    if (!isLoginTab && roles.length === 0) {
      fetch('/api/v1/auth/roles')
        .then(res => res.json())
        .then(data => {
          if (data.status === 'sucesso') {
            setRoles(data.dados);
          }
        })
        .catch(err => console.log(err));
    }
  }, [isLoginTab]);

  const handleLogin = async (e) => {
    e.preventDefault();
    setErro('');
    setSucesso('');
    setLoading(true);

    try {
      const response = await fetch('/api/v1/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ usuario, senha })
      });

      const data = await response.json();

      if (response.ok && data.status === 'sucesso') {
        localStorage.setItem('sgceem_token', data.token);
        localStorage.setItem('sgceem_user', JSON.stringify(data.usuario));
        
        // Saudação dinâmica baseada no horário
        const hora = new Date().getHours();
        let saudacao = 'Boa noite';
        if (hora >= 5 && hora < 12) saudacao = 'Bom dia';
        else if (hora >= 12 && hora < 18) saudacao = 'Boa tarde';
        
        const u = data.usuario;
        const patente = u.postograd ? `${u.postograd} ` : '';
        const nome = u.nomeguerra || u.nomecompleto;

        Swal.fire({
          title: `${saudacao}, ${patente}${nome}!`,
          text: 'Acesso Concedido.',
          icon: 'success',
          timer: 2500,
          showConfirmButton: false,
          background: '#0a0a0a',
          color: '#fff',
          iconColor: '#00ff00'
        }).then(() => {
          onLoginSuccess();
        });
        
      } else {
        setErro(data.mensagem || 'Falha na autenticação.');
      }
    } catch (err) {
      setErro('Erro de conexão com o servidor militar.');
    } finally {
      setLoading(false);
    }
  };

  const handleCadastro = async (e) => {
    e.preventDefault();
    setErro('');
    setSucesso('');
    setLoading(true);

    try {
      const response = await fetch('/api/v1/auth/usuarios', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
          nomeguerra, 
          postograd, 
          role_id: roleId, 
          senha: senhaCadastro 
        })
      });

      const data = await response.json();

      if (response.ok && data.status === 'sucesso') {
        setSucesso(`Conta criada! Seu login é: ${data.usuario_gerado}`);
        setNomeguerra('');
        setPostograd('');
        setRoleId('');
        setSenhaCadastro('');
        setTimeout(() => setIsLoginTab(true), 4000);
      } else {
        setErro(data.mensagem || 'Erro ao cadastrar.');
      }
    } catch (err) {
      setErro('Erro de conexão com o servidor.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{
      minHeight: '100vh',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      background: 'linear-gradient(135deg, #0d6efd 0%, #004aad 100%)',
      fontFamily: 'system-ui, sans-serif'
    }}>
      <div style={{
        background: 'rgba(255, 255, 255, 0.1)',
        backdropFilter: 'blur(15px)',
        border: '1px solid rgba(255, 255, 255, 0.2)',
        borderRadius: '16px',
        padding: '30px 40px',
        width: '100%',
        maxWidth: '450px',
        boxShadow: '0 25px 50px rgba(0,0,0,0.2)',
        color: 'white',
        textAlign: 'center'
      }}>
        <h2 style={{ marginBottom: '5px', fontWeight: 'bold' }}>SGCEEM v2.0</h2>
        <p style={{ opacity: 0.8, marginBottom: '20px' }}>Sistema de Gestão de Empenho</p>

        <div style={{ display: 'flex', gap: '10px', marginBottom: '25px' }}>
          <button 
            onClick={() => { setIsLoginTab(true); setErro(''); setSucesso(''); }}
            style={{
              flex: 1, padding: '10px', borderRadius: '8px',
              background: isLoginTab ? '#fff' : 'transparent',
              color: isLoginTab ? '#0d6efd' : '#fff',
              fontWeight: 'bold', transition: 'all 0.3s',
              border: !isLoginTab ? '1px solid rgba(255,255,255,0.3)' : 'none'
            }}
          >
            Entrar
          </button>
          <button 
            onClick={() => { setIsLoginTab(false); setErro(''); setSucesso(''); }}
            style={{
              flex: 1, padding: '10px', borderRadius: '8px',
              background: !isLoginTab ? '#fff' : 'transparent',
              color: !isLoginTab ? '#0d6efd' : '#fff',
              fontWeight: 'bold', transition: 'all 0.3s',
              border: isLoginTab ? '1px solid rgba(255,255,255,0.3)' : 'none'
            }}
          >
            Cadastrar
          </button>
        </div>

        {erro && (
          <div style={{ background: 'rgba(220, 53, 69, 0.2)', borderLeft: '4px solid #dc3545', padding: '10px', borderRadius: '4px', marginBottom: '20px', fontSize: '14px' }}>
            {erro}
          </div>
        )}

        {sucesso && (
          <div style={{ background: 'rgba(25, 135, 84, 0.2)', borderLeft: '4px solid #198754', padding: '10px', borderRadius: '4px', marginBottom: '20px', fontSize: '14px' }}>
            {sucesso}
          </div>
        )}

        {isLoginTab ? (
          <form onSubmit={handleLogin} style={{ display: 'flex', flexDirection: 'column', gap: '15px' }}>
            <input 
              type="text" 
              placeholder="Usuário (Ex: felipealves)" 
              value={usuario}
              onChange={(e) => setUsuario(e.target.value)}
              required
              style={{ width: '100%', padding: '12px', borderRadius: '8px', border: 'none', background: 'rgba(255,255,255,0.9)', outline: 'none', boxSizing: 'border-box' }}
            />
            <input 
              type="password" 
              placeholder="Senha" 
              value={senha}
              onChange={(e) => setSenha(e.target.value)}
              required
              style={{ width: '100%', padding: '12px', borderRadius: '8px', border: 'none', background: 'rgba(255,255,255,0.9)', outline: 'none', boxSizing: 'border-box' }}
            />
            <button 
              type="submit" 
              disabled={loading}
              style={{ marginTop: '10px', width: '100%', padding: '12px', borderRadius: '8px', border: 'none', background: '#ffc107', color: '#000', fontWeight: 'bold', fontSize: '16px', cursor: loading ? 'not-allowed' : 'pointer' }}
            >
              {loading ? 'Autenticando...' : 'Acessar Sistema'}
            </button>
          </form>
        ) : (
          <form onSubmit={handleCadastro} style={{ display: 'flex', flexDirection: 'column', gap: '15px' }}>
            <div style={{ display: 'flex', gap: '10px' }}>
              <select 
                value={postograd}
                onChange={(e) => setPostograd(e.target.value)}
                required
                style={{ flex: 1, padding: '12px', borderRadius: '8px', border: 'none', background: 'rgba(255,255,255,0.9)', outline: 'none' }}
              >
                <option value="">Posto/Grad</option>
                {postos.map(p => <option key={p} value={p}>{p}</option>)}
              </select>
              
              <input 
                type="text" 
                placeholder="Nome de Guerra" 
                value={nomeguerra}
                onChange={(e) => setNomeguerra(e.target.value.toUpperCase())}
                required
                style={{ flex: 2, padding: '12px', borderRadius: '8px', border: 'none', background: 'rgba(255,255,255,0.9)', outline: 'none', boxSizing: 'border-box' }}
              />
            </div>
            
            <select 
              value={roleId}
              onChange={(e) => setRoleId(e.target.value)}
              required
              style={{ width: '100%', padding: '12px', borderRadius: '8px', border: 'none', background: 'rgba(255,255,255,0.9)', outline: 'none', boxSizing: 'border-box' }}
            >
              <option value="">Selecione sua Função...</option>
              {roles.map(r => <option key={r.id} value={r.id}>{r.name}</option>)}
            </select>

            <input 
              type="password" 
              placeholder="Crie sua Senha" 
              value={senhaCadastro}
              onChange={(e) => setSenhaCadastro(e.target.value)}
              required
              style={{ width: '100%', padding: '12px', borderRadius: '8px', border: 'none', background: 'rgba(255,255,255,0.9)', outline: 'none', boxSizing: 'border-box' }}
            />
            
            <button 
              type="submit" 
              disabled={loading}
              style={{ marginTop: '10px', width: '100%', padding: '12px', borderRadius: '8px', border: 'none', background: '#ffc107', color: '#000', fontWeight: 'bold', fontSize: '16px', cursor: loading ? 'not-allowed' : 'pointer' }}
            >
              {loading ? 'Cadastrando...' : 'Criar Conta'}
            </button>
          </form>
        )}

        <div style={{ marginTop: '20px', fontSize: '12px', opacity: 0.6 }}>
          Acesso Restrito Militar
        </div>
      </div>
    </div>
  );
}
