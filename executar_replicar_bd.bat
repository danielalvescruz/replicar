@echo off
mode con: cols=130 lines=45

if exist "D:\Daniel Alves\Trabalho\Daniel Alves\replicar" (
    php "D:\Daniel Alves\Trabalho\Daniel Alves\replicar\replicar_bd.php"
) else (
    php "D:\Clientes\Daniel Alves\Replicar\replicar_bd.php"
)

pause