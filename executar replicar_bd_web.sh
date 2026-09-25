#!/bin/bash
# Abre a versão web do replicar de banco de dados (a pasta não importa aqui).
# Funciona na máquina do Daniel e no servidor do Ricardo.

REPLICAR_SERVIDOR="/home/ricardo/web/dev.aguiarsoftware.com.br/public_html/replicar"
REPLICAR_LOCAL="/media/daniel_alves/Novo volume/Clientes/Daniel Alves/Replicar"
if [ -d "$REPLICAR_SERVIDOR" ]; then
    REPLICAR="$REPLICAR_SERVIDOR"
else
    REPLICAR="$REPLICAR_LOCAL"
fi

bash "$REPLICAR/replicar_web.sh" "" "banco.php"
