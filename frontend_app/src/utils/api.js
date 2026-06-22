import Swal from 'sweetalert2';

export const API_BASE = '/api/v1';

export const getAuthToken = () => localStorage.getItem('sgceem_token');

export const getHeaders = () => ({
  'Authorization': `Bearer ${getAuthToken()}`,
  'Content-Type': 'application/json'
});

// Telemetria Global de Sincronização
const syncChannel = new BroadcastChannel('sgceem_sync_channel');
syncChannel.onmessage = (event) => {
  if (event.data === 'db_updated') {
    window.dispatchEvent(new CustomEvent('db_updated'));
  }
};

export const handleUnauthorized = () => {
  localStorage.removeItem('sgceem_token');
  localStorage.removeItem('sgceem_user');
  
  Swal.fire({
    title: 'Sessão Expirada',
    text: 'Sua sessão expirou por segurança. Por favor, faça login novamente.',
    icon: 'warning',
    confirmButtonText: 'OK',
    allowOutsideClick: false
  }).then(() => {
    window.location.href = '/';
  });
};

export const performLogout = async () => {
  try {
    await fetch(`${API_BASE}/auth/logout`, { method: 'POST', headers: getHeaders() });
  } catch(e) {}
  localStorage.removeItem('sgceem_token');
  localStorage.removeItem('sgceem_user');
  window.location.href = '/';
};

export const apiFetch = async (endpoint, options = {}) => {
  const url = `${API_BASE}/${endpoint.replace(/^\//, '')}`;
  
  const defaultOptions = {
    headers: getHeaders(),
  };

  const finalOptions = { ...defaultOptions, ...options };

  try {
    const response = await fetch(url, finalOptions);

    if (response.status === 401) {
      handleUnauthorized();
      throw new Error('Sessão expirada');
    }

    const data = await response.json();
    
    // Fallback if the API returns 200 but explicitly says token is invalid
    if (data.status === 'erro' && data.mensagem?.toLowerCase().includes('token')) {
      handleUnauthorized();
      throw new Error('Sessão expirada');
    }

    // Telemetria: Avisar todo o sistema se houve alteração (POST, PUT, DELETE)
    const method = finalOptions.method || 'GET';
    if (['POST', 'PUT', 'DELETE'].includes(method) && data.status === 'sucesso') {
      window.dispatchEvent(new CustomEvent('db_updated'));
      syncChannel.postMessage('db_updated');
    }

    return data;
  } catch (error) {
    if (error.message !== 'Sessão expirada') {
      if (import.meta.env && import.meta.env.DEV) {
        console.error('API Fetch Error:', error); // Auditoria B1
      }
    }
    throw error;
  }
};
