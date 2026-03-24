# =============================================================================
# Script: descargar-pedidos-s3.ps1
# Descripcion: Descarga archivos .PE0 desde S3 (eurobelleza-siesa/pedidos/)
#              a una ruta local en Windows y los elimina del bucket.
#
#   S3 actua como canal de transferencia: Laravel sube el archivo, Windows
#   lo descarga y lo elimina. El historico queda en el servidor local de Laravel.
#
# Requisitos:
#   - AWS CLI instalado: https://aws.amazon.com/cli/
#   - Credenciales configuradas con: aws configure --profile eurobelleza
#
# Configuracion inicial (ejecutar una sola vez en la PC):
#   aws configure --profile eurobelleza
#   > AWS Access Key ID: (clave de usuario eurobelleza-windows)
#   > AWS Secret Access Key: (secreto de usuario eurobelleza-windows)
#   > Default region name: us-east-2
#   > Default output format: json
#
# Task Scheduler:
#   - Programa: powershell.exe
#   - Argumentos: -ExecutionPolicy Bypass -File "C:\ruta\descargar-pedidos-s3.ps1"
#   - Disparador: Repetir cada $IntervalMinutes minutos
# =============================================================================

# --- CONFIGURACION -----------------------------------------------------------
$BucketName      = "eurobelleza-siesa"
$S3Prefix        = "pedidos/"
$LocalPath       = "C:\eurobelleza\pedidos"       # Ruta local donde SIESA lee los PE0
$AwsProfile      = "eurobelleza"
$AwsRegion       = "us-east-2"
$LogFile         = "C:\eurobelleza\logs\descarga.log"
$IntervalMinutes = 30                              # Solo informativo, el intervalo real se define en Task Scheduler
# -----------------------------------------------------------------------------

# Crear carpetas si no existen
if (-not (Test-Path $LocalPath))   { New-Item -ItemType Directory -Path $LocalPath -Force | Out-Null }
if (-not (Test-Path (Split-Path $LogFile))) { New-Item -ItemType Directory -Path (Split-Path $LogFile) -Force | Out-Null }

function Write-Log {
    param([string]$Message)
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $line = "[$timestamp] $Message"
    Write-Host $line
    Add-Content -Path $LogFile -Value $line
}

Write-Log "=== Iniciando descarga de pedidos desde S3 ==="

# Listar archivos .PE0 en el bucket (busca recursivamente dentro de pedidos/)
try {
    $objects = aws s3api list-objects-v2 `
        --bucket $BucketName `
        --prefix $S3Prefix `
        --query "Contents[?ends_with(Key, '.PE0')].[Key]" `
        --output text `
        --profile $AwsProfile `
        --region $AwsRegion

    if ([string]::IsNullOrWhiteSpace($objects) -or $objects -eq "None") {
        Write-Log "No hay archivos PE0 pendientes de descarga."
        exit 0
    }
} catch {
    Write-Log "ERROR al listar archivos en S3: $_"
    exit 1
}

# Procesar cada archivo
$files = $objects -split "`n" | Where-Object { $_.Trim() -ne "" }
$downloaded = 0
$errors     = 0

foreach ($s3Key in $files) {
    $s3Key = $s3Key.Trim()
    if ([string]::IsNullOrWhiteSpace($s3Key)) { continue }

    # Nombre del archivo (sin la carpeta de fecha)
    $fileName = Split-Path $s3Key -Leaf

    # Destino local: $LocalPath\{nombre}.PE0
    $localFile = Join-Path $LocalPath $fileName

    Write-Log "Descargando: $s3Key -> $localFile"

    try {
        # Descargar archivo
        aws s3 cp "s3://$BucketName/$s3Key" $localFile `
            --profile $AwsProfile `
            --region $AwsRegion | Out-Null

        if (Test-Path $localFile) {
            Write-Log "  OK - Descargado: $fileName"

            # Eliminar del S3 para no volver a procesarlo
            aws s3 rm "s3://$BucketName/$s3Key" `
                --profile $AwsProfile `
                --region $AwsRegion | Out-Null

            Write-Log "  OK - Eliminado de S3: $s3Key"
            $downloaded++
        } else {
            Write-Log "  ERROR - El archivo no se encontro localmente despues de la descarga: $fileName"
            $errors++
        }
    } catch {
        Write-Log "  ERROR al procesar $s3Key : $_"
        $errors++
    }
}

Write-Log "=== Fin del proceso. Descargados: $downloaded | Errores: $errors ==="
