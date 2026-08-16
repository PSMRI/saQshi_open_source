[CmdletBinding()]
param(
    [ValidateSet('scheduled-task', 'windows-service', 'redis-coordinated')]
    [string]$Mode,
    [string]$PhpPath = 'php.exe',
    [string]$NssmPath,
    [switch]$Apply
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\\..')).Path
$workerScript = Join-Path $projectRoot 'api\\cli\\job_worker.php'
$configPath = Join-Path $projectRoot 'api\\config\\background_worker.json'

if (-not $Mode) {
    Write-Host 'Select background-worker mode for this deployment:'
    Write-Host '  1. Windows Scheduled Task (recommended for small deployments)'
    Write-Host '  2. Windows Service via NSSM (recommended for continuous processing)'
    Write-Host '  3. Redis-coordinated worker (for multiple application servers)'
    switch (Read-Host 'Enter 1, 2, or 3') {
        '1' { $Mode = 'scheduled-task' }
        '2' { $Mode = 'windows-service' }
        '3' { $Mode = 'redis-coordinated' }
        default { throw 'Invalid worker mode.' }
    }
}

$config = Get-Content -Raw -LiteralPath $configPath | ConvertFrom-Json
$config.execution_mode = $Mode
$config.redis_coordinated_worker.enabled = ($Mode -eq 'redis-coordinated')
$config | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath $configPath -Encoding utf8
Write-Host "Saved '$Mode' to $configPath"

if (-not $Apply) {
    Write-Host 'Configuration saved. Re-run with -Apply from an elevated PowerShell to install the selected runner.'
    exit 0
}

switch ($Mode) {
    'scheduled-task' {
        $taskName = $config.scheduled_task.task_name
        $minutes = [Math]::Max(1, [int]$config.scheduled_task.interval_minutes)
        $taskCommand = ('"{0}" "{1}" --once' -f $PhpPath, $workerScript)
        schtasks.exe /Create /TN $taskName /SC MINUTE /MO $minutes /TR $taskCommand /RU SYSTEM /F
        Write-Host "Installed scheduled task '$taskName'."
    }
    'windows-service' {
        if (-not $NssmPath -or -not (Test-Path -LiteralPath $NssmPath)) {
            throw 'Windows Service mode needs NSSM. Re-run with -NssmPath C:\\path\\to\\nssm.exe -Apply.'
        }
        $serviceName = $config.windows_service.service_name
        & $NssmPath install $serviceName $PhpPath $workerScript --daemon
        & $NssmPath set $serviceName AppDirectory $projectRoot
        & $NssmPath start $serviceName
        Write-Host "Installed and started Windows service '$serviceName'."
    }
    'redis-coordinated' {
        Write-Host 'Redis-coordinated mode is configured. Run the following command on every worker host under your service manager:'
        Write-Host ('"{0}" "{1}" --daemon --redis-lock' -f $PhpPath, $workerScript)
    }
}
