<?php

// Contexto confiável do atendimento no Dash (PLAN-20260905-001).
require_once __DIR__ . '/glpi_admin.php';

/** O navegador nunca escolhe o autor nem o perfil enviado ao bridge. */
function dashglpi_attendance_request(string $endpoint, int $ticketId, array $payload = [], array $files = []): array
{
    dashglpi_assert_ticket_access($ticketId);
    $context = dashglpi_current_user_context();
    $profileId = (int) ($context['effective_profile_id'] ?? 0);
    if ($profileId <= 0) {
        // Mesmo desempate da listagem de perfis: padrão, estático, menor ID.
        // A ausência de regra do Dash não concede direitos nem combina perfis.
        $profiles = dashglpi_current_user_profiles((int) $context['user_id']);
        $profileId = (int) ($profiles[0]['profile_id'] ?? 0);
    }
    if ($profileId <= 0) {
        throw new RuntimeException('Nenhum perfil GLPI disponível para este atendimento.');
    }
    $payload['ticket_id'] = $ticketId;
    $payload['actor'] = ['user_id' => (int) $context['user_id'], 'profile_id' => $profileId];
    return dashglpi_admin_bridge_request($endpoint, $payload, $files);
}

/** Valida os uploads antes do encaminhamento, sem confiar no MIME do navegador. */
function dashglpi_attendance_uploads(array $files): array
{
    $entry = $files['attachments'] ?? [];
    $result = [];
    foreach ((array) ($entry['name'] ?? []) as $index => $name) {
        $error = (int) ($entry['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $path = (string) ($entry['tmp_name'][$index] ?? '');
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($path)) {
            throw new RuntimeException('Falha ao receber anexo. Verifique o limite de envio.');
        }
        $result[] = ['field_name' => 'attachments[]', 'client_name' => basename((string) $name),
            'tmp_name' => $path, 'mime_type' => (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream'];
    }
    return $result;
}
