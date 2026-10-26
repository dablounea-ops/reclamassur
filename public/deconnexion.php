<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/auth.php';
deconnecter();
$script = $_SERVER['SCRIPT_NAME'] ?? '';
rediriger(str_ends_with($script, '.php') ? 'connexion.php' : url('/connexion'));