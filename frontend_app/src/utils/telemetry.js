let currentVersion = null;
let intervalId = null;

export const startTelemetry = () => {
    if (intervalId) return;
    
    intervalId = setInterval(async () => {
        try {
            const token = localStorage.getItem('sgceem_token');
            if (!token) return;

            const res = await fetch('/api/v1/sys/version', {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            const data = await res.json();

            if (data.status === 'sucesso') {
                if (currentVersion === null) {
                    currentVersion = data.version;
                } else if (data.version !== currentVersion) {
                    currentVersion = data.version;
                    // Dispara o evento global de atualização
                    window.dispatchEvent(new Event('db_updated'));
                }
            }
        } catch (e) {
            console.error('Falha na telemetria', e);
        }
    }, 3000); // Polling a cada 3 segundos
};

export const stopTelemetry = () => {
    if (intervalId) {
        clearInterval(intervalId);
        intervalId = null;
    }
};
