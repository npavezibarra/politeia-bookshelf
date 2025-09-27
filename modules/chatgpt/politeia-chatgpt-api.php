<?php
/**
 * Politeia ChatGPT – API helpers + AJAX
 *
 * - Envía prompts de texto y/o imagen a OpenAI y devuelve SIEMPRE JSON puro
 *   gracias a `response_format` con JSON Schema.
 * - Incluye un helper opcional `politeia_extract_json()`.
 * - Handlers AJAX:
 *     * politeia_process_input  (unificado: text / image / audio*)
 *     * politeia_chatgpt_upload (legacy, por compatibilidad)
 */

if ( ! defined('ABSPATH') ) exit;

/** ======================================================================
 *  Config / Tokens
 *  ====================================================================== */

/** Devuelve el token de OpenAI desde opciones. */
function politeia_chatgpt_get_api_token() {
	return (string) get_option('politeia_chatgpt_api_token', '');
}

/** ======================================================================
 *  Structured Output (JSON Schema)
 *  ====================================================================== */

/** JSON Schema reutilizable para forzar la salida: { "books": [ { "title", "authors": [] } ] } */
function politeia_chatgpt_books_schema() {
	return [
		'type'   => 'json_schema',
		'json_schema' => [
			'name'   => 'books_list',
			'strict' => true,
			'schema' => [
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => [ 'books' ],
				'properties'           => [
                                        'books' => [
                                                'type'  => 'array',
                                                'items' => [
                                                        'type'                 => 'object',
                                                        'additionalProperties' => false,
                                                        'required'             => [ 'title', 'authors' ],
                                                        'properties'           => [
                                                                'title'   => [ 'type' => 'string' ],
                                                                'authors' => [
                                                                        'type'     => 'array',
                                                                        'minItems' => 1,
                                                                        'items'    => [ 'type' => 'string' ],
                                                                ],
                                                                // Back-compat: allow legacy single-author payloads if provider sends both fields.
                                                                'author'  => [ 'type' => 'string' ],
                                                        ],
                                                ],
                                        ],
				],
			],
		],
	];
}

/** ======================================================================
 *  Utilidades
 *  ====================================================================== */

/** Extrae JSON desde un bloque con fences ```json ... ``` si existiera. */
function politeia_extract_json( $s ) {
	if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $s, $m)) {
		return trim($m[1]);
	}
	return trim($s, "\xEF\xBB\xBF \t\n\r\0\x0B");
}

/** Convierte un archivo subido en data URL base64. */
function politeia_file_to_data_url( $file ) {
	if ( empty($file['tmp_name']) || ! is_readable($file['tmp_name']) ) return null;
	$mime = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : ( $file['type'] ?? 'image/jpeg' );
	if ( ! $mime ) $mime = 'image/jpeg';
	$raw = file_get_contents($file['tmp_name']);
	if ( $raw === false ) return null;
	return 'data:' . $mime . ';base64,' . base64_encode($raw);
}

/** Normaliza la lista de libros {books:[...]} o array directo → [{title,authors[]}]. */
function politeia_normalize_books_array( $decoded ) {
        $out = [];
        $items = [];
        if ( is_array($decoded) && isset($decoded['books']) && is_array($decoded['books']) )      $items = $decoded['books'];
        elseif ( is_array($decoded) )                                                               $items = $decoded;

        foreach ( $items as $b ) {
                $t = isset($b['title'])  ? trim((string)$b['title'])  : '';
                $authors = [];

                if ( isset( $b['authors'] ) && is_array( $b['authors'] ) ) {
                        foreach ( $b['authors'] as $entry ) {
                                $entry = trim( (string) $entry );
                                if ( '' !== $entry ) {
                                        $authors[] = $entry;
                                }
                        }
                } elseif ( isset( $b['author'] ) ) {
                        $single = trim( (string) $b['author'] );
                        if ( '' !== $single ) {
                                $authors[] = $single;
                        }
                }

                $authors = array_values( array_unique( $authors ) );

                if ( $t !== '' && ! empty( $authors ) ) {
                        $out[] = [
                                'title'   => $t,
                                'authors' => $authors,
                                'author'  => implode( ', ', $authors ),
                        ];
                }
        }
        return $out;
}

/** ======================================================================
 *  OpenAI calls
 *  ====================================================================== */

/** POST a /v1/chat/completions y devuelve el content del primer choice (JSON string). */
function politeia_chatgpt_post_payload( array $payload ) {
	$api_token = politeia_chatgpt_get_api_token();
	if ( empty($api_token) ) return 'Error: No se ha configurado el token de API.';

	$api_url = 'https://api.openai.com/v1/chat/completions';
	$args = [
		'headers' => [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $api_token,
		],
		'body'    => wp_json_encode($payload),
		'method'  => 'POST',
		'timeout' => 90,
	];

	$response = wp_remote_post($api_url, $args);
	if ( is_wp_error($response) )  return 'Error al conectar con la API: ' . $response->get_error_message();

	$body = wp_remote_retrieve_body($response);
	$data = json_decode($body, true);

	if ( isset($data['error']) ) return 'Error de la API: ' . $data['error']['message'];

	if ( isset($data['choices'][0]['message']['content']) )
		return $data['choices'][0]['message']['content'];

	return 'No se pudo obtener una respuesta válida de la API.';
}

/** Envía PROMPT de texto: debe devolver JSON {books:[...]}. */
function politeia_chatgpt_send_query( $prompt ) {
	$messages = [ [ 'role' => 'user', 'content' => $prompt ] ];
	$payload  = [
		'model'           => 'gpt-4o',
		'messages'        => $messages,
		'temperature'     => 0,
		'max_tokens'      => 1500,
		'response_format' => politeia_chatgpt_books_schema(),
	];
	return politeia_chatgpt_post_payload($payload);
}

/** Envía IMAGEN (dataURL o URL) + instrucción: devuelve JSON {books:[...]}. */
function politeia_chatgpt_process_image( $base64_image, $instruction = '' ) {
	$prompt = $instruction ?: (
		"Analiza esta imagen de una estantería de libros. " .
                "Extrae los libros visibles y devuelve EXCLUSIVAMENTE un JSON con esta forma exacta:\n" .
                "{ \"books\": [ { \"title\": \"...\", \"authors\": [\"...\"] } ] }\n" .
                "No incluyas comentarios, ni markdown, ni texto adicional."
        );

	$messages = [[
		'role' => 'user',
		'content' => [
			[ 'type'=>'text',      'text' => $prompt ],
			[ 'type'=>'image_url', 'image_url' => [ 'url' => $base64_image ] ],
		],
	]];

	$payload = [
		'model'           => 'gpt-4o',
		'messages'        => $messages,
		'temperature'     => 0,
		'max_tokens'      => 2000,
		'response_format' => politeia_chatgpt_books_schema(),
	];

	return politeia_chatgpt_post_payload($payload);
}

/** ======================================================================
 *  AJAX: Upload (legacy) → mantiene compatibilidad con flujos antiguos
 *  ====================================================================== */

add_action('wp_ajax_politeia_chatgpt_upload', 'politeia_chatgpt_upload');
add_action('wp_ajax_nopriv_politeia_chatgpt_upload', 'politeia_chatgpt_upload');

function politeia_chatgpt_upload() {
	check_ajax_referer('politeia-chatgpt-nonce', 'nonce');

	$user_id = get_current_user_id();
	if ( ! $user_id ) wp_send_json_error([ 'message' => 'not_logged_in' ], 401);

	$input_type  = isset($_POST['input_type'])  ? sanitize_text_field(wp_unslash($_POST['input_type']))  : 'text';
	$source_note = isset($_POST['source_note']) ? sanitize_text_field(wp_unslash($_POST['source_note'])) : '';

	$candidates = [];
	$raw_error  = null;

	// Si caller ya pasó "books"
	if (!empty($_POST['books'])) {
		$decoded = json_decode(wp_unslash($_POST['books']), true);
		$candidates = politeia_normalize_books_array($decoded);
	}

	// Imagen subida vía $_FILES (legacy)
	if (empty($candidates) && !empty($_FILES['file']['tmp_name'])) {
		$data_url = politeia_file_to_data_url($_FILES['file']);
		if ($data_url) {
			$raw = politeia_chatgpt_process_image($data_url);
			if (is_string($raw) && stripos($raw, 'Error') === 0) {
				$raw_error = $raw;
			} else {
				$json    = politeia_extract_json($raw);
				$decoded = json_decode($json, true);
				if (is_array($decoded)) $candidates = politeia_normalize_books_array($decoded);
				else $raw_error = 'openai_invalid_json';
			}
		} else {
			$raw_error = 'data_url_failed';
		}
	}

	// Texto libre "Titulo - Autor"
	if (empty($candidates) && !empty($_POST['text'])) {
		$lines = preg_split('/\r\n|\r|\n/', wp_unslash($_POST['text']));
		foreach ($lines as $ln) {
			$ln = trim($ln);
			if ($ln === '') continue;
			if (strpos($ln, ' - ') !== false) {
				list($t,$a) = array_map('trim', explode(' - ', $ln, 2));
				if ($t && $a) $candidates[] = ['title'=>$t, 'author'=>$a];
			}
		}
	}

	if (empty($candidates)) {
		if ($raw_error) wp_send_json_error([ 'message'=>'openai_error', 'detail'=>$raw_error ], 502);
		wp_send_json_error([ 'message'=>'no_books_detected' ], 200);
	}

	// Encola / filtra (DB + efímeros)
	if ( ! function_exists('politeia_chatgpt_queue_confirm_items') ) {
		$path = plugin_dir_path(__FILE__) . 'modules/book-detection/functions-book-confirm-queue.php';
		if ( file_exists($path) ) require_once $path;
	}

	$result = politeia_chatgpt_queue_confirm_items(
		$candidates,
		[
			'user_id'      => $user_id,
			'input_type'   => $input_type,
			'source_note'  => $source_note ? $source_note : ( $input_type === 'image' ? 'vision' : 'manual' ),
			'raw_response' => isset($_POST['raw']) ? wp_unslash($_POST['raw']) : null,
		]
	);

	// Persistir efímeros para 1er render
	$ephem_key = 'pol_confirm_ephemeral_' . (int) $user_id;
	$existing  = get_transient($ephem_key);
	$existing  = is_array($existing) ? $existing : [];
	$incoming  = $result['in_shelf'] ?? [];
	if (!empty($incoming)) set_transient($ephem_key, array_merge($existing, $incoming), 15 * MINUTE_IN_SECONDS);

	wp_send_json_success([
		'queued'   => (int)($result['queued']   ?? 0),
		'skipped'  => (int)($result['skipped']  ?? 0),
		'pending'  => $result['pending']  ?? [],
		'in_shelf' => $result['in_shelf'] ?? [],
	]);
}

/** ======================================================================
 *  AJAX unificado moderno: text / image / (audio*)
 *  ====================================================================== */
if ( ! function_exists('politeia_process_input_ajax') ) {
	add_action('wp_ajax_politeia_process_input',        'politeia_process_input_ajax');
	add_action('wp_ajax_nopriv_politeia_process_input', 'politeia_process_input_ajax');

	function politeia_process_input_ajax() {
		check_ajax_referer('politeia-chatgpt-nonce', 'nonce');

		$type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
		if (!$type) wp_send_json_error(['message'=>'bad_request'], 400);

		$user_id = get_current_user_id();
		if (!$user_id) wp_send_json_error(['message'=>'not_logged_in'], 401);

		// Cargar cola si no está
		if ( ! function_exists('politeia_chatgpt_queue_confirm_items') ) {
			$path = plugin_dir_path(__FILE__) . 'modules/book-detection/functions-book-confirm-queue.php';
			if ( file_exists($path) ) require_once $path;
		}

		try {
			$raw_from_api = '';
			$candidates   = [];

			if ($type === 'image') {
				if (empty($_POST['image_data'])) wp_send_json_error(['message'=>'no_image'], 400);
				$raw_from_api = politeia_chatgpt_process_image( wp_unslash($_POST['image_data']) );
			} elseif ($type === 'text') {
				if (empty($_POST['prompt'])) wp_send_json_error(['message'=>'empty_text'], 400);
				// Instrucción por defecto (puedes sustituirla por una opción de admin)
				$user_text = sanitize_textarea_field($_POST['prompt']);
                                $prompt = 'A partir del siguiente texto, extrae los libros y devuelve SOLO un JSON con la forma { "books": [ { "title": "...", "authors": ["..."]} ] }.' .
                                                  "\n\nTexto:\n\"{$user_text}\"";
				$raw_from_api = politeia_chatgpt_send_query($prompt);
			} elseif ($type === 'audio') {
				// (Opcional) Implementar transcripción si lo deseas
				wp_send_json_error(['message'=>'audio_not_enabled'], 501);
			} else {
				wp_send_json_error(['message'=>'bad_type'], 400);
			}

			if (strpos($raw_from_api, 'Error:') === 0) {
				wp_send_json_error(['message'=>'openai_error', 'detail'=>$raw_from_api], 502);
			}

			// Parseo de JSON
			$raw_clean = politeia_extract_json($raw_from_api);
			$decoded   = json_decode($raw_clean, true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				wp_send_json_error(['message'=>'openai_invalid_json'], 502);
			}

			$candidates = politeia_normalize_books_array($decoded);

			if (empty($candidates)) {
				// Éxito pero sin candidatos: avisamos a la UI para que refresque de todos modos
				wp_send_json_success([
					'queued'   => 0,
					'skipped'  => 0,
					'pending'  => [],
					'in_shelf' => [],
				]);
			}

			// Encolar / filtrar
			$result = politeia_chatgpt_queue_confirm_items(
				$candidates,
				[
					'user_id'      => $user_id,
					'input_type'   => $type,
					'source_note'  => ($type === 'image') ? 'vision' : $type,
					'raw_response' => is_string($raw_clean) ? $raw_clean : wp_json_encode($raw_clean),
				]
			);

			// Persistir efímeros
			$ephem_key = 'pol_confirm_ephemeral_' . (int) $user_id;
			$existing  = get_transient($ephem_key);
			$existing  = is_array($existing) ? $existing : [];
			$incoming  = $result['in_shelf'] ?? [];
			if (!empty($incoming)) set_transient($ephem_key, array_merge($existing, $incoming), 15 * MINUTE_IN_SECONDS);

			wp_send_json_success([
				'queued'   => (int)($result['queued']   ?? 0),
				'skipped'  => (int)($result['skipped']  ?? 0),
				'pending'  => $result['pending']  ?? [],
				'in_shelf' => $result['in_shelf'] ?? [],
			]);

		} catch (Throwable $e) {
			wp_send_json_error(['message'=>'exception', 'detail'=>$e->getMessage()], 500);
		}
	}
}
