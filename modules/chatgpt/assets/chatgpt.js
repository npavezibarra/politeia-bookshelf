/* assets/chatgpt.js – robust uploader & response handling */
(function () {
    const $ = (sel, el) => (el || document).querySelector(sel);
  
    const AJAX  = (window.politeia_chatgpt_vars && window.politeia_chatgpt_vars.ajaxurl)
      ? window.politeia_chatgpt_vars.ajaxurl
      : (window.ajaxurl || '/wp-admin/admin-ajax.php');
    const NONCE = (window.politeia_chatgpt_vars && window.politeia_chatgpt_vars.nonce) || '';
  
    // ---- Status UI (mensaje bajo el input) ----
    const statusEl = (function ensureStatus(){
      let el = document.getElementById('pol-inline-status');
      if (!el) {
        el = document.createElement('div');
        el.id = 'pol-inline-status';
        el.style.margin = '10px 0';
        el.style.textAlign = 'center';
        const anchor = document.getElementById('pol-chatgpt-box') || document.querySelector('form') || document.body;
        anchor.parentNode.insertBefore(el, anchor.nextSibling);
      }
      return el;
    })();
  
    function setStatus(msg, tone) {
      if (!statusEl) return;
      statusEl.textContent = msg || '';
      statusEl.style.color =
        tone === 'ok'   ? '#166534' :
        tone === 'warn' ? '#92400e' :
        tone === 'err'  ? '#b91c1c' :
                          '#344055';
    }
  
    // ---- Fetch helpers (con fallback a texto) ----
    async function postFD(fd) {
      const res = await fetch(AJAX, { method: 'POST', body: fd });
      const rawText = await res.text(); // SIEMPRE leo texto primero
      let parsed = null;
      try {
        parsed = JSON.parse(rawText);
      } catch (_e) {
        // intenta extraer el primer bloque {...} grande
        const m = rawText && rawText.match(/\{[\s\S]*\}/);
        if (m) {
          try { parsed = JSON.parse(m[0]); } catch (_e2) {}
        }
      }
      return { parsed, rawText };
    }
  
    function coercePayload(obj) {
      // Acepta varias formas y devuelve {pending:[], in_shelf:[], message?, success?}
      let p = obj || {};
      let success = (typeof p.success === 'boolean') ? p.success : undefined;
  
      // Formas típicas de WP: {success:true|false, data:{...}}
      if (p && p.data && (typeof p.data === 'object')) {
        // a veces viene {success:false, data:{pending:[],in_shelf:[]}}
        if (Array.isArray(p.data.pending) || Array.isArray(p.data.in_shelf)) {
          p = p.data;
        } else if (p.data.data && (Array.isArray(p.data.data.pending) || Array.isArray(p.data.data.in_shelf))) {
          p = p.data.data;
        } else {
          // si es {success:true, data:{queued,...}} lo usamos igual
          p = p.data;
        }
      }
  
      // Si quedó plano {queued, pending, in_shelf}
      const pending = Array.isArray(p.pending) ? p.pending : [];
      const in_shelf = Array.isArray(p.in_shelf) ? p.in_shelf : [];
      const message = p.message || obj?.data?.message || obj?.message || null;
  
      return { pending, in_shelf, message, success };
    }
  
    function appendToTable(pending, in_shelf) {
      window.dispatchEvent(new CustomEvent('politeia:queue-append', {
        detail: { pending, in_shelf }
      }));
    }
  
    // ---- Input file trigger ----
    const trigger = document.querySelector('[data-pol-upload-trigger]') ||
                    document.querySelector('[aria-label="Subir imagen de tus libros"]') ||
                    document.querySelector('[data-testid="paperclip-button"]') || // por si hay algún ícono
                    null;
    const hiddenFile = document.createElement('input');
    hiddenFile.type = 'file';
    hiddenFile.accept = 'image/*';
    hiddenFile.style.display = 'none';
    document.body.appendChild(hiddenFile);
  
    if (trigger) {
      trigger.addEventListener('click', (e) => {
        e.preventDefault();
        hiddenFile.click();
      });
    }
  
    hiddenFile.addEventListener('change', async () => {
      if (!hiddenFile.files || !hiddenFile.files.length) return;
      const file = hiddenFile.files[0];
  
      const fd = new FormData();
      fd.append('action', 'politeia_chatgpt_upload');
      fd.append('nonce', NONCE);
      fd.append('input_type', 'image');
      fd.append('source_note', 'vision');
      fd.append('file', file);
  
      try {
        setStatus('Analizando imagen…', '');
        const { parsed, rawText } = await postFD(fd);
        console.debug('[politeia upload] raw:', rawText);
        console.debug('[politeia upload] parsed:', parsed);
  
        if (!parsed) {
          setStatus('Respuesta desconocida del servidor (no JSON).', 'warn');
          return;
        }
  
        const { pending, in_shelf, message, success } = coercePayload(parsed);
        const total = pending.length + in_shelf.length;
  
        if (total > 0) {
          appendToTable(pending, in_shelf);
          setStatus(`Listo. Candidatos encolados: ${total}`, 'ok');
        } else {
          if (message === 'upload_error') {
            setStatus('Error al subir la imagen. Revisa el tamaño permitido.', 'err');
          } else if (message === 'openai_error') {
            setStatus('Hubo un problema al contactar OpenAI.', 'err');
          } else if (message === 'no_books_detected' || success === false || success === true) {
            // Si success vino cualquiera pero no hay arrays, realmente no hubo libros
            setStatus('No se detectaron libros.', 'warn');
          } else {
            setStatus('No se detectaron libros (respuesta no reconocida).', 'warn');
          }
        }
      } catch (e) {
        console.error('[politeia upload] exception', e);
        setStatus('Error de red. Intenta nuevamente.', 'err');
      } finally {
        hiddenFile.value = '';
      }
    });
  
    // ---- (Opcional) envío de texto libre ----
    const textForm = document.getElementById('pol-chatgpt-text-form');
    if (textForm) {
      textForm.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const input = document.getElementById('pol-chatgpt-text-input');
        const val = (input && input.value || '').trim();
        if (!val) return;
  
        const fd = new FormData();
        fd.append('action', 'politeia_chatgpt_upload');
        fd.append('nonce', NONCE);
        fd.append('input_type', 'text');
        fd.append('text', val);
  
        try {
          setStatus('Procesando…', '');
          const { parsed, rawText } = await postFD(fd);
          console.debug('[politeia text] raw:', rawText);
          console.debug('[politeia text] parsed:', parsed);
  
          if (!parsed) { setStatus('Respuesta desconocida del servidor.', 'warn'); return; }
          const { pending, in_shelf, message, success } = coercePayload(parsed);
          const total = pending.length + in_shelf.length;
  
          if (total > 0) {
            appendToTable(pending, in_shelf);
            setStatus(`Listo. Candidatos encolados: ${total}`, 'ok');
          } else {
            setStatus('No se detectaron libros.', 'warn');
          }
        } catch (e) {
          console.error(e);
          setStatus('Error de red. Intenta nuevamente.', 'err');
        }
      });
    }
  })();
  