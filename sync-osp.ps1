# sync-osp.ps1 — зеркало проекта в домен OpenServer Panel.
# Запуск: powershell -ExecutionPolicy Bypass -File sync-osp.ps1
# или двойной клик по sync-osp.cmd

param(
    [string]$Src = "G:\AI_projects\CMS_for_ai",
    [string]$Dst = "G:\OSPanel\home\cms-ai.test"
)

if (-not (Test-Path $Src)) { Write-Error "Нет исходной папки: $Src"; exit 1 }

New-Item -ItemType Directory -Force -Path $Dst | Out-Null

# Runtime-файлы назначения удаляем (они не являются частью пакета)
Remove-Item "$Dst\core\config.php","$Dst\core\cms.sqlite","$Dst\core\php-error.log","$Dst\install.done.php" -Force -ErrorAction SilentlyContinue
Remove-Item "$Dst\core\cache" -Recurse -Force -ErrorAction SilentlyContinue

robocopy $Src $Dst /MIR /NJH /NJS /NDL /NFL /NP `
    /XD "$Src\core\cache" "$Src\.git" "$Src\tools" `
    /XF "core\config.php" "install.done.php" "core\php-error.log" "core\cms.sqlite" "Thumbs.db" ".DS_Store"

# robocopy: 0..7 — успех, >=8 — ошибка
if ($LASTEXITCODE -ge 8) {
    Write-Error "robocopy завершился с кодом $LASTEXITCODE"
    exit $LASTEXITCODE
}

Write-Host "`nСинхронизировано: $Src -> $Dst" -ForegroundColor Green
Write-Host "Открыть: http://cms-ai.test  (после запуска OpenServer Panel)"
exit 0
