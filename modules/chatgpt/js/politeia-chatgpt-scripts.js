/**
 * politeia-chatgpt-scripts.js
 * v3.1 — Entrada de texto/audio/imagen.
 * - No renderiza tabla inline.
 * - Tras encolar candidatos, dispara `politeia:queue-updated` para que el shortcode de confirmación se refresque.
 */
(function () {
  if (typeof window.politeia_chatgpt_vars === 'undefined') {
    console.warn('[Politeia ChatGPT] Missing politeia_chatgpt_vars. Did you call wp_localize_script()?');
    return;
  }
  const AJAX  = String(window.politeia_chatgpt_vars.ajaxurl || '');
  const NONCE = String(window.politeia_chatgpt_vars.nonce  || '');

  const txt       = document.getElementById('politeia-chat-prompt');
  const btnSend   = document.getElementById('politeia-submit-btn');
  const btnMic    = document.getElementById('politeia-mic-btn');
  const fileInput = document.getElementById('politeia-file-upload');
  const statusEl  = document.getElementById('politeia-chat-status');

  if (!txt || !btnSend || !btnMic || !fileInput || !statusEl) return;

  let busy = false;
  function setStatus(msg) { statusEl.textContent = msg || ''; }
  function setBusy(on) {
    busy = !!on;
    [btnSend, btnMic, fileInput, txt].forEach(el => { if (el) el.disabled = busy; });
    [btnSend, btnMic].forEach(el => { if (el) el.style.opacity = busy ? '0.6' : '1'; });
  }

  async function postFD(fd) {
    const res = await fetch(AJAX, { method: 'POST', body: fd });
    try { return await res.clone().json(); }
    catch (_e) { return { success:false, data: await res.text() }; }
  }

  function notifyQueueUpdated(count){
    try {
      window.dispatchEvent(new CustomEvent('politeia:queue-updated', {
        detail: { count: Number(count || 0) }
      }));
    } catch(_) {}
  }

  function safeParseJSON(s) {
    if (!s) return null;
    if (typeof s !== 'string') return s;
    const t = s.replace(/^```json\s*|\s*```$/g, '');
    try { return JSON.parse(t); } catch { return null; }
  }

  // Cuenta resultados considerando pending + in_shelf
  function countItemsFromResponse(payload) {
    if (!payload || !payload.data) return 0;
    const d = payload.data;

    if (typeof d.queued_count === 'number') return d.queued_count;
    if (typeof d.queued       === 'number') return d.queued;

    // arrays comunes
    let total = 0;
    if (Array.isArray(d.pending))  total += d.pending.length;
    if (Array.isArray(d.in_shelf)) total += d.in_shelf.length;
    if (Array.isArray(d.items))    total += d.items.length;       // por compatibilidad
    if (Array.isArray(d.candidates)) total += d.candidates.length;

    if (total > 0) return total;

    // fallback a raw_response con JSON {books:[...]} o [...]
    const raw = d.raw_response;
    const parsed = safeParseJSON(raw);
    if (Array.isArray(parsed)) return parsed.length;
    if (parsed && Array.isArray(parsed.books)) return parsed.books.length;

    return 0;
  }

  // ======================= TEXTO =======================
  async function sendText(){
    const prompt = (txt.value || '').trim();
    if (!prompt || busy) return;

    setBusy(true);
    setStatus('Procesando texto…');

    const fd = new FormData();
    fd.append('action','politeia_process_input');
    fd.append('nonce', NONCE);
    fd.append('type','text');
    fd.append('prompt', prompt);

    try{
      const resp = await postFD(fd);
      if (resp && resp.success){
        const n = countItemsFromResponse(resp);
        if (n > 0){
          setStatus(`Listo. Candidatos encolados: ${n}`);
          notifyQueueUpdated(n);
        } else {
          setStatus('Listo. Resultados actualizados.');
          notifyQueueUpdated(0);
        }
      } else {
        setStatus('Error al procesar el texto.');
        console.warn('[Politeia ChatGPT] text error:', resp);
      }
    } catch(e){
      setStatus('Error de red.');
      console.error(e);
    } finally {
      setBusy(false);
    }
  }

  btnSend.addEventListener('click', sendText);
  txt.addEventListener('keydown', (e)=>{ if (e.key==='Enter' && !e.shiftKey){ e.preventDefault(); sendText(); } });

  const baseTextHeight = (() => {
    try {
      const styles = window.getComputedStyle(txt);
      const lineHeight = parseFloat(styles.lineHeight) || 0;
      const paddingTop = parseFloat(styles.paddingTop) || 0;
      const paddingBottom = parseFloat(styles.paddingBottom) || 0;
      const borderTop = parseFloat(styles.borderTopWidth) || 0;
      const borderBottom = parseFloat(styles.borderBottomWidth) || 0;
      const rowsAttr = parseInt(txt.getAttribute('rows') || '1', 10);
      const rows = Number.isFinite(rowsAttr) && rowsAttr > 0 ? rowsAttr : 1;
      const min = lineHeight > 0 ? (lineHeight * rows) + paddingTop + paddingBottom + borderTop + borderBottom : 0;
      return Math.max(min, 44);
    } catch (_err) {
      return 44;
    }
  })();

  function autoResizeTextarea(){
    txt.style.height = 'auto';
    const scrollHeight = txt.scrollHeight;
    const target = Math.max(scrollHeight, baseTextHeight);
    txt.style.height = `${target}px`;
  }

  txt.addEventListener('input', autoResizeTextarea);
  autoResizeTextarea();

  // ======================= AUDIO (placeholder) =======================
  // Puedes implementar más adelante; por ahora mostramos aviso claro.
  btnMic.addEventListener('click', () => {
    setStatus('La grabación de audio aún no está habilitada.');
  });

  // ======================= IMAGEN =======================
  fileInput.addEventListener('change', async (e)=>{
    if (busy) return;
    const file = e.target.files && e.target.files[0]; if (!file) return;

    function toDataURL(file){
      return new Promise((resolve,reject)=>{
        const r = new FileReader();
        r.onload = ()=> resolve(r.result);
        r.onerror = reject;
        r.readAsDataURL(file);
      });
    }

    try{
      setBusy(true);
      setStatus('Analizando imagen…');
      const dataUrl = await toDataURL(file);

      const fd = new FormData();
      fd.append('action','politeia_process_input');
      fd.append('nonce', NONCE);
      fd.append('type','image');
      fd.append('image_data', dataUrl);

      const resp = await postFD(fd);
      if (resp && resp.success){
        const n = countItemsFromResponse(resp);
        if (n > 0){
          setStatus(`Listo. Candidatos encolados: ${n}`);
          notifyQueueUpdated(n);
        } else {
          setStatus('Listo. Resultados actualizados.');
          notifyQueueUpdated(0);
        }
      } else {
        setStatus('Error al procesar la imagen.');
        console.warn('[Politeia ChatGPT] image error:', resp);
      }
    } catch(e){
      setStatus('Error al leer/enviar la imagen.');
      console.error(e);
    } finally {
      fileInput.value = '';
      setBusy(false);
    }
  });

})();
