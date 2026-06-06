param(
    [int]$LaravelPort = 8000,
    [int]$InferencePort = 9010,
    [string]$LaravelHost = "",
    [switch]$Visible
)

$ErrorActionPreference = "Stop"

$root = Resolve-Path (Join-Path $PSScriptRoot "..")
$inferenceRoot = Resolve-Path "C:\dev\smart-farming-inference"
$logDir = Join-Path $root "storage\logs\local-stack"

New-Item -ItemType Directory -Force -Path $logDir | Out-Null

function Get-LanAddress {
    $addresses = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object {
            $_.IPAddress -notlike "127.*" -and
            $_.IPAddress -notlike "169.254.*" -and
            $_.IPAddress -notlike "172.17.*" -and
            $_.IPAddress -notlike "172.18.*" -and
            $_.IPAddress -notlike "172.19.*" -and
            $_.IPAddress -notlike "172.20.*" -and
            $_.InterfaceAlias -notmatch "Loopback|Docker|WSL|vEthernet"
        } |
        Sort-Object SkipAsSource, InterfaceAlias

    return ($addresses | Select-Object -First 1).IPAddress
}

function Stop-ProcessOnPort {
    param([int]$Port)

    $connections = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue
    foreach ($connection in $connections) {
        if ($connection.OwningProcess -gt 0) {
            Stop-Process -Id $connection.OwningProcess -Force -ErrorAction SilentlyContinue
        }
    }
}

function Stop-ExistingStackProcesses {
    $patterns = @(
        "*artisan serve*--port $LaravelPort*",
        "*artisan queue:work*--queue=inference,default*",
        "*uvicorn app.main:app*--port $InferencePort*"
    )

    $processes = Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
        Where-Object {
            $commandLine = $_.CommandLine
            if ([string]::IsNullOrWhiteSpace($commandLine)) {
                return $false
            }

            foreach ($pattern in $patterns) {
                if ($commandLine -like $pattern) {
                    return $true
                }
            }

            return $false
        }

    foreach ($process in $processes) {
        if ($process.ProcessId -gt 0 -and $process.ProcessId -ne $PID) {
            Stop-Process -Id $process.ProcessId -Force -ErrorAction SilentlyContinue
        }
    }
}

function Start-StackProcess {
    param(
        [string]$Name,
        [string]$FilePath,
        [string[]]$Arguments,
        [string]$WorkingDirectory,
        [string]$LogFile
    )

    $windowStyle = if ($Visible) { "Normal" } else { "Hidden" }
    $argumentList = @(
        "-NoExit",
        "-Command",
        "& `"$FilePath`" $($Arguments -join ' ') 2>&1 | Tee-Object -FilePath `"$LogFile`""
    )

    Start-Process -FilePath "powershell.exe" `
        -ArgumentList $argumentList `
        -WorkingDirectory $WorkingDirectory `
        -WindowStyle $windowStyle | Out-Null

    Write-Host "Started $Name"
}

function Wait-HttpOk {
    param(
        [string]$Url,
        [int]$TimeoutSeconds = 45
    )

    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)

    do {
        try {
            Invoke-RestMethod -Uri $Url -TimeoutSec 5 | Out-Null
            return $true
        } catch {
            Start-Sleep -Seconds 2
        }
    } while ((Get-Date) -lt $deadline)

    return $false
}

if ($LaravelHost.Trim() -eq "") {
    $LaravelHost = Get-LanAddress
}

if ($LaravelHost.Trim() -eq "") {
    $LaravelHost = "127.0.0.1"
}

$python = Join-Path $inferenceRoot ".venv\Scripts\python.exe"
$artisan = Join-Path $root "artisan"

if (-not (Test-Path -LiteralPath $python)) {
    throw "Inference Python runtime not found: $python"
}

if (-not (Test-Path -LiteralPath $artisan)) {
    throw "Laravel artisan not found: $artisan"
}

Stop-ExistingStackProcesses
Stop-ProcessOnPort -Port $LaravelPort
Stop-ProcessOnPort -Port $InferencePort
Start-Sleep -Seconds 2

Start-StackProcess `
    -Name "Python inference" `
    -FilePath $python `
    -Arguments @("-m", "uvicorn", "app.main:app", "--host", "127.0.0.1", "--port", "$InferencePort") `
    -WorkingDirectory $inferenceRoot `
    -LogFile (Join-Path $logDir "inference.log")

$inferenceHealthUrl = "http://127.0.0.1:$InferencePort/health"
if (-not (Wait-HttpOk -Url $inferenceHealthUrl -TimeoutSeconds 60)) {
    throw "Inference service did not become healthy: $inferenceHealthUrl"
}

Start-StackProcess `
    -Name "Laravel API/backoffice" `
    -FilePath "php" `
    -Arguments @("-d", "max_execution_time=0", "artisan", "serve", "--host", $LaravelHost, "--port", "$LaravelPort") `
    -WorkingDirectory $root `
    -LogFile (Join-Path $logDir "laravel-api.log")

Start-StackProcess `
    -Name "Laravel queue worker" `
    -FilePath "php" `
    -Arguments @("-d", "max_execution_time=0", "artisan", "queue:work", "--queue=inference,default", "--tries=3", "--timeout=120", "--sleep=3") `
    -WorkingDirectory $root `
    -LogFile (Join-Path $logDir "queue.log")

Start-Sleep -Seconds 3

Write-Host ""
Write-Host "Local stack started."
Write-Host "Backoffice/API:  http://$LaravelHost`:$LaravelPort"
Write-Host "Mobile API URL:  http://$LaravelHost`:$LaravelPort/api/v1"
Write-Host "Inference:       http://127.0.0.1:$InferencePort"
Write-Host "Logs:            $logDir"
Write-Host ""
Write-Host "Use the Mobile API URL in the Flutter login/API setup."
