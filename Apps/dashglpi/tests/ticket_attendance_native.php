<?php

// Teste de integração GLPI 11 em banco DESCARTÁVEL (PLAN-20260905-001).
// Não executar contra a instalação operacional. A dupla guarda abaixo é obrigatória.
if (PHP_SAPI !== 'cli' || getenv('DASHGLPI_TEST_CONFIG') !== '/test/config') {
    exit("Use o ambiente descartável descrito em tests/README.md.\n");
}
define('GLPI_CONFIG_DIR', '/test/config');
require '/var/www/glpi/vendor/autoload.php';
$kernel = new \Glpi\Kernel\Kernel();
$kernel->boot();
if ($DB->dbdefault !== 'attendance_test' || $DB->dbhost !== 'localhost:/test/socket/mysql.sock') {
    throw new RuntimeException('A base não é a base descartável de teste.');
}
require __DIR__ . '/../inc/ticket_attendance_bridge.php';
$CFG_GLPI['use_notifications'] = 0;
Session::initVars();
$_SESSION['glpiID'] = 2;
$_SESSION['glpidefault_entity'] = 0;
$user = new User(); $user->getFromDB(2); $user->loadPreferencesInSession();
Session::initEntityProfiles(2); Session::changeProfile(4); Session::changeActiveEntities('all'); Session::loadGroups();

$passed = 0;
function check(bool $condition, string $message): void {
    global $passed;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    $passed++; echo 'PASS: ' . $message . PHP_EOL;
}
function denied(callable $fn, string $message, ?int $code = null): void {
    try { $fn(); } catch (RuntimeException $e) { check($code === null || $e->getCode() === $code, $message); return; }
    check(false, $message);
}
function fixture_user(string $name, int $profileId, int $entityId = 0): int {
    $user = new User();
    $id = (int) $user->add(['name' => 'attendance_' . $name . '_' . bin2hex(random_bytes(3)), 'is_active' => 1, '_profiles_id' => $profileId, '_entities_id' => $entityId, '_is_recursive' => 0]);
    if ($id <= 0) throw new RuntimeException('Falha na criação do usuário fictício.');
    (new Profile_User())->add(['users_id' => $id, 'profiles_id' => $profileId, 'entities_id' => $entityId, 'is_recursive' => 0]);
    return $id;
}
function fixture_ticket(int $requester, int $entityId = 0): int {
    $ticket = new Ticket();
    $id = (int) $ticket->add(['name' => 'Teste de atendimento', 'content' => '<div class="ticket-detail-section" style="font-size:16px"><p>Áudio não funciona em reuniões.</p><p><strong>Verificar drivers.</strong></p></div>',
        'entities_id' => $entityId, 'type' => 1, 'urgency' => 3, 'impact' => 3, '_users_id_requester' => $requester]);
    if ($id <= 0) throw new RuntimeException('Falha na criação do chamado fictício.');
    return $id;
}
$fixtures = Session::callAsSystem(function (): array {
    $requester = fixture_user('solicitante', 1);
    $other = fixture_user('outro', 1);
    $operator = fixture_user('tecnico', 6);
    $second = fixture_user('tecnico2', 6);
    $readonly = fixture_user('leitura', 8);
    $child = (int) (new Entity())->add(['name' => 'Entidade de teste ' . bin2hex(random_bytes(3)), 'entities_id' => 0]);
    $outsider = fixture_user('externo', 1, $child);
    return compact('requester', 'other', 'operator', 'second', 'readonly', 'child', 'outsider') + [
        'ticket' => fixture_ticket($requester), 'other_ticket' => fixture_ticket($other), 'outside_ticket' => fixture_ticket($outsider, $child),
    ];
});
function actor_call(int $user, int $profile, int $ticketId, callable $fn, array $extra = []): array {
    return dashglpi_attendance_as_actor(['actor' => ['user_id' => $user, 'profile_id' => $profile], 'ticket_id' => $ticketId] + $extra, $fn);
}
function mutate_as(int $user, int $profile, int $ticketId, array $payload, callable $fn): array {
    return actor_call($user, $profile, $ticketId, fn(array $p, Ticket $t): array => dashglpi_attendance_mutate($t, $payload, fn(Ticket $locked): array => $fn($locked, $payload)));
}
$tid = $fixtures['ticket']; $requester = $fixtures['requester']; $tech = $fixtures['operator'];
$detail = actor_call(2, 4, $tid, fn($p, $t) => dashglpi_attendance_detail($t));
check($detail['capabilities']['mode'] === 'operator' && $detail['capabilities']['edit'], 'Admin recebe capacidades operacionais nativas');
check(!str_contains($detail['content_html'], 'style=') && !str_contains($detail['content_html'], 'class='), 'Descrição remove atributos de apresentação');
check(str_contains($detail['content_html'], '<strong>Verificar drivers.</strong>'), 'Descrição preserva formatação');
$before = $_SESSION;
$self = actor_call($requester, 1, $tid, fn($p, $t) => dashglpi_attendance_detail($t));
check($self['capabilities']['mode'] === 'self_service' && !$self['capabilities']['edit'] && !$self['capabilities']['private'], 'Self-Service não ganha poderes operacionais');
check($_SESSION === $before, 'Contexto do ator é restaurado após a consulta');
$selfWithoutDashProfile = actor_call($requester, 0, $tid, fn($p, $t) => dashglpi_attendance_detail($t));
check($selfWithoutDashProfile['capabilities']['mode'] === 'self_service', 'Solicitante segue a autorização nativa mesmo sem perfil escolhido pelo Dash');
denied(fn() => actor_call($requester, 4, $tid, fn($p, $t) => []), 'Perfil forjado é negado', 403);
denied(fn() => actor_call($requester, 1, $fixtures['other_ticket'], fn($p, $t) => []), 'Outro solicitante na mesma entidade não pode ler o chamado', 403);
denied(fn() => actor_call($requester, 1, $fixtures['outside_ticket'], fn($p, $t) => []), 'Chamado de outra entidade é negado', 403);
denied(fn() => mutate_as($requester, 1, $tid, ['action'=>'update_properties', 'changes'=>['name'=>'Forjado']], 'dashglpi_attendance_update'), 'Self-Service não altera propriedades por requisição manual', 403);
denied(fn() => mutate_as($fixtures['readonly'], 8, $tid, ['content'=>'Forjado'], fn($t,$p)=>dashglpi_attendance_add_event($t,$p,'solution')), 'Perfil somente leitura não registra solução', 403);
$catalog = actor_call(2, 4, $tid, fn($p, $t) => dashglpi_attendance_catalog($t, []));
check(isset($catalog['catalog']['technicians'], $catalog['catalog']['categories']), 'Catálogo nativo é carregado');
check(!in_array($fixtures['outsider'], array_column($catalog['catalog']['users'], 'id'), true), 'Catálogo respeita entidade');
mutate_as(2,4,$tid,['action'=>'assign','user_id'=>$tech], 'dashglpi_attendance_update');
mutate_as(2,4,$tid,['action'=>'assign','user_id'=>$fixtures['second']], 'dashglpi_attendance_update');
$assigned = actor_call(2,4,$tid,fn($p,$t)=>dashglpi_attendance_detail($t));
check(count(array_filter($assigned['actors']['assign'], fn($a)=>$a['itemtype']==='User')) === 2, 'Atribuição preserva os técnicos existentes');
$reply = mutate_as($tech,6,$tid,['content'=>'Drivers instalados. <script>exemplo literal</script>'],fn($t,$p)=>dashglpi_attendance_add_event($t,$p,'reply'));
$private = mutate_as($tech,6,$tid,['content'=>'Diagnóstico interno'],fn($t,$p)=>dashglpi_attendance_add_event($t,$p,'private'));
$task = mutate_as($tech,6,$tid,['content'=>'Validar áudio','duration_minutes'=>15,'state'=>2,'users_id_tech'=>$tech],fn($t,$p)=>dashglpi_attendance_add_event($t,$p,'task'));
check($task['event']['itemtype'] === 'TicketTask', 'Tarefa é criada pela classe nativa');
$timeline = actor_call($tech,6,$tid,fn($p,$t)=>dashglpi_attendance_timeline($t,[]));
$selfTimeline = actor_call($requester,1,$tid,fn($p,$t)=>dashglpi_attendance_timeline($t,[]));
check(in_array($private['event']['id'], array_column(array_filter($timeline['followups'], fn($e)=>$e['itemtype']==='ITILFollowup'), 'id'), true), 'Atendente autorizado lê nota interna');
check(!in_array($private['event']['id'], array_column(array_filter($selfTimeline['followups'], fn($e)=>$e['itemtype']==='ITILFollowup'), 'id'), true), 'Nota interna não está no JSON do solicitante');
$row = $DB->request(['FROM'=>'glpi_itilfollowups','WHERE'=>['id'=>$reply['event']['id']]])->current();
check((int)$row['users_id'] === $tech, 'Autoria persistida corresponde ao atendente');
$current = actor_call(2,4,$tid,fn($p,$t)=>dashglpi_attendance_detail($t));
mutate_as(2,4,$tid,['action'=>'update_properties','revision'=>$current['revision'],'changes'=>['name'=>'Novo título','urgency'=>4]],'dashglpi_attendance_update');
denied(fn()=>mutate_as(2,4,$tid,['action'=>'update_properties','revision'=>$current['revision'],'changes'=>['name'=>'Sobrescrita']], 'dashglpi_attendance_update'), 'Revisão desatualizada é rejeitada', 409);
$reloaded = actor_call(2,4,$tid,fn($p,$t)=>dashglpi_attendance_detail($t));
check($reloaded['name']==='Novo título' && (int)$reloaded['urgency']===4, 'Propriedades persistidas no GLPI');
check((int)$reloaded['priority'] === Ticket::computePriority(4, 3), 'Prioridade segue a matriz nativa');
denied(fn()=>mutate_as(2,4,$tid,['action'=>'update_properties','changes'=>['entities_id'=>$fixtures['child']]],'dashglpi_attendance_update'), 'Campo fora da allowlist é rejeitado', 422);
denied(fn()=>mutate_as(2,4,$tid,['action'=>'actor','operation'=>'add','role'=>'requester','itemtype'=>'User','items_id'=>$fixtures['outsider']],'dashglpi_attendance_update'), 'Inclusão de ator de outra entidade é rejeitada', 403);
denied(fn()=>mutate_as(2,4,$tid,['action'=>'update_status','status'=>4],'dashglpi_attendance_update'), 'Pendência sem motivo é rejeitada', 422);
mutate_as(2,4,$tid,['action'=>'update_status','status'=>4,'reason'=>'Aguardando retorno para teste'],'dashglpi_attendance_update');
$pending = actor_call(2,4,$tid,fn($p,$t)=>dashglpi_attendance_detail($t));
check((int)$pending['status']===Ticket::WAITING, 'Pendência e justificativa são persistidas pelo GLPI');
mutate_as(2,4,$tid,['action'=>'update_status','status'=>2],'dashglpi_attendance_update');
$planned = mutate_as($tech,6,$tid,['content'=>'Retorno planejado','users_id_tech'=>$tech,'begin'=>'2026-09-10 14:00:00','end'=>'2026-09-10 14:30:00'],fn($t,$p)=>dashglpi_attendance_add_event($t,$p,'task'));
$plannedRow = $DB->request(['FROM'=>'glpi_tickettasks','WHERE'=>['id'=>$planned['event']['id']]])->current();
check($plannedRow['begin']==='2026-09-10 14:00:00' && $plannedRow['end']==='2026-09-10 14:30:00', 'Planejamento da tarefa persiste início e fim');
$firstPage = actor_call($tech,6,$tid,fn($p,$t)=>dashglpi_attendance_timeline($t,['limit'=>1]));
$secondPage = actor_call($tech,6,$tid,fn($p,$t)=>dashglpi_attendance_timeline($t,['limit'=>1,'cursor'=>$firstPage['next_cursor']]));
check($firstPage['followups'][0]['itemtype'].':'.$firstPage['followups'][0]['id'] !== $secondPage['followups'][0]['itemtype'].':'.$secondPage['followups'][0]['id'], 'Paginação não repete eventos com a mesma data');
denied(fn()=>actor_call($tech,6,$tid,fn($p,$t)=>dashglpi_attendance_timeline($t,['cursor'=>'inválido'])), 'Cursor inválido é rejeitado', 400);
// Upload multipart real: o servidor PHP isolado exerce is_uploaded_file e GLPIUploadHandler.
$server = proc_open([PHP_BINARY, '-S', '127.0.0.1:18769', __DIR__ . '/ticket_attendance_upload_router.php'],
    [0=>['pipe','r'],1=>['file','/test/upload-server.log','a'],2=>['file','/test/upload-server.log','a']], $pipes);
try {
    for ($attempt=0;$attempt<50;$attempt++) {
        $health = @file_get_contents('http://127.0.0.1:18769/health');
        if ($health==='ok') break;
        usleep(50000);
    }
    check(($health ?? '')==='ok', 'Servidor de upload isolado disponível');
    file_put_contents('/test/pixel.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jZ1kAAAAASUVORK5CYII='));
    $curl = curl_init('http://127.0.0.1:18769/ticket_followup_config.php');
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['X-DashGLPI-Bridge-Token: integration-only'],
        CURLOPT_POSTFIELDS=>['payload'=>json_encode(['actor'=>['user_id'=>$tech,'profile_id'=>6],'ticket_id'=>$tid,'action'=>'add','content'=>'Imagem interna','is_private'=>1]),
            'attachments[0]'=>new CURLFile('/test/pixel.png','image/png','captura.png')]]);
    $uploadRaw = curl_exec($curl); $upload = json_decode($uploadRaw,true); curl_close($curl);
    check(!empty($upload['ok']), 'Upload multipart cria acompanhamento: ' . ($upload['error'] ?? 'ok'));
    $uploadId = (int)$upload['event']['id'];
    $privateDocs = actor_call($tech,6,$tid,fn($p,$t)=>dashglpi_attendance_documents($t,[['glpi_documents_items.itemtype'=>'ITILFollowup','glpi_documents_items.items_id'=>$uploadId]]));
    check(count($privateDocs)===1 && $privateDocs[0]['name']==='captura.png', 'Anexo mantém nome e vínculo com acompanhamento');
    $docId = $privateDocs[0]['id'];
    $requesterDocs = actor_call($requester,1,$tid,fn($p,$t)=>dashglpi_attendance_documents($t,null,$docId));
    check($requesterDocs===[], 'Documento privado é negado ao solicitante, inclusive por ID direto');
    $doc = (new Document())->getById($docId);
    check(is_file(GLPI_DOC_DIR . '/' . $doc->fields['filepath']), 'GLPI gravou o arquivo no armazenamento de teste');
    $curl = curl_init('http://127.0.0.1:18769/ticket_followup_config.php');
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-DashGLPI-Bridge-Token: invalid'],
        CURLOPT_POSTFIELDS=>json_encode(['payload'=>['actor'=>['user_id'=>2,'profile_id'=>4],'ticket_id'=>$tid,'action'=>'list']])]);
    $invalid = json_decode(curl_exec($curl),true); $httpCode = curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl);
    check($httpCode===403 && empty($invalid['ok']), 'Bridge HTTP rejeita token inválido');
    $surveyTicket = Session::callAsSystem(fn()=>fixture_ticket($requester));
    mutate_as(2,4,$surveyTicket,['content'=>'Solução para pesquisa'],fn($t,$p)=>dashglpi_attendance_add_event($t,$p,'solution'));
    mutate_as($requester,1,$surveyTicket,['action'=>'approve'],'dashglpi_attendance_solution_decision');
    Session::callAsSystem(fn()=>(new TicketSatisfaction())->add(['tickets_id'=>$surveyTicket,'type'=>1,'date_begin'=>date('Y-m-d H:i:s')]));
    foreach ([['ticket_satisfaction_config.php',$surveyTicket,['action'=>'submit','satisfaction'=>4,'comment'=>'Validado'],200],
        ['ticket_satisfaction_config.php',$surveyTicket,['action'=>'submit','satisfaction'=>3],500],
        ['ticket_cancel_config.php',$tid,['action'=>'cancel'],403]] as [$endpoint,$target,$input,$expected]) {
        $curl = curl_init('http://127.0.0.1:18769/'.$endpoint);
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-DashGLPI-Bridge-Token: integration-only'],
            CURLOPT_POSTFIELDS=>json_encode(['payload'=>['actor'=>['user_id'=>$requester,'profile_id'=>1],'ticket_id'=>$target]+$input])]);
        $body = curl_exec($curl); $httpCode=curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl);
        check($httpCode===$expected, "$endpoint retorna HTTP $expected: ".($httpCode===$expected?'ok':$body));
    }
} finally {
    proc_terminate($server); proc_close($server);
}

$solution = mutate_as($tech,6,$tid,['content'=>'Instalação de drivers e teste com o usuário.'],fn($t,$p)=>dashglpi_attendance_add_event($t,$p,'solution'));
$solved = actor_call($requester,1,$tid,fn($p,$t)=>dashglpi_attendance_detail($t));
check((int)$solved['status']===Ticket::SOLVED && $solved['capabilities']['approve'], 'Solução nativa aguarda aprovação do solicitante');
mutate_as($requester,1,$tid,['action'=>'refuse','reason'=>'Áudio ainda falha'], 'dashglpi_attendance_solution_decision');
$reopened = actor_call($requester,1,$tid,fn($p,$t)=>dashglpi_attendance_detail($t));
check((int)$reopened['status']===Ticket::ASSIGNED, 'Recusa reabre o chamado');
mutate_as($tech,6,$tid,['content'=>'Correção final validada.'],fn($t,$p)=>dashglpi_attendance_add_event($t,$p,'solution'));
mutate_as($requester,1,$tid,['action'=>'approve'], 'dashglpi_attendance_solution_decision');
$closed = actor_call($requester,1,$tid,fn($p,$t)=>dashglpi_attendance_detail($t));
check((int)$closed['status']===Ticket::CLOSED && !$closed['capabilities']['reply'], 'Aprovação fecha o chamado e atualiza capacidades');
foreach ([
    '<p onclick="alert(1)">Seguro<script>alert(1)</script></p>',
    '<div><iframe src="https://invalid.example"></iframe><a href="javascript:alert(1)">Link</a></div>',
    '<p><img src="https://invalid.example/pixel" onerror="alert(1)"></p>',
] as $html) {
    $safe = dashglpi_attendance_content($html, $tid)['content_html'];
    check(!preg_match('/<script|<iframe|onerror|onclick|javascript:|<img/i', $safe), 'HTML ativo e carregamentos externos são removidos');
}
$code = dashglpi_attendance_content('<pre>&lt;div class="exemplo"&gt;Código&lt;/div&gt;</pre>', $tid);
check(str_contains($code['content_html'],'&lt;div') && str_contains($code['content_text'],'<div'), 'Código HTML intencional permanece literal');
$empty = dashglpi_attendance_content('', $tid);
check($empty['content_text'] === '', 'Descrição vazia é renderizada sem erro');
$image = dashglpi_attendance_content('<img src="/front/document.send.php?docid=42" style="width:9999px">', $tid, [42=>['is_image'=>true]]);
check(str_contains($image['content_html'], 'document-preview.php?ticket_id=') && !str_contains($image['content_html'], 'style='), 'Imagem autorizada usa a rota autenticada sem estilos arbitrários');
$volumeTicket = Session::callAsSystem(fn()=>fixture_ticket($requester));
for ($index=0;$index<65;$index++) {
    mutate_as(2,4,$volumeTicket,['content'=>'Evento de volume '.$index],fn($t,$p)=>dashglpi_attendance_add_event($t,$p,'reply'));
}
$started = microtime(true); $cursor = null; $seen = []; $pages = 0;
do {
    $page = actor_call(2,4,$volumeTicket,fn($p,$t)=>dashglpi_attendance_timeline($t,['cursor'=>$cursor]));
    check(count($page['followups']) <= 30, 'Página de histórico respeita limite 30');
    foreach ($page['followups'] as $event) $seen[] = $event['itemtype'].':'.$event['id'];
    $cursor = $page['next_cursor']; $pages++;
    if ($pages>4) throw new RuntimeException('Cursor não terminou.');
} while ($cursor !== null);
check(count($seen)===65 && count(array_unique($seen))===65 && $pages===3, 'Histórico de 65 eventos é percorrido sem perdas ou duplicações');
echo sprintf("Pagination: 65 events / 3 pages in %.3f s (isolated fixture).\n",microtime(true)-$started);
$fixtureBytes = file_put_contents('/test/browser-fixtures.json', json_encode(['operator'=>$reloaded,'self'=>$self,'timeline'=>$timeline,'selfTimeline'=>$selfTimeline], JSON_UNESCAPED_UNICODE));
check($fixtureBytes !== false, 'Fixtures de navegador foram gravadas');
echo "Native integration: $passed checks passed.\n";
