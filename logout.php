<?php
// logout.php
session_start();
session_unset();
session_destroy();
require_once 'includes/db_config.php';
?>
<!DOCTYPE html>
<html>
<head><title>Signing out...</title></head>
<body>
<script>
    // Clear chatbot session history on logout
    sessionStorage.removeItem('nibash_chat_history');
    window.location.href = '<?= BASE_URL ?>index.php';
</script>
</body>
</html>
