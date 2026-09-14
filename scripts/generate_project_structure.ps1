# Caminhos absolutos (ajuste conforme necessário)
$SOURCE_DIR = "D:\GitHub\Windows\glpi_app"
$DEST_DIR = "D:\GitHub\Windows\glpi_app\docs"
$OUTPUT_FILE = Join-Path $DEST_DIR "Project_Structure.md"

function Write-Line {
    param (
        [string]$Line
    )
    Add-Content -Path $OUTPUT_FILE -Value $Line
}

function Print-Tree {
    param (
        [string]$Path,
        [string]$Indent = ""
    )

    $items = Get-ChildItem -LiteralPath $Path -Force | Sort-Object { -not $_.PSIsContainer }, Name

    foreach ($item in $items) {
        $isDir = $item.PSIsContainer
        $name = $item.Name

        if ($isDir) {
            Write-Line "$Indent|-- $name/"
            Print-Tree -Path $item.FullName -Indent "$Indent|   "
        } else {
            Write-Line "$Indent|-- $name"
        }
    }
}

# Cabeçalho
Set-Content -Path $OUTPUT_FILE -Value "## Directory Tree`n"
Write-Line "glpi_app/"

# Pastas específicas
$folders = @(
    "public",
    "scripts",
    "src",
    "config",
    "utils"
)

foreach ($folder in $folders) {
    $fullPath = Join-Path $SOURCE_DIR $folder
    if (Test-Path $fullPath) {
        Write-Line "|-- $folder/"
        Print-Tree -Path $fullPath -Indent "|   "
    }
}

# Arquivos do nível raiz
$rootFiles = Get-ChildItem -LiteralPath $SOURCE_DIR -File -Force | Sort-Object Name
foreach ($file in $rootFiles) {
    Write-Line "|-- $($file.Name)"
}
