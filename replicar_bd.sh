#!/bin/bash
# Roda o replicar de banco de dados (replicar_bd.php).
# - No servidor do Ricardo: roda direto aqui.
# - Na máquina do Daniel: roda no servidor do Ricardo via SSH (alias ricardo-bay, usuário daniel),
#   já que o PHP local não tem o mysqli.

REPLICAR_SERVIDOR="/home/ricardo/web/dev.aguiarsoftware.com.br/public_html/replicar"

if [ -d "$REPLICAR_SERVIDOR" ]; then
    cd "$REPLICAR_SERVIDOR" && php replicar_bd.php
else
    ssh -t ricardo-bay "cd '$REPLICAR_SERVIDOR' && php replicar_bd.php"
fi

read -p "Pressione ENTER para sair..."
