#!/bin/bash

# 1. Pasta alvo recebida como parâmetro (pasta onde o executar_replicar_php.sh foi colocado)
PASTA_ALVO="$1"

# Fallback: se não recebeu parâmetro (execução direta/antiga), usa a pasta do próprio script
if [ -z "$PASTA_ALVO" ]; then
    PASTA_ALVO="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
fi

# 2. Identifica se está rodando via Dolphin em conexão remota (KIO-Fuse) ou Localmente
if [[ "$PASTA_ALVO" == *"kio-fuse"* ]]; then
    # --- AMBIENTE SERVIDOR REMOTO (RICARDO) ---
    REMOTE_DIR=$(echo "$PASTA_ALVO" | sed -E 's|.*/sftp/[^/]+||')
    echo "Executando no Servidor Remoto..."
    echo "Pasta remota: $REMOTE_DIR"
    echo "---------------------------------------------------"

    ssh -t root@100.96.10.71 "cd '$REMOTE_DIR' && php /home/ricardo/web/dev.aguiarsoftware.com.br/public_html/replicar/replicar.php"

else
    # --- AMBIENTE MÁQUINA LOCAL (DANIEL ALVES) ---
    echo "Executando no Linux Local..."
    echo "Pasta local: $PASTA_ALVO"
    echo "---------------------------------------------------"

    # Navega até a pasta onde o script foi executado e chama o PHP local
    cd "$PASTA_ALVO" && php "/media/daniel_alves/Novo volume/Clientes/Daniel Alves/Replicar/replicar.php"
fi

echo ""
read -p "Pressione ENTER para sair..."
exit