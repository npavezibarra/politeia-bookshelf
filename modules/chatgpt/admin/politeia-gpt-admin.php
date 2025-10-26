<?php
/**
 * Admin UI for PoliteiaGPT
 * - Main menu "PoliteiaGPT" with tabs "General" and "GPT Instructions".
 * - Saves:
 *     - politeia_chatgpt_api_token        (API Key)
 *     - politeia_gpt_instruction_text     (instruction for TEXT)
 *     - politeia_gpt_instruction_audio    (instruction for AUDIO)
 *     - politeia_gpt_instruction_image    (instruction for IMAGE)
 * - Fallback: if the AUDIO instruction is empty, the handler will reuse the TEXT one.
 */

if ( ! defined('ABSPATH') ) exit;

/** Sanitize paragraphs (strip HTML) */
function politeia_gpt_sanitize_paragraph( $input ) {
    $input = wp_strip_all_tags( $input ); // remove HTML/JS
    return trim( $input );
}

/** Register settings and fields */
function politeia_gpt_register_settings() {

    // === Tab: General ===
    register_setting(
        'politeia_gpt_general',
        'politeia_chatgpt_api_token',
        [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]
    );

    add_settings_section(
        'politeia_gpt_section_general',
        'General configuration',
        function () {
            echo '<p>Provide your OpenAI API Key to enable the plugin features.</p>';
        },
        'politeia_gpt_general'
    );

    add_settings_field(
        'politeia_chatgpt_api_token_field',
        'OpenAI API Key',
        function () {
            $token = get_option('politeia_chatgpt_api_token', '');
            echo '<input type="password" name="politeia_chatgpt_api_token" value="' . esc_attr($token) . '" class="regular-text" />';
            echo '<p class="description">Generate a key at <a target="_blank" href="https://platform.openai.com/api-keys">platform.openai.com/api-keys</a>.</p>';
        },
        'politeia_gpt_general',
        'politeia_gpt_section_general'
    );

    // === Tab: GPT Instructions ===
    register_setting(
        'politeia_gpt_instructions',
        'politeia_gpt_instruction_text',
        [
            'type'              => 'string',
            'sanitize_callback' => 'politeia_gpt_sanitize_paragraph',
            'default'           => '',
        ]
    );
    register_setting(
        'politeia_gpt_instructions',
        'politeia_gpt_instruction_audio',
        [
            'type'              => 'string',
            'sanitize_callback' => 'politeia_gpt_sanitize_paragraph',
            'default'           => '',
        ]
    );
    register_setting(
        'politeia_gpt_instructions',
        'politeia_gpt_instruction_image',
        [
            'type'              => 'string',
            'sanitize_callback' => 'politeia_gpt_sanitize_paragraph',
            'default'           => '',
        ]
    );

    add_settings_section(
        'politeia_gpt_section_instructions',
        'GPT Instructions',
        function () {
            echo '<p>Define instructions per input type. If "Audio" is empty, the "Text" instruction will be used.</p>';
        },
        'politeia_gpt_instructions'
    );

    // TEXT field
    add_settings_field(
        'politeia_gpt_instruction_text_field',
        'Instruction for TEXT',
        function () {
            $val = get_option('politeia_gpt_instruction_text', '');
            if ($val === '') {
                $val = 'Based on the following text, extract the mentioned books and return ONLY a JSON with this exact shape: { "books": [ { "title": "...", "author": "..." } ] }. Do not include comments, notes, or markdown.';
            }
            echo '<textarea name="politeia_gpt_instruction_text" rows="6" class="large-text" style="max-width: 900px;">' . esc_textarea($val) . '</textarea>';
            echo '<p class="description">Used for written prompts (or audio transcripts when no audio-specific instruction is defined).</p>';
        },
        'politeia_gpt_instructions',
        'politeia_gpt_section_instructions'
    );

    // AUDIO field
    add_settings_field(
        'politeia_gpt_instruction_audio_field',
        'Instruction for AUDIO (optional)',
        function () {
            $val = get_option('politeia_gpt_instruction_audio', '');
            echo '<textarea name="politeia_gpt_instruction_audio" rows="6" class="large-text" style="max-width: 900px;">' . esc_textarea($val) . '</textarea>';
            echo '<p class="description">Useful for dictated inputs: ask to ignore filler words, correct book title pronunciation, remove repetitions, and normalize author names. When empty, the TEXT instruction is reused.</p>';
        },
        'politeia_gpt_instructions',
        'politeia_gpt_section_instructions'
    );

    // IMAGE field
    add_settings_field(
        'politeia_gpt_instruction_image_field',
        'Instruction for IMAGE',
        function () {
            $val = get_option('politeia_gpt_instruction_image', '');
            if ($val === '') {
                $val = 'Analyze this image (bookshelf). Extract the visible book titles and authors and return ONLY a JSON with this exact shape: { "books": [ { "title": "...", "author": "..." } ] }. Skip uncertain or partial matches. No markdown or extra text.';
            }
            echo '<textarea name="politeia_gpt_instruction_image" rows="6" class="large-text" style="max-width: 900px;">' . esc_textarea($val) . '</textarea>';
            echo '<p class="description">For computer-vision image analysis.</p>';
        },
        'politeia_gpt_instructions',
        'politeia_gpt_section_instructions'
    );
}
add_action('admin_init', 'politeia_gpt_register_settings');

/** Main menu */
function politeia_gpt_add_menu() {
    $cap = 'manage_options';
    add_menu_page(
        'PoliteiaGPT',
        'PoliteiaGPT',
        $cap,
        'politeia-gpt',
        'politeia_gpt_render_page',
        'dashicons-art',
        59
    );
}
add_action('admin_menu', 'politeia_gpt_add_menu');

/** Render admin page with tabs */
function politeia_gpt_render_page() {
    if ( ! current_user_can('manage_options') ) return;

    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
    if ( ! in_array($tab, ['general', 'instructions'], true) ) $tab = 'general';

    $base_url       = admin_url('admin.php?page=politeia-gpt');
    $url_general    = add_query_arg( ['tab' => 'general'], $base_url );
    $url_instructions = add_query_arg( ['tab' => 'instructions'], $base_url );
    ?>
    <div class="wrap">
        <h1>PoliteiaGPT</h1>
        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url($url_general); ?>" class="nav-tab <?php echo $tab === 'general' ? 'nav-tab-active' : ''; ?>">General</a>
            <a href="<?php echo esc_url($url_instructions); ?>" class="nav-tab <?php echo $tab === 'instructions' ? 'nav-tab-active' : ''; ?>">GPT Instructions</a>
        </h2>

        <?php if ($tab === 'general'): ?>
            <form action="options.php" method="post">
                <?php
                settings_fields('politeia_gpt_general');
                do_settings_sections('politeia_gpt_general');
                submit_button('Save changes');
                ?>
            </form>
        <?php else: ?>
            <form action="options.php" method="post">
                <?php
                settings_fields('politeia_gpt_instructions');
                do_settings_sections('politeia_gpt_instructions');
                submit_button('Save instructions');
                ?>
            </form>
        <?php endif; ?>
    </div>
    <?php
}
