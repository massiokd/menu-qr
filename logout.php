<?php
session_name('FENNEC_ADMIN');
session_start();
session_destroy();

session_name('FENNEC_GERANT');
session_start();
session_destroy();

header('Location: login.php');
exit;