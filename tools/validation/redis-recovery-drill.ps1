[CmdletBinding()]
param(
    [Parameter()]
    [ValidateSet('takein_phase8_gate')]
    [string] $ComposeProject = 'takein_phase8_gate'
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$repositoryRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\..')).Path
$expectedReadinessUrl = 'http://127.0.0.1:28100/api/readiness'
$expectedContainerName = "${ComposeProject}-redis-1"
$sentinelKey = 'phase9:restart:sentinel'
$sentinelValue = 'phase9-persistence-ok-20260810'

function Invoke-Docker {
    param(
        [Parameter(Mandatory)]
        [string[]] $Arguments,

        [Parameter()]
        [string] $Operation = 'Docker command'
    )

    $nativeOutput = @(& docker @Arguments 2>&1)
    $exitCode = $LASTEXITCODE
    $textOutput = [string] ($nativeOutput -join "`n")

    if ($exitCode -ne 0) {
        throw "$Operation failed with exit code ${exitCode}: $($textOutput.Trim())"
    }

    return $textOutput.TrimEnd("`r", "`n")
}

function Get-ReadinessResponse {
    try {
        $response = Invoke-WebRequest -UseBasicParsing -Uri $expectedReadinessUrl -TimeoutSec 15

        return [ordered] @{
            Status = [int] $response.StatusCode
            Body = [string] $response.Content
        }
    }
    catch {
        if ($null -eq $_.Exception.Response) {
            throw
        }

        $response = $_.Exception.Response
        $reader = [System.IO.StreamReader]::new($response.GetResponseStream())

        try {
            return [ordered] @{
                Status = [int] $response.StatusCode
                Body = $reader.ReadToEnd()
            }
        }
        finally {
            $reader.Dispose()
        }
    }
}

$containerIds = @(
    & docker @(
        'ps',
        '--filter', "label=com.docker.compose.project=$ComposeProject",
        '--filter', 'label=com.docker.compose.service=redis',
        '--format', '{{.ID}}'
    )
)

if ($LASTEXITCODE -ne 0 -or $containerIds.Count -ne 1 -or [string]::IsNullOrWhiteSpace($containerIds[0])) {
    throw "Expected exactly one running Redis container for '$ComposeProject'."
}

$containerId = $containerIds[0]
$inspection = @((Invoke-Docker -Arguments @('inspect', $containerId) -Operation 'Redis inspection') | ConvertFrom-Json)

if ($inspection.Count -ne 1) {
    throw 'Redis inspection did not return exactly one record.'
}

$container = $inspection[0]
$containerName = ([string] $container.Name).TrimStart('/')
$labels = $container.Config.Labels

if ($containerName -cne $expectedContainerName -or
    [string] $labels.'com.docker.compose.project' -cne $ComposeProject -or
    [string] $labels.'com.docker.compose.service' -cne 'redis' -or
    [string] $labels.'com.docker.compose.container-number' -cne '1') {
    throw 'Refusing Redis recovery drill: container identity or Compose labels do not match the approved isolated target.'
}

$workingDirectory = [string] $labels.'com.docker.compose.project.working_dir'

if (-not $workingDirectory.Equals($repositoryRoot, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Refusing Redis recovery drill: Compose working directory is outside this repository.'
}

if (-not [bool] $container.State.Running -or [string] $container.State.Health.Status -cne 'healthy') {
    throw 'Refusing Redis recovery drill: the isolated Redis container is not running and healthy.'
}

$publishedPortBindings = @($container.HostConfig.PortBindings.PSObject.Properties)

if ($publishedPortBindings.Count -gt 0) {
    throw 'Refusing Redis recovery drill: Redis unexpectedly publishes a host port.'
}

$commandText = [string] (@($container.Config.Cmd) -join ' ')

if ($commandText -cnotmatch '--appendonly\s+yes') {
    throw 'Refusing Redis recovery drill: append-only persistence is not enabled in the container command.'
}

$unauthenticatedPing = Invoke-Docker -Arguments @('exec', $containerId, 'redis-cli', '--raw', 'PING') -Operation 'Unauthenticated Redis probe'

if ($unauthenticatedPing -cnotmatch 'NOAUTH') {
    throw 'Unauthenticated Redis PING did not fail with NOAUTH.'
}

$authenticatedPing = Invoke-Docker -Arguments @(
    'exec', $containerId, 'sh', '-ceu',
    'REDISCLI_AUTH="$REDIS_PASSWORD" redis-cli --raw PING'
) -Operation 'Authenticated Redis probe'

if ($authenticatedPing.Trim() -cne 'PONG') {
    throw 'Authenticated Redis PING did not return PONG.'
}

$aofConfiguration = Invoke-Docker -Arguments @(
    'exec', $containerId, 'sh', '-ceu',
    'REDISCLI_AUTH="$REDIS_PASSWORD" redis-cli --raw CONFIG GET appendonly'
) -Operation 'Redis AOF configuration probe'

if ($aofConfiguration -cnotmatch '(?m)^appendonly\r?\nyes$') {
    throw 'Redis runtime appendonly configuration is not yes.'
}

$setResult = Invoke-Docker -Arguments @(
    'exec', $containerId, 'sh', '-ceu',
    ('REDISCLI_AUTH="$REDIS_PASSWORD" redis-cli --raw SET ' + $sentinelKey + ' ' + $sentinelValue)
) -Operation 'Redis persistence sentinel write'

if ($setResult.Trim() -cne 'OK') {
    throw 'Redis persistence sentinel write did not return OK.'
}

$before = Get-ReadinessResponse

if ($before.Status -ne 200 -or $before.Body -cnotmatch 'ready') {
    throw "Readiness was not healthy before the Redis outage drill: HTTP $($before.Status)."
}

$redisStopped = $false

try {
    Invoke-Docker -Arguments @('stop', '--time', '10', $containerId) -Operation 'Bounded isolated Redis stop' | Out-Null
    $redisStopped = $true
    $during = Get-ReadinessResponse

    if ($during.Status -ne 503 -or $during.Body -cnotmatch 'unavailable') {
        throw "Readiness did not fail closed during the Redis outage drill: HTTP $($during.Status)."
    }
}
finally {
    if ($redisStopped) {
        Invoke-Docker -Arguments @('start', $containerId) -Operation 'Isolated Redis recovery start' | Out-Null
    }
}

$healthy = $false

for ($attempt = 0; $attempt -lt 30; $attempt++) {
    $currentRecords = @((Invoke-Docker -Arguments @('inspect', $containerId) -Operation 'Redis recovery inspection') | ConvertFrom-Json)
    $current = $currentRecords[0]

    if ([string] $current.State.Health.Status -ceq 'healthy') {
        $healthy = $true
        break
    }

    Start-Sleep -Seconds 1
}

if (-not $healthy) {
    throw 'Redis did not return to healthy state within 30 seconds.'
}

$persistedValue = Invoke-Docker -Arguments @(
    'exec', $containerId, 'sh', '-ceu',
    ('REDISCLI_AUTH="$REDIS_PASSWORD" redis-cli --raw GET ' + $sentinelKey)
) -Operation 'Redis persistence sentinel read'

if ($persistedValue.Trim() -cne $sentinelValue) {
    throw 'Redis persistence sentinel did not survive the restart.'
}

$after = Get-ReadinessResponse

if ($after.Status -ne 200 -or $after.Body -cnotmatch 'ready') {
    throw "Readiness did not recover after Redis restarted: HTTP $($after.Status)."
}

$cleanupResult = Invoke-Docker -Arguments @(
    'exec', $containerId, 'sh', '-ceu',
    ('REDISCLI_AUTH="$REDIS_PASSWORD" redis-cli --raw DEL ' + $sentinelKey)
) -Operation 'Redis persistence sentinel cleanup'

if ($cleanupResult.Trim() -cne '1') {
    throw 'Redis persistence sentinel cleanup did not delete exactly one key.'
}

[ordered] @{
    Status = 'PASS'
    ComposeProject = $ComposeProject
    Container = $expectedContainerName
    ContainerId = $containerId.Substring(0, 12)
    HostPorts = 'none'
    UnauthenticatedProbe = 'NOAUTH'
    AuthenticatedProbe = 'PONG'
    AppendOnly = 'yes'
    ReadinessBefore = $before.Status
    ReadinessDuringOutage = $during.Status
    SentinelAfterRestart = $persistedValue.Trim()
    ReadinessAfter = $after.Status
    SentinelCleanup = 'deleted'
} | ConvertTo-Json

Write-Output 'Redis recovery drill: PASS (NOAUTH, AOF persistence, readiness failure/recovery, and sentinel cleanup verified).'
