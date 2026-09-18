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

    # Usa o alias ricardo-bay (~/.ssh/config -> User daniel, chave ed25519) em vez de root@,
    # assim conecta com a sua chave e nao pede senha nenhuma
    #
    # A saida completa e capturada num arquivo temporario (pra checar depois se o envio
    # SFTP pro Note 1 falhou) e tambem mostrada na tela em tempo real via tee. Nao filtramos
    # nada ao vivo (grep quebrava o eco do que voce digita nos prompts interativos do PHP,
    # ja que grep so libera a saida linha a linha) - a mensagem do git abaixo so complementa
    # o aviso quando o erro acontecer.
    LOG_TEMP=$(mktemp)
    ssh -t ricardo-bay "cd '$REMOTE_DIR' && php /home/ricardo/web/dev.aguiarsoftware.com.br/public_html/replicar/replicar.php" | tee "$LOG_TEMP"

    # Se deu o erro de SFTP/chave pro Note 1, limpa a tela e reimprime o log inteiro sem
    # essa linha, trocando por um aviso pra copiar manualmente pro git. A filtragem so
    # acontece DEPOIS que o processo termina (nao durante), pra nao travar o eco do que
    # voce digita nos prompts interativos do PHP - filtrar ao vivo com grep quebra isso,
    # pois grep so libera a saida linha a linha completa.
    if grep -qE "Erro: login SFTP falhou|Erro: chave SSH não encontrada" "$LOG_TEMP"; then
        clear
        grep -vE "Erro: login SFTP falhou|Erro: chave SSH não encontrada" "$LOG_TEMP"
        echo ""
        echo "Copie ou coloque o(s) arquivo(s) replicado(s) no git"
    fi
    rm -f "$LOG_TEMP"

else
    # --- AMBIENTE MÁQUINA LOCAL (DANIEL ALVES) ---
    echo "Executando no Linux Local..."
    echo "Pasta local: $PASTA_ALVO"
    echo "---------------------------------------------------"

    # Navega até a pasta onde o script foi executado e chama o PHP local
    cd "$PASTA_ALVO" && php "/media/daniel_alves/Novo volume/Clientes/Daniel Alves/Replicar/replicar.php"
fi

echo ""
sleep 3

# Forca o fechamento da janela do Konsole via D-Bus (mais confiavel que $WINDOWID,
# que nessa acao especifica do Dolphin vem com um valor invalido). O Konsole exporta
# KONSOLE_DBUS_SERVICE e KONSOLE_DBUS_WINDOW pra identificar a propria janela/sessao.
# Usamos dbus-send (ja vem no sistema) em vez de qdbus (nao instalado e com dependencia
# quebrada nesta maquina).
if [ -n "$KONSOLE_DBUS_SERVICE" ] && [ -n "$KONSOLE_DBUS_WINDOW" ]; then
    dbus-send --session --dest="$KONSOLE_DBUS_SERVICE" "$KONSOLE_DBUS_WINDOW" org.kde.konsole.Window.close
fi
exit