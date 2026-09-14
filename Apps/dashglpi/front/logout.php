<?php
require_once __DIR__ . '/../inc/bootstrap.php';

dashglpi_logout();
dashglpi_redirect('/front/login.php');
