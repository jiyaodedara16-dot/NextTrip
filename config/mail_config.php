<?php
/**
 * config/mail_config.php
 * ------------------------------------------------------------
 * Gmail SMTP credentials for includes/mailer.php (PHPMailer).
 *
 * IMPORTANT: MAIL_SMTP_USER below is set to nexttrip@gmail.com to
 * match the address used elsewhere on the site (footer, contact
 * page) — but a Gmail App Password is tied to ONE specific Google
 * account. If "tvai xicl nnyq qgjs" wasn't generated on
 * nexttrip@gmail.com, change MAIL_SMTP_USER to whichever address it
 * WAS generated on, or SMTP auth will fail.
 *
 * Never commit real credentials to a public repo — keep this file
 * out of version control (or move the values into environment
 * variables) once you're past coursework/local use.
 * ------------------------------------------------------------
 */

define('MAIL_SMTP_HOST', 'smtp.gmail.com');
define('MAIL_SMTP_PORT', 587);
define('MAIL_SMTP_USER', 'jiyaodedara16@gmail.com'); // Gmail address
define('MAIL_SMTP_PASS', 'tvaixiclnnyqqgjs'); // Gmail App Password, spaces removed
define('MAIL_FROM_EMAIL', MAIL_SMTP_USER);
define('MAIL_FROM_NAME', 'NextTrip');

/**
 * When true, every generated OTP is also written to
 * logs/otp_debug.log (created automatically) regardless of whether
 * the email actually sent. Handy while SMTP isn't confirmed working
 * yet (e.g. on a host that blocks outbound port 587). Set to false
 * once real email delivery is confirmed.
 */
define('MAIL_DEBUG_LOG_OTP', true);
