# PowerShell script for flat backup with include logic on Windows

# Fixed directories
$SOURCE_DIR = "D:\GitHub\Windows\glpi_app"
$DEST_DIR = "D:\GitHub\BKPS\Windows\glpi_app"

# Check if source directory exists
if (-not (Test-Path -Path $SOURCE_DIR -PathType Container)) {
    Write-Host "Erro: Diretorio fonte nao encontrado: $SOURCE_DIR" -ForegroundColor Red
    exit 1
}

# Create destination directory if it doesn't exist
New-Item -ItemType Directory -Path $DEST_DIR -Force | Out-Null
if ($?) {
    Write-Host "Diretorio de destino criado: $DEST_DIR" -ForegroundColor Green
}

# Create timestamp for backup name
$TIMESTAMP = Get-Date -Format "yyyyMMdd_HHmmss"
$BACKUP_DIR = Join-Path -Path $DEST_DIR -ChildPath $TIMESTAMP

# Create backup directory
New-Item -ItemType Directory -Path $BACKUP_DIR -Force | Out-Null

# List of directories to INCLUDE in backup
$INCLUDE_DIRS = @(
    
    "/"
    # "/config",
    # "/docs",
    # "/scripts"
)

# List of file extensions to INCLUDE
$INCLUDE_EXTENSIONS = @(
    "*.ts",
    "*.vue",
    "*.conf",    
    "*.php",
    "*.ini",
    "*.sh",
    "*.yml",
    "Dockerfile",
    "Project_Structure.md"
)

# Function to check if file should be included
function Test-FileIncluded {
    param (
        [string]$FilePath
    )
    $filename = Split-Path -Path $FilePath -Leaf
    
    # Check against include extensions/patterns
    foreach ($pattern in $INCLUDE_EXTENSIONS) {
        if ($filename -like $pattern) {
            return $true
        }
    }
    return $false
}

# Function to perform flat backup with include logic
function Invoke-FlatBackup {
    Write-Host "Iniciando backup com estrutura plana (modo INCLUDE)..." -ForegroundColor Cyan
    Write-Host "Origem: $SOURCE_DIR" -ForegroundColor Cyan
    Write-Host "Destino: $BACKUP_DIR" -ForegroundColor Cyan

    $fileCount = 0
    $errorCount = 0
    $successFiles = @()
    $errorFiles = @()
    $files = @()

    # First, get files directly in the source directory (root level)
    Get-ChildItem -Path $SOURCE_DIR -File | ForEach-Object {
        if (Test-FileIncluded -FilePath $_.FullName) {
            $files += $_.FullName
        }
    }

    # Then, ONLY process directories that are in the INCLUDE_DIRS list
    foreach ($includeDir in $INCLUDE_DIRS) {
        $dirPath = Join-Path -Path $SOURCE_DIR -ChildPath $includeDir
        if (Test-Path -Path $dirPath -PathType Container) {
            Write-Host "Processando diretorio: $includeDir" -ForegroundColor Yellow
            Get-ChildItem -Path $dirPath -File -Recurse | ForEach-Object {
                if (Test-FileIncluded -FilePath $_.FullName) {
                    $files += $_.FullName
                }
            }
        }
    }

    $totalFiles = $files.Length
    Write-Host "Total de arquivos a copiar: $totalFiles" -ForegroundColor Cyan

    if ($totalFiles -eq 0) {
        Write-Host "Aviso: Nenhum arquivo encontrado com os criterios de inclusao definidos." -ForegroundColor Yellow
        Write-Host "Verifique se os diretorios e extensoes de arquivo estao corretos." -ForegroundColor Cyan
        return $false
    }

    # Copy each file to backup directory
    foreach ($file in $files) {
        $filename = Split-Path -Path $file -Leaf
        $destFile = Join-Path -Path $BACKUP_DIR -ChildPath $filename

        # Handle duplicate filenames
        if (Test-Path -Path $destFile) {
            $counter = 1
            $filenameNoExt = [System.IO.Path]::GetFileNameWithoutExtension($filename)
            $fileExt = [System.IO.Path]::GetExtension($filename)
            while (Test-Path -Path $destFile) {
                $newFilename = "${filenameNoExt}_${counter}${fileExt}"
                $destFile = Join-Path -Path $BACKUP_DIR -ChildPath $newFilename
                $counter++
            }
        }

        # Copy file
        try {
            Copy-Item -Path $file -Destination $destFile -ErrorAction Stop
            $fileCount++
            $successFiles += $filename
            # Show progress
            if ($fileCount % 10 -eq 0 -or $fileCount -eq $totalFiles) {
                Write-Host "Progresso: $fileCount/$totalFiles arquivos copiados" -ForegroundColor Green
            }
        }
        catch {
            $errorCount++
            $errorFiles += "$file : Falha na copia"
            Write-Host "Erro ao copiar $file" -ForegroundColor Yellow
        }
    }

    # Check if backup was successful
    if ($fileCount -gt 0) {
        Write-Host "`nBackup concluido!" -ForegroundColor Green
        Write-Host "Arquivos copiados: $fileCount de $totalFiles" -ForegroundColor Green

        if ($errorCount -gt 0) {
            Write-Host "Atençao: $errorCount arquivos nao puderam ser copiados." -ForegroundColor Yellow
        }

        # Create log file
        $LOG_FILE = Join-Path -Path $BACKUP_DIR -ChildPath "backup_log.txt"
        $logContent = @"
Backup realizado em: $(Get-Date)
Diretorio fonte: $SOURCE_DIR
Diretorio destino: $BACKUP_DIR
Total de arquivos copiados: $fileCount

Criterios de inclusao utilizados:
Diretorios incluidos:
$($INCLUDE_DIRS | ForEach-Object { "  - $_" })

Extensoes de arquivo incluidas:
$($INCLUDE_EXTENSIONS | ForEach-Object { "  - $_" })

Arquivos incluidos no backup:
$($successFiles | Sort-Object)
"@
        if ($errorCount -gt 0) {
            $logContent += "`nArquivos que nao puderam ser copiados:`n$($errorFiles | ForEach-Object { $_ })"
        }
        $logContent | Out-File -FilePath $LOG_FILE -Encoding UTF8

        return $true
    }
    else {
        Write-Host "Erro: Nenhum arquivo foi copiado!" -ForegroundColor Red
        if (Test-Path -Path $BACKUP_DIR) {
            Remove-Item -Path $BACKUP_DIR -Recurse -Force
        }
        return $false
    }
}

# Execute backup
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Script de Backup - Modo INCLUDE" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

if (Invoke-FlatBackup) {
    Write-Host "`nOs arquivos foram salvos em: $BACKUP_DIR" -ForegroundColor Green
    Write-Host "Um log do backup foi criado em: $BACKUP_DIR\backup_log.txt" -ForegroundColor Green
}
else {
    Write-Host "`nO backup falhou." -ForegroundColor Red
    if (Test-Path -Path $BACKUP_DIR) {
        Write-Host "Removendo diretorio de backup incompleto..." -ForegroundColor Yellow
        Remove-Item -Path $BACKUP_DIR -Recurse -Force
    }
}