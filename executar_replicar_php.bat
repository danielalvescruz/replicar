@echo off
mode con: cols=130 lines=45

if exist "C:\Daniel Alves\Trabalho\Daniel Alves\replicar" (
    php "C:\Daniel Alves\Trabalho\Daniel Alves\replicar\replicar.php"
) else (
    php "D:\Clientes\Daniel Alves\Replicar\replicar.php"
)

pause