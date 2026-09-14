[CmdletBinding()]
param(
    [string]$ContainerName = 'dashglpi-app',
    [string]$HtmlTemplatePath = 'Apps/templates/Hafen/Add Followup.html',
    [string]$TemplateName = 'Tickets Acompanhamento',
    [string]$SourceTemplateName = 'Tickets',
    [string]$Language = 'pt_BR',
    [string]$Subject = '##ticket.title## - ##ticket.action##',
    [switch]$IncludeDeleteFollowup,
    [switch]$Apply
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Resolve-RepoPath {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path
    )

    if ([System.IO.Path]::IsPathRooted($Path)) {
        return (Resolve-Path -LiteralPath $Path).Path
    }

    $repoRoot = Split-Path -Parent $PSScriptRoot
    return (Resolve-Path -LiteralPath (Join-Path $repoRoot $Path)).Path
}

function New-FollowupTextTemplate {
    return @'
Informamos que houve uma atualizacao em um chamado ja existente em nossa Central de Servicos. Abaixo seguem os dados do atendimento e o ultimo acompanhamento registrado.

Titulo: ##ticket.title##
Unidade de Negocio: ##ticket.shortentity##

Ultimo acompanhamento registrado:
##FOREACH LAST 1 followups##
Autor do acompanhamento: ##followup.author##
Data/Hora do acompanhamento: ##followup.date##
Conteudo do acompanhamento:
##followup.description##
##ENDFOREACHfollowups##

O prazo previsto para este atendimento permanece firmado para ##ticket.duedate##.
Seguiremos com o atendimento e retornaremos caso haja novas informacoes relevantes.
Este atendimento esta sendo gerenciado sob o protocolo ##ticket.id##.
Acesse a plataforma de atendimento: ##ticket.url##
'@
}

$helperScriptPath = Resolve-RepoPath -Path 'scripts/configure_followup_notification.php'
$htmlPath = Resolve-RepoPath -Path $HtmlTemplatePath
$htmlContent = Get-Content -LiteralPath $htmlPath -Raw

if ($htmlContent -notmatch '##FOREACH LAST 1 followups##') {
    throw "O HTML de acompanhamento precisa usar '##FOREACH LAST 1 followups##' para o ultimo follow-up."
}

if ($htmlContent -notmatch '##ENDFOREACHfollowups##') {
    throw "O HTML de acompanhamento precisa fechar '##ENDFOREACHfollowups##'."
}

$events = @('add_followup', 'update_followup')
$notificationNames = @('Add Followup', 'Update Followup')

if ($IncludeDeleteFollowup.IsPresent) {
    $events += 'delete_followup'
    $notificationNames += 'Delete Followup'
}

$payload = [ordered]@{
    itemtype             = 'Ticket'
    template_name        = $TemplateName
    source_template_name = $SourceTemplateName
    language             = $Language
    subject              = $Subject
    content_html         = $htmlContent
    content_text         = New-FollowupTextTemplate
    events               = $events
    notification_names   = $notificationNames
    dry_run              = -not $Apply.IsPresent
}

$localPayloadPath = Join-Path ([System.IO.Path]::GetTempPath()) ("dashglpi-followup-" + [guid]::NewGuid().ToString('N') + '.json')
$remoteHelperPath = '/tmp/dashglpi-followup-config.php'
$remotePayloadPath = '/tmp/dashglpi-followup-payload.json'

try {
    $payload | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath $localPayloadPath -Encoding utf8

    docker cp $helperScriptPath "${ContainerName}:$remoteHelperPath"
    docker cp $localPayloadPath "${ContainerName}:$remotePayloadPath"

    $rawResult = docker exec $ContainerName php $remoteHelperPath $remotePayloadPath
    if ([string]::IsNullOrWhiteSpace($rawResult)) {
        throw 'O helper PHP nao retornou nenhum resultado.'
    }

    $result = $rawResult | ConvertFrom-Json
    $result | ConvertTo-Json -Depth 8
} finally {
    if (Test-Path -LiteralPath $localPayloadPath) {
        Remove-Item -LiteralPath $localPayloadPath -Force
    }

    try {
        docker exec $ContainerName sh -lc "rm -f $remoteHelperPath $remotePayloadPath" | Out-Null
    } catch {
    }
}
