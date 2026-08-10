[CmdletBinding()]
param(
    [Parameter()]
    [ValidateRange(0, [int]::MaxValue)]
    [int] $ExpectedCount = 296,

    [Parameter()]
    [ValidatePattern('^[0-9a-fA-F]{64}$')]
    [string] $ExpectedHash = '7e0838b62ee918c559594688c6f72a3d7a5d15adfb8bf0b97826a2435eb268bb',

    [Parameter()]
    [switch] $ShowEvidence
)

$ErrorActionPreference = 'Stop'

# PHASE 0 is the immutable compatibility contract. Security and operations may
# add routes, but every addition must be reviewed here explicitly; additions
# are removed before the legacy inventory is hashed.
$allowedAdditionalRoutes = @(
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'admin/providers/{user}/documents/{document}'; name = 'admin.providers.documents.show' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'api/admin/providers/{provider}/documents/{document}'; name = 'api.admin.providers.documents.show' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'api/provider/profile/documents/{document}'; name = 'api.provider.profile.documents.show' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'provider/profile/documents/{document}'; name = 'provider.documents.show' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'private/chat-attachments/{message}'; name = 'chat.attachments.show' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'api/readiness'; name = 'api.readiness' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'api/reviews'; name = 'api.reviews.index' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'horizon/api/batches'; name = 'horizon.jobs-batches.index' }
    [ordered] @{ methods = @('POST'); uri = 'horizon/api/batches/retry/{id}'; name = 'horizon.jobs-batches.retry' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'horizon/api/batches/{id}'; name = 'horizon.jobs-batches.show' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'horizon/api/jobs/completed'; name = 'horizon.completed-jobs.index' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'horizon/api/jobs/failed'; name = 'horizon.failed-jobs.index' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'horizon/api/jobs/failed/{id}'; name = 'horizon.failed-jobs.show' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'horizon/api/jobs/pending'; name = 'horizon.pending-jobs.index' }
    [ordered] @{ methods = @('POST'); uri = 'horizon/api/jobs/retry/{id}'; name = 'horizon.retry-jobs.show' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'horizon/api/jobs/silenced'; name = 'horizon.silenced-jobs.index' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'horizon/api/jobs/{id}'; name = 'horizon.jobs.show' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'horizon/api/masters'; name = 'horizon.masters.index' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'horizon/api/metrics/jobs'; name = 'horizon.jobs-metrics.index' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'horizon/api/metrics/jobs/{id}'; name = 'horizon.jobs-metrics.show' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'horizon/api/metrics/queues'; name = 'horizon.queues-metrics.index' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'horizon/api/metrics/queues/{id}'; name = 'horizon.queues-metrics.show' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'horizon/api/monitoring'; name = 'horizon.monitoring.index' }
    [ordered] @{ methods = @('POST'); uri = 'horizon/api/monitoring'; name = 'horizon.monitoring.store' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'horizon/api/monitoring/{tag}'; name = 'horizon.monitoring-tag.paginate' }
    [ordered] @{ methods = @('DELETE'); uri = 'horizon/api/monitoring/{tag}'; name = 'horizon.monitoring-tag.destroy' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'horizon/api/stats'; name = 'horizon.stats.index' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'horizon/api/workload'; name = 'horizon.workload.index' }
    [ordered] @{ methods = @('GET', 'HEAD'); uri = 'horizon/{view?}'; name = 'horizon.index' }
)

function Get-Sha256Hex {
    param(
        [Parameter(Mandatory)]
        [byte[]] $Bytes
    )

    $sha256 = [System.Security.Cryptography.SHA256]::Create()

    try {
        return -join ($sha256.ComputeHash($Bytes) | ForEach-Object { $_.ToString('x2') })
    }
    finally {
        $sha256.Dispose()
    }
}

$repositoryRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\..')).Path
$laravelRoot = Join-Path $repositoryRoot 'backend\laravel-core'
$artisanPath = Join-Path $laravelRoot 'artisan'

if (-not (Test-Path -LiteralPath $artisanPath -PathType Leaf)) {
    throw "Laravel artisan entry point was not found at '$artisanPath'."
}

$phpCommand = Get-Command php -CommandType Application -ErrorAction Stop | Select-Object -First 1
$previousLocation = Get-Location

try {
    Set-Location -LiteralPath $laravelRoot
    $commandOutput = @(& $phpCommand.Source $artisanPath 'route:list' '--json' 2>&1)
    $commandExitCode = $LASTEXITCODE
}
finally {
    Set-Location -LiteralPath $previousLocation
}

if ($commandExitCode -ne 0) {
    throw "Laravel route inventory command failed with exit code $commandExitCode."
}

try {
    $routeItems = @((($commandOutput | Out-String).Trim() | ConvertFrom-Json))
}
catch {
    throw 'Laravel route inventory did not return valid JSON.'
}

$normalizedGeneratedNameCount = 0
$canonicalLines = [string[]] @(
    foreach ($routeItem in $routeItems) {
        $availableProperties = @($routeItem.PSObject.Properties.Name)

        foreach ($requiredProperty in @('method', 'uri', 'name')) {
            if ($requiredProperty -notin $availableProperties) {
                throw "Laravel route inventory record is missing the '$requiredProperty' property."
            }
        }

        $methods = [string[]] @([string] $routeItem.method -split '\|')
        [Array]::Sort($methods, [StringComparer]::Ordinal)

        if ($methods.Count -eq 0 -or [string]::IsNullOrWhiteSpace($methods[0])) {
            throw "Laravel route inventory contains an empty method for URI '$([string] $routeItem.uri)'."
        }

        $routeName = [string] $routeItem.name
        if ($routeName.StartsWith('generated::', [StringComparison]::Ordinal)) {
            # Laravel assigns ephemeral names to unnamed framework routes when
            # route caching. They are not part of the public route-name contract.
            $routeName = ''
            $normalizedGeneratedNameCount++
        }

        [ordered] @{
            methods = $methods
            uri     = [string] $routeItem.uri
            name    = $routeName
        } | ConvertTo-Json -Compress -Depth 5
    }
)

$allowedAdditionalLines = [string[]] @(
    foreach ($allowedRoute in $allowedAdditionalRoutes) {
        $allowedMethods = [string[]] @($allowedRoute.methods)
        [Array]::Sort($allowedMethods, [StringComparer]::Ordinal)

        [ordered] @{
            methods = $allowedMethods
            uri     = [string] $allowedRoute.uri
            name    = [string] $allowedRoute.name
        } | ConvertTo-Json -Compress -Depth 5
    }
)

$remainingRouteLines = [System.Collections.Generic.List[string]]::new()
foreach ($line in $canonicalLines) {
    $remainingRouteLines.Add($line)
}

$missingAllowedRoutes = [System.Collections.Generic.List[string]]::new()
foreach ($allowedLine in $allowedAdditionalLines) {
    $matchIndex = $remainingRouteLines.IndexOf($allowedLine)

    if ($matchIndex -lt 0) {
        $missingAllowedRoutes.Add($allowedLine)
        continue
    }

    $remainingRouteLines.RemoveAt($matchIndex)
}

$legacyCanonicalLines = [string[]] @($remainingRouteLines)
[Array]::Sort($legacyCanonicalLines, [StringComparer]::Ordinal)

$canonicalInventory = '[' + ($legacyCanonicalLines -join ',') + ']'
$utf8WithoutBom = [System.Text.UTF8Encoding]::new($false)
$canonicalBytes = $utf8WithoutBom.GetBytes($canonicalInventory)
$totalCount = $canonicalLines.Count
$actualCount = $legacyCanonicalLines.Count
$actualHash = Get-Sha256Hex -Bytes $canonicalBytes
$normalizedExpectedHash = $ExpectedHash.ToLowerInvariant()

if ($ShowEvidence) {
    [pscustomobject] @{
        ArtisanPath       = $artisanPath
        CanonicalFields   = 'methods,uri,name'
        SortComparer      = 'StringComparer.Ordinal'
        Encoding          = 'UTF-8 without BOM'
        TotalRouteCount   = $totalCount
        LegacyRouteCount  = $actualCount
        AllowedAdditions  = $allowedAdditionalLines.Count
        CanonicalByteCount = $canonicalBytes.Count
        Sha256            = $actualHash
        ExpectedCount     = $ExpectedCount
        ExpectedSha256    = $normalizedExpectedHash
        GeneratedNamesNormalized = $normalizedGeneratedNameCount
    } | Format-List | Out-String | Write-Output
}

$parityFailures = @()

if ($missingAllowedRoutes.Count -gt 0) {
    $parityFailures += "missing $($missingAllowedRoutes.Count) explicitly allowed additive route(s)"
}

if ($actualCount -ne $ExpectedCount) {
    $parityFailures += "count expected $ExpectedCount but found $actualCount"
}

if ($actualHash -cne $normalizedExpectedHash) {
    $parityFailures += "SHA-256 expected $normalizedExpectedHash but found $actualHash"
}

if ($parityFailures.Count -gt 0) {
    throw "Route parity FAILED: $($parityFailures -join '; ')."
}

Write-Output "Route parity PASS: $actualCount legacy records, $($allowedAdditionalLines.Count) reviewed additions, SHA-256 $actualHash."
