/**
 * Simple Toast Plugin for Vue 3
 * Provides $toast.success(), $toast.error(), $toast.warning(), $toast.info()
 */

const ToastPlugin = {
  install(app) {
    const toast = {
      success(message) {
        this._show(message, 'success');
      },
      error(message) {
        this._show(message, 'error');
      },
      warning(message) {
        this._show(message, 'warning');
      },
      info(message) {
        this._show(message, 'info');
      },
      _show(message, type = 'info') {
        // Remove existing toast container if any
        let container = document.getElementById('toast-container');
        if (!container) {
          container = document.createElement('div');
          container.id = 'toast-container';
          container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 8px;
          `;
          document.body.appendChild(container);
        }

        const colors = {
          success: '#10b981',
          error: '#ef4444',
          warning: '#f59e0b',
          info: '#3b82f6',
        };

        const toastEl = document.createElement('div');
        toastEl.style.cssText = `
          padding: 12px 20px;
          border-radius: 8px;
          color: #fff;
          background: ${colors[type] || colors.info};
          box-shadow: 0 4px 12px rgba(0,0,0,0.15);
          font-size: 14px;
          font-weight: 500;
          min-width: 250px;
          max-width: 400px;
          animation: slideIn 0.3s ease;
          display: flex;
          align-items: center;
          gap: 8px;
        `;
        toastEl.textContent = message;
        container.appendChild(toastEl);

        // Auto remove after 3 seconds
        setTimeout(() => {
          toastEl.style.animation = 'slideOut 0.3s ease';
          setTimeout(() => {
            toastEl.remove();
            if (container.children.length === 0) {
              container.remove();
            }
          }, 300);
        }, 3000);
      },
    };

    app.config.globalProperties.$toast = toast;
  },
};

export default ToastPlugin;
