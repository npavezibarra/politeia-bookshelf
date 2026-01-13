/* assets/chatgpt.js – robust uploader & response handling */
(function () {
    const $ = (sel, el) => (el || document).querySelector(sel);
  
    const AJAX  = (window.politeia_chatgpt_vars && window.politeia_chatgpt_vars.ajaxurl)
      ? window.politeia_chatgpt_vars.ajaxurl
      : (window.ajaxurl || '/wp-admin/admin-ajax.php');
    const NONCE = (window.politeia_chatgpt_vars && window.politeia_chatgpt_vars.nonce) || '';
  
    // ---- Status UI (message below the input) ----
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
  
    // ---- Fetch helpers (with text fallback) ----
    async function postFD(fd) {
      const res = await fetch(AJAX, { method: 'POST', body: fd });
      const rawText = await res.text(); // SIEMPRE leo texto primero
      let parsed = null;
      try {
        parsed = JSON.parse(rawText);
      } catch (_e) {
        // try to extract the first large {...} block
        const m = rawText && rawText.match(/\{[\s\S]*\}/);
        if (m) {
          try { parsed = JSON.parse(m[0]); } catch (_e2) {}
        }
      }
      return { parsed, rawText };
    }
  
    function coercePayload(obj) {
      // Accepts multiple shapes and returns {pending:[], in_shelf:[], message?, success?}
      let p = obj || {};
      let success = (typeof p.success === 'boolean') ? p.success : undefined;
  
      // Typical WP shape: {success:true|false, data:{...}}
      if (p && p.data && (typeof p.data === 'object')) {
        // sometimes comes as {success:false, data:{pending:[],in_shelf:[]}}
        if (Array.isArray(p.data.pending) || Array.isArray(p.data.in_shelf)) {
          p = p.data;
        } else if (p.data.data && (Array.isArray(p.data.data.pending) || Array.isArray(p.data.data.in_shelf))) {
          p = p.data.data;
        } else {
          // if it is {success:true, data:{queued,...}} use it anyway
          p = p.data;
        }
      }
  
      // If it flattened to {queued, pending, in_shelf}
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
                    document.querySelector('[aria-label="Upload an image of your books"]') ||
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
        setStatus('Analyzing image…', '');
        const { parsed, rawText } = await postFD(fd);
        console.debug('[politeia upload] raw:', rawText);
        console.debug('[politeia upload] parsed:', parsed);
  
        if (!parsed) {
          setStatus('Unknown server response (not JSON).', 'warn');
          return;
        }
  
        const { pending, in_shelf, message, success } = coercePayload(parsed);
        const total = pending.length + in_shelf.length;
  
        if (total > 0) {
          appendToTable(pending, in_shelf);
          setStatus(`Done. Queued candidates: ${total}`, 'ok');
        } else {
          if (message === 'upload_error') {
            setStatus('Error uploading the image. Check the allowed size.', 'err');
          } else if (message === 'openai_error') {
            setStatus('There was a problem contacting OpenAI.', 'err');
          } else if (message === 'no_books_detected' || success === false || success === true) {
            // Si success vino cualquiera pero no hay arrays, realmente no hubo libros
            setStatus('No books detected.', 'warn');
          } else {
            setStatus('No books detected (unrecognized response).', 'warn');
          }
        }
      } catch (e) {
        console.error('[politeia upload] exception', e);
        setStatus('Network error. Please try again.', 'err');
      } finally {
        hiddenFile.value = '';
      }
    });
  
    // ---- (Optional) free text submission ----
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
          setStatus('Processing…', '');
          const { parsed, rawText } = await postFD(fd);
          console.debug('[politeia text] raw:', rawText);
          console.debug('[politeia text] parsed:', parsed);
  
          if (!parsed) { setStatus('Unknown server response.', 'warn'); return; }
          const { pending, in_shelf, message, success } = coercePayload(parsed);
          const total = pending.length + in_shelf.length;
  
          if (total > 0) {
            appendToTable(pending, in_shelf);
            setStatus(`Done. Queued candidates: ${total}`, 'ok');
          } else {
            setStatus('No books detected.', 'warn');
          }
        } catch (e) {
          console.error(e);
          setStatus('Network error. Please try again.', 'err');
        }
      });
    }
  })();
  
