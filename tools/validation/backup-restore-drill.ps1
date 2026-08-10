[CmdletBinding()]
param(
    [Parameter()]
    [ValidateSet('takein_phase8_gate')]
    [string] $ComposeProject = 'takein_phase8_gate',

    [Parameter()]
    [ValidateSet('youyaku')]
    [string] $SourceDatabase = 'youyaku',

    [Parameter()]
    [ValidateSet('youyaku_phase9_restore')]
    [string] $RestoreDatabase = 'youyaku_phase9_restore'
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$repositoryRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\..')).Path
$expectedComposeFile = (Resolve-Path -LiteralPath (Join-Path $repositoryRoot 'docker-compose.yml')).Path
$expectedEnvironmentFile = (Resolve-Path -LiteralPath (Join-Path $repositoryRoot '.env.example')).Path
$expectedContainerName = "$ComposeProject-db-1"
$expectedVolumeName = "${ComposeProject}_youyaku_mysql"
$expectedNetworkName = "${ComposeProject}_youyaku_data"
$dumpNonce = [Guid]::NewGuid().ToString('N')
$dumpPath = "/tmp/${ComposeProject}_${RestoreDatabase}_${dumpNonce}.sql"
$restoreCreatedByThisRun = $false
$dumpCreatedByThisRun = $false
$primaryFailure = $null
$cleanupFailures = [System.Collections.Generic.List[string]]::new()
$cleanupEvidence = [ordered] @{
    DumpRemoved = $false
    RestoreDatabaseDropped = $false
}

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
        $safeDetail = if ([string]::IsNullOrWhiteSpace($textOutput)) {
            'no diagnostic output'
        }
        else {
            $textOutput.Trim()
        }

        throw "$Operation failed with exit code ${exitCode}: $safeDetail"
    }

    return $textOutput.TrimEnd("`r", "`n")
}

function Get-ContainerInspection {
    param(
        [Parameter(Mandatory)]
        [string] $ContainerId
    )

    $inspectionJson = Invoke-Docker -Arguments @('inspect', $ContainerId) -Operation 'Container inspection'
    $inspection = @($inspectionJson | ConvertFrom-Json)

    if ($inspection.Count -ne 1) {
        throw "Expected exactly one inspection record for database container, found $($inspection.Count)."
    }

    return $inspection[0]
}

function Get-EnvironmentMap {
    param(
        [Parameter(Mandatory)]
        [object] $Inspection
    )

    $environment = @{}

    foreach ($entry in @($Inspection.Config.Env)) {
        $separator = $entry.IndexOf('=')

        if ($separator -lt 1) {
            continue
        }

        $environment[$entry.Substring(0, $separator)] = $entry.Substring($separator + 1)
    }

    return $environment
}

function Assert-ExactContainerIdentity {
    param(
        [Parameter(Mandatory)]
        [object] $Inspection
    )

    $labels = $Inspection.Config.Labels
    $actualName = ([string] $Inspection.Name).TrimStart('/')

    if ($actualName -cne $expectedContainerName) {
        throw "Refusing database drill: expected container '$expectedContainerName', found '$actualName'."
    }

    if ([string] $labels.'com.docker.compose.project' -cne $ComposeProject) {
        throw 'Refusing database drill: Compose project label does not match the approved isolated project.'
    }

    if ([string] $labels.'com.docker.compose.service' -cne 'db') {
        throw 'Refusing database drill: container is not the Compose db service.'
    }

    if ([string] $labels.'com.docker.compose.container-number' -cne '1') {
        throw 'Refusing database drill: expected Compose db container number 1.'
    }

    if ([string] $labels.'com.docker.compose.oneoff' -cne 'False') {
        throw 'Refusing database drill: one-off containers are not valid backup sources.'
    }

    if ([string] $Inspection.Config.Image -cne 'mysql:8.0') {
        throw "Refusing database drill: expected mysql:8.0, found '$($Inspection.Config.Image)'."
    }

    if (-not [bool] $Inspection.State.Running) {
        throw 'Refusing database drill: database container is not running.'
    }

    if ([string] $Inspection.State.Health.Status -cne 'healthy') {
        throw "Refusing database drill: database container health is '$($Inspection.State.Health.Status)'."
    }

    $workingDirectory = [string] $labels.'com.docker.compose.project.working_dir'
    $configFiles = @(([string] $labels.'com.docker.compose.project.config_files') -split ',')
    $environmentFiles = @(([string] $labels.'com.docker.compose.project.environment_file') -split ',')

    if (-not $workingDirectory.Equals($repositoryRoot, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Refusing database drill: Compose working-directory label is outside the current repository.'
    }

    if ($configFiles.Count -ne 1 -or -not $configFiles[0].Equals($expectedComposeFile, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Refusing database drill: Compose config-file label does not identify this repository docker-compose.yml exactly.'
    }

    if ($environmentFiles.Count -ne 1 -or -not $environmentFiles[0].Equals($expectedEnvironmentFile, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Refusing database drill: Compose environment-file label does not identify this repository .env.example exactly.'
    }

    $dataMounts = @(
        $Inspection.Mounts | Where-Object {
            [string] $_.Destination -ceq '/var/lib/mysql'
        }
    )

    if ($dataMounts.Count -ne 1) {
        throw "Refusing database drill: expected one /var/lib/mysql mount, found $($dataMounts.Count)."
    }

    $dataMount = $dataMounts[0]

    if ([string] $dataMount.Type -cne 'volume' -or [string] $dataMount.Name -cne $expectedVolumeName -or -not [bool] $dataMount.RW) {
        throw 'Refusing database drill: MySQL data mount is not the exact writable isolated-project volume.'
    }

    $networkNames = @($Inspection.NetworkSettings.Networks.PSObject.Properties.Name)

    if ($networkNames.Count -ne 1 -or [string] $networkNames[0] -cne $expectedNetworkName) {
        throw 'Refusing database drill: database container is attached to an unexpected network.'
    }
}

function Assert-ExactVolumeIdentity {
    $volumeJson = Invoke-Docker -Arguments @('volume', 'inspect', $expectedVolumeName) -Operation 'MySQL volume inspection'
    $volumes = @($volumeJson | ConvertFrom-Json)

    if ($volumes.Count -ne 1) {
        throw "Refusing database drill: expected exactly one MySQL volume, found $($volumes.Count)."
    }

    $volume = $volumes[0]

    if ([string] $volume.Name -cne $expectedVolumeName -or [string] $volume.Driver -cne 'local' -or [string] $volume.Scope -cne 'local') {
        throw 'Refusing database drill: MySQL volume identity, driver, or scope is unexpected.'
    }

    if ([string] $volume.Labels.'com.docker.compose.project' -cne $ComposeProject -or [string] $volume.Labels.'com.docker.compose.volume' -cne 'youyaku_mysql') {
        throw 'Refusing database drill: MySQL volume labels do not match the approved isolated project.'
    }
}

function Invoke-MySqlQuery {
    param(
        [Parameter(Mandatory)]
        [string] $ContainerId,

        [Parameter(Mandatory)]
        [string] $Sql,

        [Parameter()]
        [string] $Operation = 'MySQL query'
    )

    $encodedSql = [Convert]::ToBase64String([System.Text.UTF8Encoding]::new($false).GetBytes($Sql))
    $shellScript = 'export MYSQL_PWD=$MYSQL_ROOT_PASSWORD;printf %s ' + $encodedSql + '|base64 -d|mysql --batch --skip-column-names --raw -h 127.0.0.1 -u root'

    return Invoke-Docker -Arguments @('exec', $ContainerId, 'sh', '-ceu', $shellScript) -Operation $Operation
}

function Get-TextSha256 {
    param(
        [Parameter(Mandatory)]
        [AllowEmptyString()]
        [string] $Text
    )

    $sha256 = [System.Security.Cryptography.SHA256]::Create()

    try {
        $bytes = [System.Text.UTF8Encoding]::new($false).GetBytes($Text)
        return -join ($sha256.ComputeHash($bytes) | ForEach-Object { $_.ToString('x2') })
    }
    finally {
        $sha256.Dispose()
    }
}

function Get-SchemaManifest {
    param(
        [Parameter(Mandatory)]
        [string] $ContainerId,

        [Parameter(Mandatory)]
        [string] $Database
    )

    $escapedDatabase = $Database.Replace("'", "''")
    $queries = [ordered] @{
        DATABASE = "SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$escapedDatabase';"
        TABLES = "SELECT TABLE_NAME, TABLE_TYPE, COALESCE(ENGINE, ''), COALESCE(ROW_FORMAT, ''), COALESCE(TABLE_COLLATION, ''), COALESCE(CREATE_OPTIONS, '') FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$escapedDatabase' ORDER BY TABLE_NAME;"
        COLUMNS = "SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COALESCE(COLUMN_DEFAULT, '<NULL>'), IS_NULLABLE, DATA_TYPE, COLUMN_TYPE, COALESCE(CHARACTER_SET_NAME, ''), COALESCE(COLLATION_NAME, ''), COLUMN_KEY, EXTRA, COALESCE(GENERATION_EXPRESSION, '') FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$escapedDatabase' ORDER BY TABLE_NAME, ORDINAL_POSITION;"
        INDEXES = "SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, COALESCE(COLLATION, ''), COALESCE(SUB_PART, 0), COALESCE(NULLABLE, ''), INDEX_TYPE, COALESCE(EXPRESSION, '') FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '$escapedDatabase' ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;"
        FOREIGN_KEYS = "SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, ORDINAL_POSITION, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, POSITION_IN_UNIQUE_CONSTRAINT FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '$escapedDatabase' AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION;"
        CONSTRAINTS = "SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE, COALESCE(ENFORCED, '') FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = '$escapedDatabase' ORDER BY TABLE_NAME, CONSTRAINT_NAME;"
        TRIGGERS = "SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE, ACTION_ORDER, ACTION_CONDITION, ACTION_STATEMENT, ACTION_ORIENTATION, ACTION_TIMING, SQL_MODE, CHARACTER_SET_CLIENT, COLLATION_CONNECTION, DATABASE_COLLATION FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = '$escapedDatabase' ORDER BY TRIGGER_NAME;"
        ROUTINES = "SELECT ROUTINE_NAME, ROUTINE_TYPE, DATA_TYPE, ROUTINE_DEFINITION, IS_DETERMINISTIC, SQL_DATA_ACCESS, SECURITY_TYPE, SQL_MODE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = '$escapedDatabase' ORDER BY ROUTINE_TYPE, ROUTINE_NAME;"
        EVENTS = "SELECT EVENT_NAME, EVENT_DEFINITION, EVENT_TYPE, EXECUTE_AT, INTERVAL_VALUE, INTERVAL_FIELD, SQL_MODE, STATUS, ON_COMPLETION FROM information_schema.EVENTS WHERE EVENT_SCHEMA = '$escapedDatabase' ORDER BY EVENT_NAME;"
        VIEWS = "SELECT TABLE_NAME, VIEW_DEFINITION, CHECK_OPTION, IS_UPDATABLE, SECURITY_TYPE, CHARACTER_SET_CLIENT, COLLATION_CONNECTION FROM information_schema.VIEWS WHERE TABLE_SCHEMA = '$escapedDatabase' ORDER BY TABLE_NAME;"
    }

    $sections = [System.Collections.Generic.List[string]]::new()

    foreach ($entry in $queries.GetEnumerator()) {
        $result = Invoke-MySqlQuery -ContainerId $ContainerId -Sql $entry.Value -Operation "Read $($entry.Key) schema metadata"
        $normalized = $result.Replace("``$Database``", '``<DATABASE>``').Replace($Database, '<DATABASE>')
        $sections.Add("[$($entry.Key)]`n$normalized")
    }

    return $sections -join "`n"
}

function Get-TableRowCounts {
    param(
        [Parameter(Mandatory)]
        [string] $ContainerId,

        [Parameter(Mandatory)]
        [string] $Database,

        [Parameter(Mandatory)]
        [string[]] $Tables
    )

    $counts = [ordered] @{}

    foreach ($table in $Tables) {
        $escapedIdentifier = $table.Replace('`', '``')
        $countText = Invoke-MySqlQuery -ContainerId $ContainerId -Sql "SELECT COUNT(*) FROM ``$Database``.``$escapedIdentifier``;" -Operation "Count $Database.$table rows"
        $count = 0L

        if (-not [long]::TryParse($countText.Trim(), [ref] $count)) {
            throw "Could not parse row count for $Database.$table."
        }

        $counts[$table] = $count
    }

    return $counts
}

function Assert-ContainerStillExact {
    param(
        [Parameter(Mandatory)]
        [string] $ContainerId
    )

    $currentInspection = Get-ContainerInspection -ContainerId $ContainerId
    Assert-ExactContainerIdentity -Inspection $currentInspection

    if ([string] $currentInspection.Id -cne $ContainerId) {
        throw 'Database container identity changed during the drill.'
    }
}

if ($SourceDatabase -ceq $RestoreDatabase) {
    throw 'Source and restore databases must never be the same.'
}

if ($RestoreDatabase -cnotmatch '^youyaku_phase9_restore$') {
    throw 'Restore database is outside the approved bounded namespace.'
}

if ($dumpPath -cnotmatch '^/tmp/takein_phase8_gate_youyaku_phase9_restore_[0-9a-f]{32}\.sql$') {
    throw 'Generated dump path is outside the approved bounded namespace.'
}

try {
    $dockerVersion = Invoke-Docker -Arguments @('version', '--format', '{{.Server.Version}}') -Operation 'Docker availability check'

    if ([string]::IsNullOrWhiteSpace($dockerVersion)) {
        throw 'Docker server version was empty.'
    }

    $candidateText = Invoke-Docker -Arguments @(
        'ps', '-aq',
        '--filter', "label=com.docker.compose.project=$ComposeProject",
        '--filter', 'label=com.docker.compose.service=db'
    ) -Operation 'Isolated database-container discovery'
    $candidateIds = @($candidateText -split "`r?`n" | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })

    if ($candidateIds.Count -ne 1) {
        throw "Refusing database drill: expected exactly one isolated db container, found $($candidateIds.Count)."
    }

    $inspection = Get-ContainerInspection -ContainerId $candidateIds[0]
    Assert-ExactContainerIdentity -Inspection $inspection
    Assert-ExactVolumeIdentity
    $containerId = [string] $inspection.Id
    $environment = Get-EnvironmentMap -Inspection $inspection

    foreach ($requiredName in @('MYSQL_DATABASE', 'MYSQL_USER', 'MYSQL_PASSWORD', 'MYSQL_ROOT_PASSWORD')) {
        if (-not $environment.ContainsKey($requiredName) -or [string]::IsNullOrWhiteSpace([string] $environment[$requiredName])) {
            throw "Refusing database drill: required container environment '$requiredName' is absent or empty."
        }
    }

    if ([string] $environment.MYSQL_DATABASE -cne $SourceDatabase -or [string] $environment.MYSQL_USER -cne 'youyaku') {
        throw 'Refusing database drill: container database/user environment does not match the isolated gate database.'
    }

    $mysqlVersion = Invoke-MySqlQuery -ContainerId $containerId -Sql 'SELECT VERSION();' -Operation 'MySQL version check'

    if ($mysqlVersion -cnotmatch '^8\.0(?:\.|$)') {
        throw "Refusing database drill: expected MySQL 8.0, found '$mysqlVersion'."
    }

    $sourceExists = Invoke-MySqlQuery -ContainerId $containerId -Sql "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$SourceDatabase';" -Operation 'Source database existence check'
    $restoreExists = Invoke-MySqlQuery -ContainerId $containerId -Sql "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$RestoreDatabase';" -Operation 'Restore database preflight check'

    if ($sourceExists.Trim() -cne '1') {
        throw "Refusing database drill: source database '$SourceDatabase' does not exist exactly once."
    }

    if ($restoreExists.Trim() -cne '0') {
        throw "Refusing database drill: restore database '$RestoreDatabase' already exists; it will not be reused or dropped."
    }

    $sourceMetadata = Invoke-MySqlQuery -ContainerId $containerId -Sql "SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$SourceDatabase';" -Operation 'Source database metadata check'
    $metadataParts = @($sourceMetadata -split "`t")

    if ($metadataParts.Count -ne 2 -or $metadataParts[0] -cnotmatch '^[a-zA-Z0-9_]+$' -or $metadataParts[1] -cnotmatch '^[a-zA-Z0-9_]+$') {
        throw 'Source database character-set metadata was missing or unsafe.'
    }

    $sourceCharacterSet = $metadataParts[0]
    $sourceCollation = $metadataParts[1]
    $storageEngineText = Invoke-MySqlQuery -ContainerId $containerId -Sql "SELECT DISTINCT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$SourceDatabase' AND TABLE_TYPE = 'BASE TABLE' ORDER BY ENGINE;" -Operation 'Source storage-engine check'
    $storageEngines = @($storageEngineText -split "`r?`n" | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })

    if ($storageEngines.Count -ne 1 -or $storageEngines[0] -cne 'InnoDB') {
        throw "Refusing online dump: source contains non-InnoDB or mixed storage engines ($($storageEngines -join ', '))."
    }

    $tableListText = Invoke-MySqlQuery -ContainerId $containerId -Sql "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$SourceDatabase' AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME;" -Operation 'Source table inventory'
    $sourceTables = @($tableListText -split "`r?`n" | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })

    if ($sourceTables.Count -lt 1 -or 'migrations' -notin $sourceTables) {
        throw 'Refusing database drill: source table inventory is empty or lacks the migrations table.'
    }

    $sourceSchemaManifest = Get-SchemaManifest -ContainerId $containerId -Database $SourceDatabase
    $sourceSchemaHash = Get-TextSha256 -Text $sourceSchemaManifest
    $sourceMigrationManifest = Invoke-MySqlQuery -ContainerId $containerId -Sql "SELECT migration, batch FROM ``$SourceDatabase``.``migrations`` ORDER BY id;" -Operation 'Source migration manifest'
    $sourceMigrationHash = Get-TextSha256 -Text $sourceMigrationManifest

    Assert-ContainerStillExact -ContainerId $containerId
    Invoke-Docker -Arguments @('exec', $containerId, 'test', '!', '-e', $dumpPath) -Operation 'Dump-path absence check' | Out-Null
    $dumpShellScript = 'umask 077;export MYSQL_PWD=$MYSQL_ROOT_PASSWORD;mysqldump --single-transaction --quick --skip-lock-tables --no-tablespaces --routines --triggers --events --hex-blob --set-gtid-purged=OFF --default-character-set=utf8mb4 -h 127.0.0.1 -u root ' + $SourceDatabase + ' > ' + $dumpPath
    $dumpOutput = Invoke-Docker -Arguments @('exec', $containerId, 'sh', '-ceu', $dumpShellScript) -Operation 'Read-only consistent MySQL dump'

    if (-not [string]::IsNullOrWhiteSpace($dumpOutput)) {
        throw 'mysqldump produced unexpected standard output.'
    }

    $dumpCreatedByThisRun = $true
    Invoke-Docker -Arguments @('exec', $containerId, 'test', '-f', $dumpPath) -Operation 'Dump file-type check' | Out-Null
    $dumpStat = Invoke-Docker -Arguments @('exec', $containerId, 'stat', '-c', '%a:%s', $dumpPath) -Operation 'Dump permission and size inspection'
    $dumpHashOutput = Invoke-Docker -Arguments @('exec', $containerId, 'sha256sum', $dumpPath) -Operation 'Dump SHA-256 inspection'
    $dumpParts = @($dumpStat -split ':')
    $dumpBytes = 0L

    if ($dumpParts.Count -ne 2 -or $dumpParts[0] -cne '600' -or -not [long]::TryParse($dumpParts[1], [ref] $dumpBytes) -or $dumpBytes -lt 1) {
        throw 'Dump file size, permissions, or SHA-256 evidence was invalid.'
    }

    $dumpHashParts = @($dumpHashOutput -split '\s+')

    if ($dumpHashParts.Count -lt 1 -or $dumpHashParts[0] -cnotmatch '^[0-9a-f]{64}$') {
        throw 'Dump SHA-256 evidence was invalid.'
    }

    $dumpSha256 = $dumpHashParts[0]
    $sourceRowCounts = Get-TableRowCounts -ContainerId $containerId -Database $SourceDatabase -Tables $sourceTables

    Assert-ContainerStillExact -ContainerId $containerId
    Invoke-MySqlQuery -ContainerId $containerId -Sql "CREATE DATABASE ``$RestoreDatabase`` CHARACTER SET $sourceCharacterSet COLLATE $sourceCollation;" -Operation 'Create bounded restore database' | Out-Null
    $restoreCreatedByThisRun = $true

    $createdRestoreCount = Invoke-MySqlQuery -ContainerId $containerId -Sql "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$RestoreDatabase';" -Operation 'Restore database creation verification'

    if ($createdRestoreCount.Trim() -cne '1') {
        throw 'Bounded restore database was not created exactly once.'
    }

    Invoke-Docker -Arguments @('exec', $containerId, 'test', '-f', $dumpPath) -Operation 'Restore dump presence check' | Out-Null
    $restoreShellScript = 'export MYSQL_PWD=$MYSQL_ROOT_PASSWORD;mysql --binary-mode=1 -h 127.0.0.1 -u root ' + $RestoreDatabase + ' < ' + $dumpPath
    Invoke-Docker -Arguments @('exec', $containerId, 'sh', '-ceu', $restoreShellScript) -Operation 'Restore dump into bounded database' | Out-Null

    $restoreTablesText = Invoke-MySqlQuery -ContainerId $containerId -Sql "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$RestoreDatabase' AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME;" -Operation 'Restored table inventory'
    $restoreTables = @($restoreTablesText -split "`r?`n" | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })

    if (($sourceTables -join "`n") -cne ($restoreTables -join "`n")) {
        throw 'Restored table inventory does not exactly match the source inventory.'
    }

    $restoreSchemaManifest = Get-SchemaManifest -ContainerId $containerId -Database $RestoreDatabase
    $restoreSchemaHash = Get-TextSha256 -Text $restoreSchemaManifest

    if ($restoreSchemaHash -cne $sourceSchemaHash) {
        throw "Restored schema hash mismatch: source $sourceSchemaHash, restore $restoreSchemaHash."
    }

    $restoreMigrationManifest = Invoke-MySqlQuery -ContainerId $containerId -Sql "SELECT migration, batch FROM ``$RestoreDatabase``.``migrations`` ORDER BY id;" -Operation 'Restored migration manifest'
    $restoreMigrationHash = Get-TextSha256 -Text $restoreMigrationManifest

    if ($restoreMigrationHash -cne $sourceMigrationHash) {
        throw "Restored migration manifest hash mismatch: source $sourceMigrationHash, restore $restoreMigrationHash."
    }

    $restoreRowCounts = Get-TableRowCounts -ContainerId $containerId -Database $RestoreDatabase -Tables $restoreTables

    foreach ($table in $sourceTables) {
        if ([long] $sourceRowCounts[$table] -ne [long] $restoreRowCounts[$table]) {
            throw "Restored row-count mismatch for '$table': source $($sourceRowCounts[$table]), restore $($restoreRowCounts[$table])."
        }
    }

    $selectedCounts = [ordered] @{}

    foreach ($selectedTable in @('migrations', 'users', 'providers', 'provider_branches', 'service_categories', 'services', 'bookings', 'booking_participants', 'payments')) {
        if ($sourceRowCounts.Contains($selectedTable)) {
            $selectedCounts[$selectedTable] = [long] $sourceRowCounts[$selectedTable]
        }
    }

    $migrationCount = [long] $sourceRowCounts['migrations']
    $result = [ordered] @{
        Status = 'PASS'
        ComposeProject = $ComposeProject
        Container = $expectedContainerName
        ContainerId = $containerId.Substring(0, 12)
        Image = [string] $inspection.Config.Image
        MySqlVersion = $mysqlVersion
        SourceDatabase = $SourceDatabase
        RestoreDatabase = $RestoreDatabase
        SourceTableCount = $sourceTables.Count
        MigrationCount = $migrationCount
        SchemaSha256 = $sourceSchemaHash
        MigrationManifestSha256 = $sourceMigrationHash
        DumpBytes = $dumpBytes
        DumpSha256 = $dumpSha256
        SelectedRowCounts = $selectedCounts
    }
}
catch {
    $primaryFailure = $_
}
finally {
    if ($dumpCreatedByThisRun -or $restoreCreatedByThisRun) {
        try {
            Assert-ContainerStillExact -ContainerId $containerId
        }
        catch {
            $cleanupFailures.Add("Cleanup identity validation failed; no cleanup was attempted: $($_.Exception.Message)")
        }

        if ($cleanupFailures.Count -eq 0 -and $dumpCreatedByThisRun) {
            try {
                if ($dumpPath -cnotmatch '^/tmp/takein_phase8_gate_youyaku_phase9_restore_[0-9a-f]{32}\.sql$') {
                    throw 'Dump cleanup target escaped the approved bounded path.'
                }

                Invoke-Docker -Arguments @('exec', $containerId, 'test', '-f', $dumpPath) -Operation 'Bounded dump cleanup preflight' | Out-Null
                Invoke-Docker -Arguments @('exec', $containerId, 'rm', '-f', '--', $dumpPath) -Operation 'Bounded dump cleanup' | Out-Null
                Invoke-Docker -Arguments @('exec', $containerId, 'test', '!', '-e', $dumpPath) -Operation 'Bounded dump cleanup verification' | Out-Null
                $cleanupEvidence.DumpRemoved = $true
            }
            catch {
                $cleanupFailures.Add($_.Exception.Message)
            }
        }

        if ($cleanupFailures.Count -eq 0 -and $restoreCreatedByThisRun) {
            try {
                if ($RestoreDatabase -cnotmatch '^youyaku_phase9_restore$' -or $RestoreDatabase -ceq $SourceDatabase) {
                    throw 'Restore cleanup target escaped the approved bounded database namespace.'
                }

                $restoreCountBeforeDrop = Invoke-MySqlQuery -ContainerId $containerId -Sql "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$RestoreDatabase';" -Operation 'Restore cleanup preflight'

                if ($restoreCountBeforeDrop.Trim() -cne '1') {
                    throw 'Restore cleanup requires exactly one bounded restore database.'
                }

                Invoke-MySqlQuery -ContainerId $containerId -Sql "DROP DATABASE ``$RestoreDatabase``;" -Operation 'Drop bounded restore database' | Out-Null
                $restoreCountAfterDrop = Invoke-MySqlQuery -ContainerId $containerId -Sql "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$RestoreDatabase';" -Operation 'Restore cleanup verification'
                $sourceCountAfterCleanup = Invoke-MySqlQuery -ContainerId $containerId -Sql "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$SourceDatabase';" -Operation 'Source preservation verification'

                if ($restoreCountAfterDrop.Trim() -cne '0' -or $sourceCountAfterCleanup.Trim() -cne '1') {
                    throw 'Restore cleanup verification failed or the source database is no longer present.'
                }

                $cleanupEvidence.RestoreDatabaseDropped = $true
            }
            catch {
                $cleanupFailures.Add($_.Exception.Message)
            }
        }
    }
}

if ($null -ne $primaryFailure -or $cleanupFailures.Count -gt 0) {
    $messages = [System.Collections.Generic.List[string]]::new()

    if ($null -ne $primaryFailure) {
        $messages.Add($primaryFailure.Exception.Message)
    }

    foreach ($cleanupFailure in $cleanupFailures) {
        $messages.Add($cleanupFailure)
    }

    throw "Backup/restore drill FAILED: $($messages -join ' | ')"
}

$result.Cleanup = $cleanupEvidence
$result | ConvertTo-Json -Depth 5
Write-Output 'Backup/restore drill: PASS (source remained present; bounded restore database and dump file were removed).'
