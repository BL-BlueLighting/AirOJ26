<?php
require __DIR__ . '/include/__init__.php';
require __DIR__ . '/logics/verify_email.php';
render_page('verify_email.php', get_defined_vars());
