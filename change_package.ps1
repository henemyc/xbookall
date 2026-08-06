# Change package name from com.gymxbook.gymxbook to com.gymxbook.app
# Run this in PowerShell from the project root

$projectRoot = "C:\flutter_project\gymxbook_flutter"
$oldPkg = "com.gymxbook.gymxbook"
$newPkg = "com.gymxbook.app"
$oldPath = "com\gymxbook\gymxbook"
$newPath = "com\gymxbook\app"

Write-Host "Changing package name to $newPkg ..." -ForegroundColor Cyan

# 1. build.gradle.kts
$buildGradle = "$projectRoot\android\app\build.gradle.kts"
(Get-Content $buildGradle) -replace $oldPkg, $newPkg | Set-Content $buildGradle
Write-Host "✓ build.gradle.kts" -ForegroundColor Green

# 2. AndroidManifest.xml (if exists)
$manifest = "$projectRoot\android\app\src\main\AndroidManifest.xml"
if (Test-Path $manifest) {
    (Get-Content $manifest) -replace $oldPkg, $newPkg | Set-Content $manifest
    Write-Host "✓ AndroidManifest.xml" -ForegroundColor Green
}

# 3. Move Kotlin folder
$kotlinBase = "$projectRoot\android\app\src\main\kotlin"
$oldDir = "$kotlinBase\$oldPath"
$newDir = "$kotlinBase\$newPath"

if (Test-Path $oldDir) {
    New-Item -ItemType Directory -Force -Path $newDir | Out-Null
    Copy-Item "$oldDir\*" $newDir -Force
    # Update package declaration in MainActivity.kt
    $mainActivity = "$newDir\MainActivity.kt"
    if (Test-Path $mainActivity) {
        (Get-Content $mainActivity) -replace $oldPkg, $newPkg | Set-Content $mainActivity
        Write-Host "✓ MainActivity.kt" -ForegroundColor Green
    }
    Remove-Item $oldDir -Recurse -Force
    Write-Host "✓ Moved Kotlin files" -ForegroundColor Green
} else {
    Write-Host "⚠ Kotlin folder not found at $oldDir" -ForegroundColor Yellow
}

# 4. pubspec.yaml — update version if needed
$pubspec = "$projectRoot\pubspec.yaml"
(Get-Content $pubspec) -replace "version: \d+\.\d+\.\d+\+\d+", "version: 1.0.0+1" | Set-Content $pubspec
Write-Host "✓ pubspec.yaml (version 1.0.0+1)" -ForegroundColor Green

# 5. Clean
Write-Host "`nRunning flutter clean..." -ForegroundColor Cyan
Set-Location $projectRoot
flutter clean

Write-Host "`nDone! Package name changed to $newPkg" -ForegroundColor Green
Write-Host "Run: flutter pub get && flutter build appbundle --release" -ForegroundColor Yellow
