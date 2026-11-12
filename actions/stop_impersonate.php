<?php
session_name('FENNEC_GERANT');
session_start();

if (isset($_SESSION['impersonated_by'])) {
    $admin_id = $_SESSION['impersonated_by'];
    
    // On détruit la session gérant
    $_SESSION = array();
    session_destroy();
    
    // On restaure la session admin
    session_name('FENNEC_ADMIN');
    session_start();
    $_SESSION['user_id'] = $admin_id;
    $_SESSION['role'] = 'super_admin';
    
    header('Location: ../super_admin.php');
    exit;
}
header('Location: ../logout.php');
exit;