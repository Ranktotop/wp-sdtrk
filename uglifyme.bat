@echo off
 
for %%F in (%*) do uglifyjs -o "%%~dpnF.min%%~xF" "%%~dpnF%%~xF" --compress sequences=true,conditionals=true,booleans=true --mangle
 
exit