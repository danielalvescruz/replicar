#!/bin/bash
# Jogue este arquivo na pasta que quer replicar e execute: abre a versão web do Replicar com a pasta
# preenchida. Funciona na máquina do Daniel e no servidor do Ricardo (cada um usa a sua pasta Replicar).

# Pasta onde este script foi colocado/executado (a pasta de trabalho real)
PASTA_ALVO="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

REPLICAR_SERVIDOR="/home/ricardo/web/dev.aguiarsoftware.com.br/public_html/replicar"
REPLICAR_LOCAL="/media/daniel_alves/Novo volume/Clientes/Daniel Alves/Replicar"
if [ -d "$REPLICAR_SERVIDOR" ]; then
    REPLICAR="$REPLICAR_SERVIDOR"
else
    REPLICAR="$REPLICAR_LOCAL"
fi

bash "$REPLICAR/replicar_web.sh" "$PASTA_ALVO"
