@echo off
REM Auto-commit and push script for gym-manager
REM Usage: Run this after making changes

cd /d "C:\Users\girum\Desktop\gym management\GYM-One"

REM Check if there are changes
git status --porcelain

if %ERRORLEVEL%==0 (
    echo No changes to commit.
    pause
    exit /b
)

REM Add all changes
git add -A

REM Get date and time for commit message
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set datetime=%%I
set commitmsg=Update: %datetime:~0,8% %datetime:~8,6%

REM Commit and push
git commit -m "%commitmsg%"
git push

echo Changes pushed to GitHub successfully!
pause
