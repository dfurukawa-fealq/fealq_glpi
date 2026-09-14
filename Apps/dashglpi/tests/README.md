# Validação da modal de atendimento

Suítes do `PLAN-20260905-001`, sem dependências novas na aplicação. Integração validada com GLPI 11.0.7, PHP 8.5.7 e MySQL 8.0. Gates standalone validados com PHP 8.3.33. Navegador: Node 22+ e Chrome instalado.

## Ambiente descartável

Use imagens locais já construídas, `kawa_glpi-glpi` e `kawa_glpi-dashglpi`. Nunca monte a configuração, arquivos ou banco operacional. Os containers abaixo não têm rede; o banco de teste comunica apenas por socket Unix. O router HTTP existe somente no container temporário, sem porta publicada. Notificações ficam desativadas nos testes.

Na raiz do repositório, prepare uma pasta de teste e o MySQL descartável:

```bash
ATTENDANCE_APP_DIR="$PWD/Apps/dashglpi"
ATTENDANCE_TEST_DIR="$(mktemp -d /tmp/kawa-attendance-test-XXXXXX)"
mkdir -p "$ATTENDANCE_TEST_DIR/config" "$ATTENDANCE_TEST_DIR/socket"
chmod 0777 "$ATTENDANCE_TEST_DIR" "$ATTENDANCE_TEST_DIR/config" "$ATTENDANCE_TEST_DIR/socket"
docker run --rm -d --name kawa-attendance-test-db --network none \
  -e MYSQL_ALLOW_EMPTY_PASSWORD=yes \
  --mount "type=bind,src=$ATTENDANCE_TEST_DIR/socket,dst=/test-socket" \
  mysql:8.0 --skip-networking --socket=/test-socket/mysql.sock
docker exec kawa-attendance-test-db mysqladmin --socket=/test-socket/mysql.sock -uroot ping
```

Depois de o banco responder ao `ping`, instale uma base nova:

```bash
docker run --rm --network none --entrypoint php \
  --mount "type=bind,src=$ATTENDANCE_TEST_DIR,dst=/test" \
  kawa_glpi-glpi /var/www/glpi/bin/console db:install \
  --config-dir=/test/config --db-host=localhost --db-port=/test/socket/mysql.sock \
  --db-name=attendance_test --db-user=root --db-password= \
  --no-interaction --no-telemetry --default-language=pt_BR
```

O uso de senha vazia é exclusivo deste banco descartável sem rede. Nenhuma credencial operacional é necessária.

## Executar

Integração real de classes GLPI, autorização, eventos, tarefas, propriedades, solução, anexos multipart, satisfação e paginação:

```bash
docker run --rm --network none --entrypoint php \
  -e DASHGLPI_TEST_CONFIG=/test/config \
  --mount "type=bind,src=$ATTENDANCE_TEST_DIR,dst=/test" \
  --mount "type=bind,src=$ATTENDANCE_APP_DIR,dst=/app,readonly" \
  --mount "type=bind,src=$ATTENDANCE_APP_DIR,dst=/var/www/glpi/plugins/dashglpi,readonly" \
  kawa_glpi-glpi /app/tests/ticket_attendance_native.php
```

O teste verifica nome da base e socket antes de qualquer fixture. Cria usuários/chamados fictícios pelas classes GLPI e exporta `browser-fixtures.json`. O token de bridge usado pelo router é fictício e só existe no processo de teste. Não publique o router como endpoint da aplicação.

Gates de sessão dos oito endpoints e rejeição de CSRF no wrapper compartilhado, sem banco:

```bash
docker run --rm --network none --entrypoint php -e DASHGLPI_ATTENDANCE_TEST=1 \
  --mount "type=bind,src=$ATTENDANCE_APP_DIR,dst=/app,readonly" \
  kawa_glpi-dashglpi /app/tests/ticket_attendance_session.php
```

DOM real da modal, CSS e JavaScript reais, com respostas HTTP fictícias baseadas nas fixtures nativas:

```bash
ATTENDANCE_FIXTURES="$ATTENDANCE_TEST_DIR/browser-fixtures.json" \
ATTENDANCE_BROWSER_OUTPUT="$ATTENDANCE_TEST_DIR/browser" \
node Apps/dashglpi/tests/ticket_attendance_browser.mjs
```

O navegador grava quatro screenshots e `result.json`. `CHROME_BIN` permite escolher outro executável Chrome. A suíte inicia Chrome temporário sem sandbox, com perfil descartável; execute somente com as fixtures locais conhecidas. O perfil e servidor local são encerrados ao finalizar.

Ao concluir, remova somente o banco criado para esta suíte:

```bash
docker stop kawa-attendance-test-db
```

## Limites das evidências

Os testes não publicam código, não acessam chamados reais e não enviam mensagens. O teste de CSRF exerce o wrapper real; não representa login completo do standalone conectado ao bridge. O navegador usa endpoints simulados. Zoom é exercido por viewport equivalente, e teclado virtual por redução da área disponível; validar também em aparelho real. Notificações, regras particulares de SLA/entidade, dados históricos migrados e concorrência com o frontend nativo exigem homologação da instalação.
