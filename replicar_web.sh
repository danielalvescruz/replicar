#!/bin/bash
# Abre a versão web do Replicar no navegador, já com a pasta preenchida.
#   replicar_web.sh <pasta> [pagina]    (pagina: vazio = Replicar arquivos, "banco.php", "sites.php")
#
# - Máquina do Daniel: sobe o servidor embutido do PHP (só em 127.0.0.1) se ainda não estiver rodando.
# - Servidor do Ricardo: a página já está no site (https://dev.aguiarsoftware.com.br/replicar/web/),
#   então só mostra o endereço (e abre o navegador, se houver um nesta sessão).

PASTA_ALVO="$1"
PAGINA="${2:-}"
DIR_REPLICAR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
URL_SERVIDOR="https://dev.aguiarsoftware.com.br/replicar/web/"
PORTA=8765

urlencode() { php -r 'echo rawurlencode($argv[1]);' "$1"; }

if [[ "$DIR_REPLICAR" == /home/ricardo/web/* ]]; then
    URL="$URL_SERVIDOR$PAGINA"
    [ -n "$PASTA_ALVO" ] && URL="$URL?pasta=$(urlencode "$PASTA_ALVO")"
    echo "Abra no navegador:"
    echo "$URL"
    if [ -n "$DISPLAY" ] || [ -n "$WAYLAND_DISPLAY" ]; then
        xdg-open "$URL" > /dev/null 2>&1 &
    fi
    exit 0
fi

if ! (exec 3<>/dev/tcp/127.0.0.1/$PORTA) 2>/dev/null; then
    # Mais de um worker pra página continuar respondendo enquanto uma replicação está rodando
    # setsid desliga o servidor do processo que chamou (Dolphin/Konsole), pra ele não morrer junto
    # quando o script termina. O log fica em /tmp pra ajudar se algo der errado.
    PHP_CLI_SERVER_WORKERS=4 setsid nohup php -S 127.0.0.1:$PORTA -t "$DIR_REPLICAR/web" \
        < /dev/null > /tmp/replicar_web.log 2>&1 &
    for i in $(seq 1 20); do
        (exec 3<>/dev/tcp/127.0.0.1/$PORTA) 2>/dev/null && break
        sleep 0.2
    done
fi

URL="http://127.0.0.1:$PORTA/$PAGINA"
[ -n "$PASTA_ALVO" ] && URL="$URL?pasta=$(urlencode "$PASTA_ALVO")"
xdg-open "$URL" > /dev/null 2>&1 &
