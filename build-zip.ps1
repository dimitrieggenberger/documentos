$ErrorActionPreference = 'Stop'

$pluginFolder = 'documents'
$component = 'local_documents'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$dist = Join-Path $root 'dist'
$build = Join-Path $root 'build\zip'
$stagePlugin = Join-Path $build $pluginFolder

# Excluded wherever they appear.
$excludedDirectories = @(
    '.git',
    '.github',
    '.claude',
    '.vscode',
    '.idea',
    'node_modules',
    'vendor',
    'logs',
    'docs',
    'dev',
    'wordpress',
    'tmp',
    'temp',
    '.cache'
)

# Excluded only at the repository root: this script's own output. A plugin's
# amd/build holds the compiled JavaScript Moodle loads and must ship.
$excludedRootDirectories = @(
    'build',
    'dist'
)

$excludedFilePatterns = @(
    '*.zip',
    '*.tar',
    '*.tar.gz',
    '*.tgz',
    '*.7z',
    '*.log',
    '*.tmp',
    '*.bak',
    '*.swp',
    '*~',
    '.DS_Store',
    'Thumbs.db',
    'desktop.ini',
    '.gitignore',
    '.gitattributes',
    'AGENTS.md',
    'README.md',
    'build-zip.ps1'
)

if (-not (Test-Path -LiteralPath (Join-Path $root 'version.php'))) {
    throw 'version.php was not found. Run this script from the Moodle plugin repository root.'
}

if (Test-Path -LiteralPath $build) {
    Remove-Item -LiteralPath $build -Recurse -Force
}

if (Test-Path -LiteralPath $dist) {
    Remove-Item -LiteralPath $dist -Recurse -Force
}

New-Item -ItemType Directory -Path $stagePlugin -Force | Out-Null
New-Item -ItemType Directory -Path $dist -Force | Out-Null

$rootPath = (Resolve-Path -LiteralPath $root).Path.TrimEnd('\')

Get-ChildItem -LiteralPath $root -Force -Recurse -File | ForEach-Object {
    $relativePath = $_.FullName.Substring($rootPath.Length + 1)
    $parts = $relativePath -split '[\\/]'

    foreach ($directory in $excludedDirectories) {
        if ($parts -contains $directory) {
            return
        }
    }

    if ($parts.Count -gt 1 -and $excludedRootDirectories -contains $parts[0]) {
        return
    }

    foreach ($pattern in $excludedFilePatterns) {
        if ($_.Name -like $pattern) {
            return
        }
    }

    $target = Join-Path $stagePlugin $relativePath
    $targetDirectory = Split-Path -Parent $target

    if (-not (Test-Path -LiteralPath $targetDirectory)) {
        New-Item -ItemType Directory -Path $targetDirectory -Force | Out-Null
    }

    Copy-Item -LiteralPath $_.FullName -Destination $target -Force
}

$versionFile = Get-Content -LiteralPath (Join-Path $root 'version.php') -Raw
$version = if ($versionFile -match '\$plugin->version\s*=\s*([0-9]+)') { $Matches[1] } else { 'unknown' }
$zipName = "$component-$version.zip"
$zipPath = Join-Path $dist $zipName

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

if (Test-Path -LiteralPath $zipPath) {
    Remove-Item -LiteralPath $zipPath -Force
}

$zipArchive = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    $stageRootPath = (Resolve-Path -LiteralPath $build).Path.TrimEnd('\')
    Get-ChildItem -LiteralPath $stagePlugin -Recurse -File | ForEach-Object {
        $entryName = $_.FullName.Substring($stageRootPath.Length + 1) -replace '\\', '/'
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zipArchive, $_.FullName, $entryName) | Out-Null
    }
} finally {
    $zipArchive.Dispose()
}

Write-Host 'Created Moodle plugin ZIP:'
Write-Host $zipPath

# The public verification page is a WordPress plugin of its own (wordpress/),
# shipped as a second ZIP with one root folder for Plugins > Add New > Upload.
$wpFolder = 'es-verificacion'
$wpSource = Join-Path $root "wordpress\$wpFolder"
if (Test-Path -LiteralPath $wpSource) {
    $wpFile = Get-Content -LiteralPath (Join-Path $wpSource "$wpFolder.php") -Raw
    $wpVersion = if ($wpFile -match 'Version:\s*([0-9.]+)') { $Matches[1] } else { 'unknown' }
    $wpZipPath = Join-Path $dist "$wpFolder-$wpVersion.zip"
    $wpArchive = [System.IO.Compression.ZipFile]::Open($wpZipPath, [System.IO.Compression.ZipArchiveMode]::Create)
    try {
        $wpRootPath = (Resolve-Path -LiteralPath (Join-Path $root 'wordpress')).Path.TrimEnd('\')
        Get-ChildItem -LiteralPath $wpSource -Recurse -File | ForEach-Object {
            $entryName = $_.FullName.Substring($wpRootPath.Length + 1) -replace '\\', '/'
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($wpArchive, $_.FullName, $entryName) | Out-Null
        }
    } finally {
        $wpArchive.Dispose()
    }
    Write-Host 'Created WordPress plugin ZIP:'
    Write-Host $wpZipPath
}
