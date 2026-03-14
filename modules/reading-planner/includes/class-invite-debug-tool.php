<?php
namespace Politeia\ReadingPlanner;

if (!defined('ABSPATH')) {
	exit;
}

class InviteDebugTool
{
	private const SHORTCODE = 'prs_plan_invite_debug';
	private const ACTION = 'prs_send_plan_invite_debug';

	public static function init(): void
	{
		add_action('init', array(__CLASS__, 'register_shortcode'));
		add_action('admin_post_' . self::ACTION, array(__CLASS__, 'handle_submit'));
	}

	public static function register_shortcode(): void
	{
		add_shortcode(self::SHORTCODE, array(__CLASS__, 'render_shortcode'));
	}

	public static function render_shortcode(): string
	{
		if (!is_user_logged_in() || !current_user_can('manage_options')) {
			return '<p>' . esc_html__('This debug tool is available to administrators only.', 'politeia-reading') . '</p>';
		}

		$status = isset($_GET['prs_invite_debug_status']) ? sanitize_key(wp_unslash($_GET['prs_invite_debug_status'])) : '';
		$code = isset($_GET['prs_invite_debug_code']) ? sanitize_key(wp_unslash($_GET['prs_invite_debug_code'])) : '';
		$db_error = isset($_GET['prs_invite_debug_db_error']) ? sanitize_text_field(wp_unslash($_GET['prs_invite_debug_db_error'])) : '';
		$mail_sent = isset($_GET['prs_invite_debug_mail_sent']) ? sanitize_key(wp_unslash($_GET['prs_invite_debug_mail_sent'])) : '';
		$invite_id = isset($_GET['prs_invite_debug_invite_id']) ? absint($_GET['prs_invite_debug_invite_id']) : 0;
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host = isset($_SERVER['HTTP_HOST']) ? (string) wp_unslash($_SERVER['HTTP_HOST']) : '';
		$uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';
		$current_url = $scheme . $host . $uri;

		ob_start();
		?>
		<div class="prs-invite-debug-tool" style="border:1px solid #ddd;padding:16px;border-radius:8px;background:#fff;">
			<h3><?php echo esc_html__('Plan Invite Debug Tool', 'politeia-reading'); ?></h3>
			<p><?php echo esc_html__('Use this temporary form to trigger participant invitation emails.', 'politeia-reading'); ?></p>

			<?php if ('success' === $status): ?>
				<p style="color:#0b6b2a;">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: invite id, 2: mail status */
							__('Invite created (ID: %1$d). Email sent: %2$s.', 'politeia-reading'),
							$invite_id,
							('1' === $mail_sent ? __('yes', 'politeia-reading') : __('no', 'politeia-reading'))
						)
					);
					?>
				</p>
			<?php elseif ('error' === $status): ?>
				<p style="color:#b42318;">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: error code */
							__('Invite failed. Error: %s', 'politeia-reading'),
							$code ? $code : 'unknown_error'
						)
					);
					?>
				</p>
				<?php if ($db_error): ?>
					<p style="color:#b42318;font-size:13px;">
						<?php echo esc_html($db_error); ?>
					</p>
				<?php endif; ?>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
				<input type="hidden" name="return_url" value="<?php echo esc_url($current_url); ?>">
				<?php wp_nonce_field('prs_invite_debug_send', 'prs_invite_debug_nonce'); ?>

				<p>
					<label for="prs_invite_plan_id"><?php echo esc_html__('Plan ID', 'politeia-reading'); ?></label><br>
					<input type="number" id="prs_invite_plan_id" name="plan_id" min="1" required>
				</p>

				<p>
					<label for="prs_invite_email"><?php echo esc_html__('Invitee email', 'politeia-reading'); ?></label><br>
					<input type="email" id="prs_invite_email" name="invitee_email" required style="min-width:320px;">
				</p>

				<p>
					<label for="prs_invite_first_name"><?php echo esc_html__('First Name', 'politeia-reading'); ?></label><br>
					<input type="text" id="prs_invite_first_name" name="first_name" style="min-width:320px;">
				</p>

				<p>
					<label for="prs_invite_last_name"><?php echo esc_html__('Last Name', 'politeia-reading'); ?></label><br>
					<input type="text" id="prs_invite_last_name" name="last_name" style="min-width:320px;">
				</p>

				<p>
					<label for="prs_invite_notify_on"><?php echo esc_html__('notify_on', 'politeia-reading'); ?></label><br>
					<select id="prs_invite_notify_on" name="notify_on">
						<option value="none">none</option>
						<option value="failures_only">failures_only</option>
						<option value="milestones">milestones</option>
						<option value="daily_summary">daily_summary</option>
						<option value="weekly_summary">weekly_summary</option>
					</select>
				</p>

				<p>
					<button type="submit"><?php echo esc_html__('Send debug invite', 'politeia-reading'); ?></button>
				</p>
				<p style="color:#666;font-size:13px;">
					<?php echo esc_html__('Shortcode: [prs_plan_invite_debug]', 'politeia-reading'); ?>
				</p>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public static function handle_submit(): void
	{
		if (!is_user_logged_in() || !current_user_can('manage_options')) {
			wp_die(esc_html__('Forbidden.', 'politeia-reading'));
		}

		check_admin_referer('prs_invite_debug_send', 'prs_invite_debug_nonce');

		$plan_id = isset($_POST['plan_id']) ? absint($_POST['plan_id']) : 0;
		$invitee_email = isset($_POST['invitee_email']) ? sanitize_email(wp_unslash($_POST['invitee_email'])) : '';
		$first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
		$last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
		$notify_on = isset($_POST['notify_on']) ? sanitize_key(wp_unslash($_POST['notify_on'])) : 'none';
		$return_url = isset($_POST['return_url']) ? esc_url_raw(wp_unslash($_POST['return_url'])) : '';

		if (!$return_url) {
			$return_url = wp_get_referer();
		}
		$return_url = wp_validate_redirect($return_url, home_url('/'));

		$request = new \WP_REST_Request('POST', '/politeia/v1/reading-plan/' . $plan_id . '/participants/invite');
		$request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
		$request->set_param('plan_id', $plan_id);
		$request->set_header('Content-Type', 'application/json');
		$request->set_body(
			wp_json_encode(
				array(
					'invitee_email' => $invitee_email,
					'role' => 'observer',
					'notify_on' => $notify_on,
					'first_name' => $first_name,
					'last_name' => $last_name,
				)
			)
		);

		$response = Rest::invite_participant($request);
		$status_code = ($response instanceof \WP_REST_Response) ? (int) $response->get_status() : 500;
		$data = ($response instanceof \WP_REST_Response) ? (array) $response->get_data() : array();

		if ($status_code >= 200 && $status_code < 300) {
			$redirect_url = add_query_arg(
				array(
					'prs_invite_debug_status' => 'success',
					'prs_invite_debug_invite_id' => isset($data['invite_id']) ? (int) $data['invite_id'] : 0,
					'prs_invite_debug_mail_sent' => !empty($data['mail_sent']) ? '1' : '0',
				),
				$return_url
			);
			wp_safe_redirect($redirect_url);
			exit;
		}

		$error_code = isset($data['error']) ? sanitize_key((string) $data['error']) : 'unknown_error';
		$db_error = isset($data['db_error']) ? sanitize_text_field((string) $data['db_error']) : '';
		$redirect_url = add_query_arg(
			array(
				'prs_invite_debug_status' => 'error',
				'prs_invite_debug_code' => $error_code,
				'prs_invite_debug_db_error' => $db_error,
			),
			$return_url
		);
		wp_safe_redirect($redirect_url);
		exit;
	}
}

InviteDebugTool::init();
