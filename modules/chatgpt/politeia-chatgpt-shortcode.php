<?php
/**
 * Politeia ChatGPT Shortcode + Enqueue
 * - Render del UI (input + mic + imagen)
 * - Enqueue del JS y variables globales
 * - SIN handlers AJAX aquí (evita colisiones)
 */

if ( ! defined('ABSPATH') ) exit;

/** ========= Enqueue del JS ========= */
function politeia_chatgpt_enqueue_scripts() {
	if ( is_admin() ) return;

	wp_enqueue_script(
		'politeia-chatgpt-scripts',
		plugin_dir_url(__FILE__) . 'js/politeia-chatgpt-scripts.js',
		[],
		'3.1',
		true
	);

	wp_localize_script(
		'politeia-chatgpt-scripts',
		'politeia_chatgpt_vars',
		[
			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce'   => wp_create_nonce('politeia-chatgpt-nonce'),
		]
	);
}
add_action('wp_enqueue_scripts', 'politeia_chatgpt_enqueue_scripts');


/** ========= Shortcode ========= */
function politeia_chatgpt_shortcode_callback() {
	// Requiere token para operar
	if ( empty( get_option('politeia_chatgpt_api_token') ) ) {
		return '<p>Error: La funcionalidad de IA no está configurada. Añade un token de API en PoliteiaGPT → General.</p>';
	}

	ob_start();
	?>
	<style>
                .politeia-chat-container { max-width: 980px; margin: 20px auto; font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; }
                .politeia-chat-input-bar { display:flex; align-items:center; gap:6px; padding:8px; border:1px solid #e0e0e0; border-radius:999px; background:#fff; box-shadow:0 4px 10px rgba(0,0,0,.07); }
                #politeia-chat-prompt { flex-grow:1; border:none; outline:none; background:transparent; font-size:16px; padding:8px; resize:none; line-height:1.5; }
                .politeia-icon-button { background:transparent; border:none; cursor:pointer; padding:8px; display:inline-flex; align-items:center; justify-content:center; color:#555; border-radius:50%; }
                .politeia-icon-button:hover { background-color:#f0f0f0; }
                #politeia-chat-status { margin-top:10px; text-align:center; color:#333; min-height:1.2em; }
                .politeia-chat-confirm { margin-top:24px; }
        </style>

        <div class="politeia-chat-container">
                <div class="politeia-chat-input-bar">
			<input type="file" id="politeia-file-upload" accept="image/*" style="display:none;" />
			<label for="politeia-file-upload" class="politeia-icon-button" title="Subir imagen de tus libros">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
			</label>

			<textarea id="politeia-chat-prompt" placeholder="Describe tus libros, graba tu voz o sube una foto..." rows="1"></textarea>

			<button class="politeia-icon-button" id="politeia-mic-btn" title="Grabar tu voz narrando los libros">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="23"></line><line x1="8" y1="23" x2="16" y2="23"></line></svg>
			</button>

			<button class="politeia-icon-button" id="politeia-submit-btn" title="Enviar texto">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
			</button>
		</div>

                <div id="politeia-chat-status"></div>

                <?php
                $politeia_confirm_markup = do_shortcode('[politeia_confirm_table]');
                if ( trim($politeia_confirm_markup) !== '' ) :
                ?>
                <div class="politeia-chat-confirm">
                        <?php echo $politeia_confirm_markup; ?>
                </div>
                <?php endif; ?>
        </div>

	<script>
	// Escucha global para refrescar la tabla de confirmación (efímeros + pending)
	window.addEventListener('politeia:queue-updated', () => { try { location.reload(); } catch(_) {} });
	</script>
	<?php
	return ob_get_clean();
}
add_shortcode('politeia_chatgpt_input', 'politeia_chatgpt_shortcode_callback');
