<?php
// Página dedicada do chamado. O detalhe é renderizado pelo mesmo componente
// usado no dashboard para evitar duplicação de HTML e lógica de atendimento.
$_GET['ticket_page'] = '1';
require __DIR__ . '/dashboard.php';
