// Testes reais de DOM/teclado/layout com respostas HTTP fictícias (PLAN-20260905-001).
// Sem dependências npm; Chrome DevTools Protocol via WebSocket nativo do Node 22+.
import { createServer } from 'node:http';
import { readFile, writeFile, mkdir, mkdtemp, rm } from 'node:fs/promises';
import { spawn } from 'node:child_process';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { once } from 'node:events';
import assert from 'node:assert/strict';
const app = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const fixturePath = process.env.ATTENDANCE_FIXTURES || '/tmp/kawa-attendance-test/browser-fixtures.json';
const fixtures = JSON.parse(await readFile(fixturePath, 'utf8'));
const output = process.env.ATTENDANCE_BROWSER_OUTPUT || '/tmp/kawa-attendance-test/browser';
await mkdir(output, { recursive: true });
const php = await readFile(join(app, 'front/dashboard.php'), 'utf8');
const start = php.indexOf('<div class="assignment-overlay" id="ticketDetailModal"');
const end = php.indexOf('<?php if ($canRanking)', start);
const modal = php.slice(start, end).replace(/<\?php[\s\S]*?\?>/g, '');
let posts = [], failNext = false;
const server = createServer(async (req, res) => {
    const url = new URL(req.url, 'http://localhost');
    if (url.pathname === '/') {
        res.setHeader('Content-Type', 'text/html');
        res.end(`<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/public/css/style.css"><body><article id="open" tabindex="0" data-ticket-detail="${fixtures.operator.id}">Abrir chamado</article>${modal}<script>
        const PLUGIN_ROOT = '', DASHGLPI_CSRF_TOKEN = 'fixture', DASHGLPI_IS_HELPDESK_VIEW = false;
        const DashState = { followupCreateState: { attachments: [], uploadMaxLabel: '' }, followupAttachmentSequence: 1 };
        const escHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        const formatDateTime = value => value || '—';
        const getStatusLabel = value => String(value);
        const loadTicketLists = async () => { window.listRefreshes = (window.listRefreshes || 0) + 1; };
        </script><script src="/public/js/script.ticket-create.js"></script><script src="/public/js/script.self-service.js"></script><script src="/public/js/script.ticket-attendance.js"></script><script>initSelfServiceActions();</script></body></html>`);
        return;
    }
    if (url.pathname.startsWith('/public/')) {
        try { const path = resolve(app, '.' + url.pathname); assert(path.startsWith(app + '/public/')); res.setHeader('Content-Type', path.endsWith('.css') ? 'text/css' : 'text/javascript'); res.end(await readFile(path)); } catch { res.writeHead(404).end(); }
        return;
    }
    res.setHeader('Content-Type', 'application/json');
    if (req.method === 'POST') {
        let body = ''; for await (const part of req) body += part;
        posts.push({ path: url.pathname, body });
        if (failNext) { failNext = false; res.writeHead(500).end(JSON.stringify({ok:false,error:'Falha de teste'})); return; }
        res.end(JSON.stringify({ok:true,event:{id:900,itemtype:'ITILFollowup'}})); return;
    }
    const self = url.searchParams.get('ticket_id') === String(fixtures.self.id + 1000);
    if (url.pathname.endsWith('ticket_detail.php')) {
        const ticket = structuredClone(self ? fixtures.self : fixtures.operator);
        if (self) ticket.id += 1000;
        if (url.searchParams.has('itemtype')) {
            delete ticket.capabilities; ticket.itemtype='problem'; ticket.followups=[];
        }
        res.end(JSON.stringify({ok:true,ticket}));
    } else if (url.pathname.endsWith('ticket_followup.php')) res.end(JSON.stringify({ok:true,...(self ? fixtures.selfTimeline : fixtures.timeline)}));
    else if (url.pathname.endsWith('ticket_attendance.php')) res.end(JSON.stringify({ok:true,catalog:{users:[],technicians:[],groups:[],categories:[],taskcategories:[],pendingreasons:[],solutiontypes:[],has_more:{}}}));
    else res.writeHead(404).end('{}');
});
server.listen(0, '127.0.0.1'); await once(server, 'listening');
const base = `http://127.0.0.1:${server.address().port}`;
const profile = await mkdtemp('/tmp/kawa-attendance-chrome-');
const chrome = spawn(process.env.CHROME_BIN || '/usr/bin/google-chrome', ['--headless=new','--no-sandbox','--disable-dev-shm-usage','--disable-gpu', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], {stdio:['ignore','ignore','pipe']});
let diagnostics = ''; chrome.stderr.on('data', data => { diagnostics += data; });
let socket;
try {
    let port;
    for (let i=0;i<100;i++) {
        try { port = (await readFile(join(profile, 'DevToolsActivePort'),'utf8')).split('\n')[0]; break; } catch { await new Promise(r=>setTimeout(r,50)); }
    }
    assert(port, `Chrome não iniciou: ${diagnostics}`);
    const pages = await (await fetch(`http://127.0.0.1:${port}/json`)).json();
    socket = new WebSocket(pages.find(p=>p.type==='page').webSocketDebuggerUrl);
    await once(socket, 'open');
    let nextId=0; const pending = new Map(), errors=[];
    socket.addEventListener('message', event => {
        const data=JSON.parse(event.data);
        if (data.id) { const p=pending.get(data.id); pending.delete(data.id); data.error ? p.reject(new Error(JSON.stringify(data.error))) : p.resolve(data.result); }
        if (data.method==='Runtime.exceptionThrown') errors.push(data.params.exceptionDetails.exception?.description || data.params.exceptionDetails.text);
    });
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++nextId; pending.set(id,{resolve,reject});socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.exception?.description);return r.result.value;};
    const waitFor=async expression=>{for(let i=0;i<100;i++){if(await evaluate(expression))return;await new Promise(r=>setTimeout(r,30));}throw new Error('Timeout: '+expression+'; '+JSON.stringify(errors)+'; '+await evaluate("document.getElementById('ticketDetailSubtitle')?.textContent"));};
    await send('Page.enable'); await send('Runtime.enable');
    let passed=0;const check=(condition,label)=>{assert(condition,label);passed++;console.log('PASS: '+label);};
    for (const width of [375,768,1024,1440]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
        await send('Page.navigate',{url:base});
        await waitFor("typeof DashAttendance !== 'undefined'");
        await evaluate("document.getElementById('open').click()");
        await waitFor('DashAttendance.ticket && DashAttendance.events.length > 0');
        await waitFor("getComputedStyle(document.getElementById('ticketDetailModal')).opacity === '1'");
        const size=await evaluate(`(()=>{const el=document.querySelector('.ticket-detail-dialog'), r=el.getBoundingClientRect();return {width:r.width,right:r.right,bottom:r.bottom,scroll:document.documentElement.scrollWidth,screen:innerWidth,operator:document.getElementById('ticketDetailModal').classList.contains('is-operator'),collapsed:!document.getElementById('attendanceProperties').open,html:document.getElementById('ticketDetailContent').textContent};})()`);
        check(size.operator && size.right <= width+1 && size.scroll <= width+1 && size.bottom <= 901, `Modal sem overflow em ${width}px`);
        if(width<=768)check(size.collapsed, `Propriedades recolhidas em ${width}px`);
        check(!size.html.includes('class=') && size.html.includes('Áudio'), `Descrição legível em ${width}px`);
        await writeFile(join(output, `attendance-${width}.png`),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    }
    await evaluate("document.getElementById('attendanceExpand').click()");
    check(await evaluate("document.getElementById('ticketDetailModal').classList.contains('is-expanded') && document.getElementById('attendanceExpand').getAttribute('aria-pressed')==='true'"), 'Expandir mantém estado acessível');
    await evaluate("document.getElementById('attendanceExpand').click()");
    await send('Emulation.setDeviceMetricsOverride',{width:720,height:450,deviceScaleFactor:2,mobile:false});
    check(await evaluate("document.documentElement.scrollWidth<=innerWidth && document.querySelector('.ticket-detail-dialog').getBoundingClientRect().bottom<=innerHeight"), 'Layout cabe na área equivalente a zoom de 200%');
    await send('Emulation.setDeviceMetricsOverride',{width:375,height:400,deviceScaleFactor:1,mobile:true});
    await evaluate("document.getElementById('attendanceGoToComposer').click()");
    check(await evaluate("document.activeElement.id==='followupContent' && document.querySelector('.ticket-detail-footer').getBoundingClientRect().bottom<=innerHeight"), 'Atender mantém campo e rodapé acessíveis com viewport reduzido');
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
    await evaluate('DashAttendance.focusStatus(5)');
    check(await evaluate("document.getElementById('attendanceKind').value==='solution' && document.activeElement.id==='followupContent'"), 'Atalho Solucionado abre registro de solução');
    await evaluate('DashAttendance.focusStatus(4)');
    check(await evaluate("document.getElementById('attendanceStatus').value==='4' && document.activeElement.id==='attendancePendingReason'"), 'Atalho Pendente exige motivo na modal');
    const quickCount = posts.length;
    await evaluate(`Promise.all([dashglpiAttendanceQuickUpdate(${fixtures.operator.id},{action:'take'}),dashglpiAttendanceQuickUpdate(${fixtures.operator.id},{action:'take'})])`);
    check(posts.length===quickCount+1 && posts.at(-1).body.includes('name="revision"'), 'Assumir pela fila envia revisão e bloqueia duplo envio');
    await evaluate("document.getElementById('attendanceKind').value='task'; document.getElementById('attendanceKind').dispatchEvent(new Event('change'))");
    check(await evaluate("!document.getElementById('attendanceTaskFields').classList.contains('is-hidden') && document.getElementById('attendanceSolutionFields').classList.contains('is-hidden')"), 'Tipo tarefa mostra os campos específicos');
    await evaluate("document.getElementById('attendanceKind').value='private'; document.getElementById('attendanceKind').dispatchEvent(new Event('change'))");
    check(await evaluate("document.getElementById('followupForm').classList.contains('is-private')"), 'Nota interna tem identificação visual');
    failNext=true;
    await evaluate("document.getElementById('followupContent').value='Rascunho preservado'; document.getElementById('followupForm').requestSubmit()");
    await waitFor("!DashAttendance.busy && document.getElementById('followupStatus').textContent.includes('Falha de teste')");
    check(await evaluate("document.getElementById('followupContent').value==='Rascunho preservado'"), 'Falha de gravação preserva rascunho');
    const count=posts.length;
    await evaluate("document.getElementById('followupForm').requestSubmit(); document.getElementById('followupForm').requestSubmit()");
    await waitFor('!DashAttendance.busy && window.listRefreshes > 0');
    check(posts.length===count+1, 'Duplo envio resulta em apenas um POST');
    check(posts.at(-1).body.includes('name="revision"') && posts.at(-1).body.includes('name="csrf_token"') && posts.at(-1).body.includes('name="is_private"'), 'Gravação envia revisão, CSRF e visibilidade');
    check(await evaluate("document.getElementById('followupContent').value===''"), 'Confirmação limpa o texto enviado');
    await evaluate("document.getElementById('attendanceEditDescription').click(); document.getElementById('attendanceDescription').value='Descrição revisada'");
    check(await evaluate('DashAttendance.dirty()'), 'Edição da descrição ativa proteção de descarte');
    await evaluate("document.getElementById('attendanceCancelDescription').click(); document.querySelector('#ticketDetailModal [data-modal-close]').focus()");
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape'});
    check(await evaluate("!document.getElementById('ticketDetailModal').classList.contains('active') && document.activeElement.id==='open'"), 'Escape fecha e devolve o foco ao chamado');
    await evaluate(`openTicketDetailModal(${fixtures.self.id+1000})`);
    await waitFor("DashAttendance.ticket?.capabilities.mode === 'self_service'");
    check(await evaluate("document.getElementById('attendanceProperties').classList.contains('is-hidden') && document.getElementById('attendanceKindLabel').classList.contains('is-hidden')"), 'Self-Service não exibe propriedades ou ações operacionais');
    check(await evaluate("!document.getElementById('followupTimeline').textContent.includes('Diagnóstico interno')"), 'Self-Service não exibe nota privada');
    await evaluate(`openTicketDetailModal(${fixtures.operator.id},{itemtype:'problem'})`);
    await waitFor("document.getElementById('ticketDetailSubtitle').textContent !== 'Carregando...'");
    check(await evaluate("document.querySelector('.ticket-detail-col-followup').classList.contains('is-hidden')"), 'Problema mantém o modo de leitura');
    check(errors.length===0, 'Nenhuma exceção JavaScript durante os fluxos');
    await writeFile(join(output,'result.json'), JSON.stringify({passed,errors,posts:posts.map(p=>p.path)},null,2));
    console.log(`Browser: ${passed} checks passed. Screenshots: ${output}`);
} finally {
    socket?.close(); chrome.kill('SIGTERM'); await new Promise(r=>setTimeout(r,150)); server.closeAllConnections(); server.close(); await rm(profile,{recursive:true,force:true});
}
