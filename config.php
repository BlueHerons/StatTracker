<?php
//
// STAT TRACKER CONFIGURATION FILE
//

// Database host. Example: database.thelocalresistance.com
define("DB_HOST", "");
// Database name. Example: StatTracker
define("DB_NAME", "");
// Database user. Must have read/write access to database specified above.
define("DB_USER", "");
// Password for the database user specified above.
define("DB_PASS", "");

// Email configuration. Stat Tracker uses email for registration and optionally stat submission.
// Email host. Example: smtp.gmail.com
define("SMTP_HOST", "smtp.gmail.com");
// Email port. If using Gmail, use 465. Other hosts may require a different value.
define("SMTP_PORT", "465");
// Email encryption. If using Gmail, use ssl. Other host may require a different value.
define("SMTP_ENCR", "ssl");
// Email username. If using Gmail, the email address that you sign in with.
define("SMTP_USER", "");
// Email password.
define("SMTP_PASS", "");

// Enter the values appropriate for your local group.
// The name of your community. Example: Blue Herons Resistance
define("GROUP_NAME",  "The Local Resistance");
// The email that should be included in the "From" field of all emails. Note: Your SMTP server may ignore this value,
// and instead use the one specified in SMTP_USER.
define("GROUP_EMAIL", "stats@localresistance.com");
// This name of the agent who should recieve activation codes from agents trying to register.
define("ADMIN_AGENT", "YourIngressAgentName");
// The address that agents should email screenshots of their profile to for Email submissions. This is an optional
// feature that requires additional set up steps. More information is available on the Stat Tracker wiki.
//define("EMAIL_SUBMISSION", "stats@thelocalresistance.com");
// Google Analytics tracking ID. This is an optional feature. Uncomment this line and insert you GA tracking ID to enable.
//define("GOOGLE_ANALYTICS_ID", "");

// Authentication Provider Configuration. PLEASE READ THE WIKI ON GITHUB TO UNDERSTAND THIS.
// Google OAuth configuration. https://console.developers.google.com. Only required if you use Google as your provider.
// This provider requires email configuration. See "SMTP_*" constants.
define("GOOGLE_CLIENT_ID",     "");
define("GOOGLE_CLIENT_SECRET", "");
define("GOOGLE_APP_NAME",      "");
define("GOOGLE_REDIRECT_URL",  "");

// You should not need to change any values below this line.

// Colors to use when displaying agent names.
define("RES_BLUE",  "#00BFFF");
define("ENL_GREEN", "#2BED1B");

// Folder to temporarily store screenshot uploads. RW access required.
define("UPLOAD_DIR", realpath("uploads") . "/"); // MUST have trailing slash
define("COMMIT_HASH", "");
define("TAG_NAME", "");
?>
