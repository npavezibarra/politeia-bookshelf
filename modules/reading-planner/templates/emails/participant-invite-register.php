<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<title>Plan invitation</title>
</head>
<body style="margin:0;padding:24px;background:#f7f7f7;font-family:Arial,Helvetica,sans-serif;color:#222;">
	<div style="max-width:640px;margin:0 auto;background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:24px;">
		<h2 style="margin:0 0 12px 0;font-size:22px;line-height:1.3;">You were invited to Politeia</h2>
		<p style="margin:0 0 12px 0;">
			<strong><?php echo esc_html((string) $inviter_name); ?></strong> invited you to observe:
			<strong><?php echo esc_html((string) $plan_name); ?></strong>.
		</p>
		<p style="margin:0 0 20px 0;">
			You need a Politeia account with this email to accept the invitation.
		</p>
		<p style="margin:0 0 10px 0;">
			<a href="<?php echo esc_url((string) $register_url); ?>" style="display:inline-block;padding:10px 16px;background:#111;color:#fff;text-decoration:none;border-radius:6px;">Create account and accept</a>
		</p>
		<p style="margin:0 0 16px 0;">
			Already have an account? <a href="<?php echo esc_url((string) $accept_url); ?>">Accept invitation</a>
		</p>
		<p style="margin:16px 0 0 0;color:#777;font-size:13px;">
			This invitation expires on <?php echo esc_html((string) $expires_at); ?> (UTC).
		</p>
	</div>
</body>
</html>
