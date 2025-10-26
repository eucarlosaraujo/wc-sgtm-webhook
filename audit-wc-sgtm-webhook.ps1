# ============================
# audit-wc-sgtm-webhook.ps1
# Auditoria rápida para WP/WC (PowerShell)
# ============================

$ErrorActionPreference = 'Stop'
$ReportName = "audit_report_{0}.txt" -f (Get-Date -Format "yyyyMMdd_HHmmss")
$ReportPath = Join-Path -Path (Get-Location) -ChildPath $ReportName

function Write-Header($title) {
    $line = "`n[$title]`n" + ('-' * $title.Length)
    $line | Tee-Object -FilePath $ReportPath -Append | Out-Host
}

"=== WC-SGTM-WEBHOOK AUDIT ($((Get-Date))) ===" | Tee-Object -FilePath $ReportPath | Out-Host

# 1) PHP Lint (syntaxe)
Write-Header "1) PHP Lint (php -l)"
$phpFiles = Get-ChildItem -Recurse -Filter *.php | Select-Object -ExpandProperty FullName
if (-not $phpFiles) {
    "Nenhum arquivo PHP encontrado." | Tee-Object -FilePath $ReportPath -Append | Out-Host
} else {
    foreach ($f in $phpFiles) {
        try {
            $result = & php -l $f 2>&1
            $result | Tee-Object -FilePath $ReportPath -Append | Out-Host
        } catch {
            "ERRO ao rodar php -l em $f : $($_.Exception.Message)" | Tee-Object -FilePath $ReportPath -Append | Out-Host
        }
    }
}

# 2) Padrões perigosos
Write-Header "2) Padrões perigosos (eval, exec, base64_decode, etc.)"
$dangerPatterns = '(eval\()|(base64_decode\()|(create_function\()|(system\()|(exec\()|(shell_exec\()|(passthru\()|(popen\()'
$dangerHits = Select-String -Path $phpFiles -Pattern $dangerPatterns -CaseSensitive
if ($dangerHits) { $dangerHits | ForEach-Object { "$($_.Path):$($_.LineNumber): $($_.Line)".Trim() } | Tee-Object -FilePath $ReportPath -Append | Out-Host }
else { "OK - nenhum padrão perigoso encontrado." | Tee-Object -FilePath $ReportPath -Append | Out-Host }

# 3) Falta de guard ABSPATH
Write-Header "3) Falta de guard ABSPATH (defined('ABSPATH'))"
$missingAbspath = @()
foreach ($f in $phpFiles) {
    try {
        $head = Get-Content -Path $f -TotalCount 5 -ErrorAction Stop
        $hasGuard = $head -join "`n" | Select-String -SimpleMatch "defined( 'ABSPATH' )"
        if (-not $hasGuard) { $missingAbspath += $f }
    } catch {
        "Aviso: não foi possível ler $f ($($_.Exception.Message))" | Tee-Object -FilePath $ReportPath -Append | Out-Host
    }
}
if ($missingAbspath.Count -gt 0) {
    "Arquivos sem guard ABSPATH:" | Tee-Object -FilePath $ReportPath -Append | Out-Host
    $missingAbspath | Tee-Object -FilePath $ReportPath -Append | Out-Host
} else {
    "OK - todos os arquivos PHP têm guard ABSPATH nas primeiras linhas." | Tee-Object -FilePath $ReportPath -Append | Out-Host
}

# 4) Includes/Requires
Write-Header "4) Includes/Requires (verificar caminhos)"
$includePatterns = 'require\(|require_once\(|include\(|include_once\('
$incHits = Select-String -Path $phpFiles -Pattern $includePatterns
if ($incHits) { $incHits | ForEach-Object { "$($_.Path):$($_.LineNumber): $($_.Line)".Trim() } | Tee-Object -FilePath $ReportPath -Append | Out-Host }
else { "Nenhum include/require encontrado." | Tee-Object -FilePath $ReportPath -Append | Out-Host }

# 5) wp_remote_* (timeout/erros)
Write-Header "5) HTTP calls (wp_remote_get/wp_remote_post)"
$httpHits = Select-String -Path $phpFiles -Pattern 'wp_remote_(get|post)\('
if ($httpHits) { $httpHits | ForEach-Object { "$($_.Path):$($_.LineNumber): $($_.Line)".Trim() } | Tee-Object -FilePath $ReportPath -Append | Out-Host }
else { "Nenhuma chamada HTTP encontrada." | Tee-Object -FilePath $ReportPath -Append | Out-Host }

# 6) $wpdb sem prepare()
Write-Header '6) Consultas $wpdb (ver prepare())'

