[CmdletBinding()]
param(
    [Parameter()]
    [ValidateRange(0, [int]::MaxValue)]
    [int] $ExpectedLegacyCount = 75,

    [Parameter()]
    [ValidatePattern('^[0-9a-fA-F]{64}$')]
    [string] $ExpectedLegacyHash = '3a7f3071ec70a56de2d2593ffbd721994a0167f47941f495073c0e1d73d2e72f',

    [Parameter()]
    [switch] $ShowEvidence
)

$ErrorActionPreference = 'Stop'

# Additive migrations are reviewed explicitly. Removing these entries or
# changing any PHASE 0 migration makes this validator fail closed.
$allowedAdditionalMigrations = @(
    '2026_08_10_000002_create_audit_logs_table.php'
    '2026_08_10_000003_add_midtrans_checkout_fields_to_provider_subscriptions.php'
    '2026_08_10_000004_create_media_migration_entries_table.php'
    '2026_08_10_000010_add_correlation_id_to_audit_logs_table.php'
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
$migrationRoot = Join-Path $repositoryRoot 'backend\laravel-core\database\migrations'

if (-not (Test-Path -LiteralPath $migrationRoot -PathType Container)) {
    throw "Laravel migration directory was not found at '$migrationRoot'."
}

$allMigrationFiles = @(Get-ChildItem -LiteralPath $migrationRoot -File | Sort-Object Name)
$additionalFiles = @($allMigrationFiles | Where-Object Name -In $allowedAdditionalMigrations)
$legacyFiles = @($allMigrationFiles | Where-Object Name -NotIn $allowedAdditionalMigrations)
$missingAllowedMigrations = @(
    $allowedAdditionalMigrations | Where-Object {
        $_ -notin @($additionalFiles | ForEach-Object Name)
    }
)

$canonicalLines = [string[]] @(
    foreach ($migration in $legacyFiles) {
        $rawBytes = [System.IO.File]::ReadAllBytes($migration.FullName)

        [ordered] @{
            path   = $migration.Name
            sha256 = Get-Sha256Hex -Bytes $rawBytes
        } | ConvertTo-Json -Compress
    }
)

[Array]::Sort($canonicalLines, [StringComparer]::Ordinal)

$canonicalInventory = '[' + ($canonicalLines -join ',') + ']'
$utf8WithoutBom = [System.Text.UTF8Encoding]::new($false)
$canonicalBytes = $utf8WithoutBom.GetBytes($canonicalInventory)
$actualLegacyHash = Get-Sha256Hex -Bytes $canonicalBytes
$normalizedExpectedHash = $ExpectedLegacyHash.ToLowerInvariant()
$failures = @()

if ($legacyFiles.Count -ne $ExpectedLegacyCount) {
    $failures += "legacy count expected $ExpectedLegacyCount but found $($legacyFiles.Count)"
}

if ($actualLegacyHash -cne $normalizedExpectedHash) {
    $failures += "legacy SHA-256 expected $normalizedExpectedHash but found $actualLegacyHash"
}

if ($missingAllowedMigrations.Count -gt 0) {
    $failures += "missing allowed additive migration(s): $($missingAllowedMigrations -join ', ')"
}

if ($additionalFiles.Count -ne $allowedAdditionalMigrations.Count) {
    $failures += "reviewed additions expected $($allowedAdditionalMigrations.Count) but found $($additionalFiles.Count)"
}

if ($ShowEvidence) {
    [pscustomobject] @{
        MigrationRoot      = $migrationRoot
        TotalCount         = $allMigrationFiles.Count
        LegacyCount        = $legacyFiles.Count
        ReviewedAdditions  = $additionalFiles.Count
        CanonicalByteCount = $canonicalBytes.Count
        LegacySha256       = $actualLegacyHash
        ExpectedSha256     = $normalizedExpectedHash
    } | Format-List | Out-String | Write-Output
}

if ($failures.Count -gt 0) {
    throw "Migration parity FAILED: $($failures -join '; ')."
}

Write-Output "Migration parity PASS: $($legacyFiles.Count) legacy files, $($additionalFiles.Count) reviewed additions, SHA-256 $actualLegacyHash."
