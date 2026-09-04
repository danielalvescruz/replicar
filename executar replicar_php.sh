#!/bin/bash

# Pasta onde este script foi colocado/executado (a pasta de trabalho real)
PASTA_ALVO="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

chmod +x "/media/daniel_alves/Novo volume/Clientes/Daniel Alves/Replicar/replicar_php.sh"
konsole -e "/media/daniel_alves/Novo volume/Clientes/Daniel Alves/Replicar/replicar_php.sh" "$PASTA_ALVO"