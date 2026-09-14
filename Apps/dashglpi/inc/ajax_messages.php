<?php

/**
 * Mensagens de fallback compartilhadas pelos endpoints AJAX (cliente e bridge).
 * Fase 1 do PLAN-20260703-013: apenas declaradas aqui, uso é opcional para quem migrar
 * (Fase 2). Nenhum endpoint existente foi alterado para usá-las.
 */

const DASHGLPI_AJAX_MSG_METHOD_NOT_ALLOWED = 'Metodo nao permitido.';
const DASHGLPI_AJAX_MSG_CSRF_INVALID = 'Token CSRF invalido.';
const DASHGLPI_AJAX_MSG_TICKET_INVALID = 'Chamado invalido.';
const DASHGLPI_AJAX_MSG_TICKET_NOT_FOUND = 'Chamado nao encontrado.';
const DASHGLPI_AJAX_MSG_UNKNOWN_ACTION = 'Acao desconhecida.';
const DASHGLPI_AJAX_MSG_GENERIC_SERVER_ERROR = 'Erro interno no servidor.';
