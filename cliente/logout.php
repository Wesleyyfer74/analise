<?php
require_once __DIR__ . '/../includes/auth.php';
start_secure_session();
logout_user();
header('Location: ../index.php', true, 303);
