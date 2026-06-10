@echo off
set "USER_ID=%USERDOMAIN%\%USERNAME%"
netsh http add urlacl url=http://127.0.0.1:38654/ user=%USER_ID%
pause
