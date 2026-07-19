<?php require __DIR__.'/../includes/functions.php';session_start();$_SESSION=[];session_destroy();header('Location: login.php');
