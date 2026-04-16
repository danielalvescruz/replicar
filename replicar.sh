#!/bin/bash
ano="2024"
nomearquivo=$(ls -1t | head -1)
echo "Ultimo arquivo alterado encontrado: "$nomearquivo
read -p 'Se quser digite o caminho completo ou o nome do arquivo que deseja replicar: ' nomecompletoarquivo;
if [[ -z "$nomecompletoarquivo" ]]; then
    nomecompletoarquivo=$nomearquivo
fi
echo "nomecompletoarquivo = "$nomecompletoarquivo
if [[ $nomecompletoarquivo == *"/"* ]]; then
    diretorio_atual=$(pwd)
    diretorio_atual=${diretorio_atual/$ano*/}$ano"/"$nomecompletoarquivo
    pasta=$(dirname "$nomecompletoarquivo")
else
   diretorio_atual=$(pwd)
   pasta=${diretorio_atual#*$ano/}
fi
nomearquivo=$(basename "$nomecompletoarquivo")
echo "Diretorio Autal = "$diretorio_atual
echo "Extraindo pasta = "$pasta
echo "Extraindo arquivo = "$nomearquivo
read -p "Verifique se está tudo ok - Pressione enter para continuar e s para saír" cont
if [ "$cont" == "s" ] || [ "$cont" == "S" ]; then
    exit
fi
echo "Continuando..."

while true; do
    read -p 'Deseja atualizar a Sacos Teste? ' atsacost;
    case $atsacost in
        [Ss]* ) break;;
        [Nn]* ) break;;
        * ) echo "Por favor responda s ou n.";
    esac
done

if [ "$atsacost" == "s" ] || [ "$atsacost" == "S" ]; then
    echo "Atualizando Sacos Bay Plastic"
    if [ -d "/media/daniel_alves/Novo volume/Clientes/Aguiarsoft Ricardo/Sacos Bay Plastic/$ano/$pasta" ]; then
        HOST="vps4.nunesti.com"
        USER="sacosbayplastic"
        PASS="3Caras&1Fera#"
        SOURCEFOLDER="/media/daniel_alves/Novo volume/Clientes/Aguiarsoft Ricardo/Sacos Bay Plastic/$ano/$pasta"
        TARGETFOLDER="$pasta"
        
        if [ "$nomearquivo" != "*" ]; then
            echo "Copiando para a pasta $pasta"
            cp "$nomearquivo" "/media/daniel_alves/Novo volume/Clientes/Aguiarsoft Ricardo/Sacos Bay Plastic/$ano/$pasta/"
            # Acessa o Servidor FTP e manda o arquivo
            lftp -u $USER,$PASS $HOST << EOF
                set ftp:ssl-allow no;
                cd httpdocs/teste\$\$\$/administrator/components/$pasta
                put "$nomearquivo"
                bye
EOF
        else
        cp -r "$diretorio_atual/." "/media/daniel_alves/Novo volume/Clientes/Aguiarsoft Ricardo/Sacos Bay Plastic/$ano/$pasta"
        # Acessa o Servidor FTP e manda a pasta toda
            lftp -u $USER,$PASS $HOST << EOF
                set ftp:ssl-allow no;
                cd httpdocs/teste\$\$\$/administrator/components/$pasta
                mput "$SOURCEFOLDER/*"            
EOF
        fi
    fi
fi

while true; do
    read -p 'Deseja atualizar a Sacos? ' atsacos;
    case $atsacos in
        [Ss]* ) break;;
        [Nn]* ) break;;
        * ) echo "Por favor responda s ou n.";
    esac
done
if [ "$atsacos" == "s" ] || [ "$atsacos" == "S" ]; then
    echo "Atualizando Sacos Bay Plastic"
    if [ -d "/media/daniel_alves/Novo volume/Clientes/Aguiarsoft Ricardo/Sacos Bay Plastic/$ano/$pasta" ]; then
        HOST="vps4.nunesti.com"
        USER="sacosbayplastic"
        PASS="3Caras&1Fera#"
        SOURCEFOLDER="/media/daniel_alves/Novo volume/Clientes/Aguiarsoft Ricardo/Sacos Bay Plastic/$ano/$pasta"
        TARGETFOLDER="$pasta"
        
        if [ "$nomearquivo" != "*" ]; then
            echo "Copiando para a pasta $pasta"
            cp "$nomearquivo" "/media/daniel_alves/Novo volume/Clientes/Aguiarsoft Ricardo/Sacos Bay Plastic/$ano/$pasta/"
            # Acessa o Servidor FTP e manda o arquivo
            lftp -u $USER,$PASS $HOST << EOF
                set ftp:ssl-allow no;
                cd httpdocs/site/administrator/components/$pasta
                put "$nomearquivo"
                bye
EOF
        else
        cp -r "$diretorio_atual/." "/media/daniel_alves/Novo volume/Clientes/Aguiarsoft Ricardo/Sacos Bay Plastic/$ano/$pasta"
        # Acessa o Servidor FTP e manda a pasta toda
            lftp -u $USER,$PASS $HOST << EOF
                set ftp:ssl-allow no;
                cd httpdocs/site/administrator/components/$pasta
                mput "$SOURCEFOLDER/*"            
EOF
        fi
    fi
fi

while true; do
    read -p 'Deseja atualizar o Comary? ' atcom;
    case $atcom in
        [Ss]* ) break;;
        [Nn]* ) break;;
        * ) echo "Por favor responda s ou n.";
    esac
done
if [ "$atcom" == "s" ] || [ "$atcom" == "S" ]; then
    echo "Atualizando Clube Comary"
    if [ -d "/media/daniel_alves/Novo volume/Clientes/Aguiarsoft Ricardo/Clube Comary/$ano/$pasta" ]; then
        HOST="vps4.nunesti.com"
        USER="clubecomary"
        PASS="3Caras&1Fera#"
        SOURCEFOLDER="/media/daniel_alves/Novo volume/Clientes/Aguiarsoft Ricardo/Clube Comary/$ano/$pasta"
        TARGETFOLDER="$pasta"
        
        if [ "$nomearquivo" != "*" ]; then
            echo "Copiando para a pasta $pasta"
            cp "$nomearquivo" "/media/daniel_alves/Novo volume/Clientes/Aguiarsoft Ricardo/Clube Comary/$ano/$pasta/"
            # Acessa o Servidor FTP e manda o arquivo
            lftp -u $USER,$PASS $HOST << EOF
                set ftp:ssl-allow no;
                cd httpdocs/site/administrator/components/$pasta
                put "$nomearquivo"
                bye
EOF
        else
        cp -r "$diretorio_atual/." "/media/daniel_alves/Novo volume/Clientes/Aguiarsoft Ricardo/Clube Comary/$ano/$pasta"
        # Acessa o Servidor FTP e manda a pasta toda
            lftp -u $USER,$PASS $HOST << EOF
                set ftp:ssl-allow no;
                cd httpdocs/site/administrator/components/$pasta
                mput "$SOURCEFOLDER/*"            
EOF
        fi
    fi
fi

while true; do
    read -p 'Deseja atualizar o IFEN? ' atif;
    case $atif in
        [Ss]* ) break;;
        [Nn]* ) break;;
        * ) echo "Por favor responda s ou n.";
    esac
done
if [ "$atif" == "s" ] || [ "$atif" == "S" ]; then
    echo "Atualizando IFEN"
    if [ -d "/media/daniel_alves/Novo volume/Clientes/Aguiarsoft Ricardo/IFEN/$ano/$pasta" ]; then
        HOST="vps4.nunesti.com"
        USER="ifen"
        PASS="3Caras&1Fera#"
        SOURCEFOLDER="/media/daniel_alves/Novo volume/Clientes/Aguiarsoft Ricardo/IFEN/$ano/$pasta"
        TARGETFOLDER="$pasta"
        
        if [ "$nomearquivo" != "*" ]; then
            echo "Copiando para a pasta $pasta"
            cp "$nomearquivo" "/media/daniel_alves/Novo volume/Clientes/Aguiarsoft Ricardo/IFEN/$ano/$pasta/"
            # Acessa o Servidor FTP e manda o arquivo
            lftp -u $USER,$PASS $HOST << EOF
                set ftp:ssl-allow no;
                cd httpdocs/site/administrator/components/$pasta
                put "$nomearquivo"
                bye
EOF
        else
        cp -r "$diretorio_atual/." "/media/daniel_alves/Novo volume/Clientes/Aguiarsoft Ricardo/IFEN/$ano/$pasta"
        # Acessa o Servidor FTP e manda a pasta toda
            lftp -u $USER,$PASS $HOST << EOF
                set ftp:ssl-allow no;
                cd httpdocs/site/administrator/components/$pasta
                mput "$SOURCEFOLDER/*"            
EOF
        fi
    fi
fi

while true; do
    read -p 'Deseja atualizar o CEERJ? ' atcee;
    case $atcee in
        [Ss]* ) break;;
        [Nn]* ) break;;
        * ) echo "Por favor responda s ou n.";
    esac
done
if [ "$atcee" == "s" ] || [ "$acee" == "S" ]; then
    echo "Atualizando CEERJ"
    if [ -d "/media/daniel_alves/Novo volume/Clientes/Aguiarsoft Ricardo/CEERJ/$ano/$pasta" ]; then
        HOST="vps4.nunesti.com"
        USER="ceerj"
        PASS="3Caras&1Fera#"
        SOURCEFOLDER="/media/daniel_alves/Novo volume/Clientes/Aguiarsoft Ricardo/CEERJ/$ano/$pasta"
        TARGETFOLDER="$pasta"
        
        if [ "$nomearquivo" != "*" ]; then
            echo "Copiando para a pasta $pasta"
            cp "$nomearquivo" "/media/daniel_alves/Novo volume/Clientes/Aguiarsoft Ricardo/CEERJ/$ano/$pasta/"
            # Acessa o Servidor FTP e manda o arquivo
            lftp -u $USER,$PASS $HOST << EOF
                set ftp:ssl-allow no;
                cd httpdocs/portal/administrator/components/$pasta
                put "$nomearquivo"
                bye
EOF
        else
        cp -r "$diretorio_atual/." "/media/daniel_alves/Novo volume/Clientes/Aguiarsoft Ricardo/CEERJ/$ano/$pasta"
        # Acessa o Servidor FTP e manda a pasta toda
            lftp -u $USER,$PASS $HOST << EOF
                set ftp:ssl-allow no;
                cd httpdocs/portal/administrator/components/$pasta
                mput "$SOURCEFOLDER/*"            
EOF
        fi
    fi
fi

while true; do
    read -p 'Deseja atualizar o Sistema? ' atsis;
    case $atsis in
        [Ss]* ) break;;
        [Nn]* ) break;;
        * ) echo "Por favor responda s ou n.";
    esac
done
if [ "$atsis" == "s" ] || [ "$atsis" == "S" ]; then
    echo "Atualizando Sistema"$pasta
    if [[ $pasta == *"nvoice"* ]]; then
        find="com_vminvoice3/"
        replace=""
        pasta_local=${pasta/$find/$replace}
        caminho="/media/daniel_alves/Novo volume/Sistema/Vendas/VM Invoice 3/Vm Invoice3/$pasta_local"
        caminho2="/media/daniel_alves/Novo volume/Sistema/Site com sistema para instalação/Site Completo/administrator/components/$find$pasta_local"
        caminho3="/var/www/html/sistema/administrator/components/$find$pasta_local"
    fi
    if [[ $pasta == *"ompra"* ]]; then
        find="com_vmcompra3/"
        replace=""
        pasta_local=${pasta/$find/$replace}
        caminho="/media/daniel_alves/Novo volume/Sistema/Compras/VM Compra 3/Vm Compra3/$pasta_local"
        caminho2="/media/daniel_alves/Novo volume/Sistema/Site com sistema para instalação/Site Completo/administrator/components/$find$pasta_local"
        caminho3="/var/www/html/sistema/administrator/components/$find$pasta_local"
    fi
    if [[ $pasta == *"inancas"* ]]; then
        find="com_asfinancas/"
        replace=""
        pasta_local=${pasta/$find/$replace}        
        caminho="/media/daniel_alves/Novo volume/Sistema/AS Finanças/As Finanças 3/admin/$pasta_local"
        caminho2="/media/daniel_alves/Novo volume/Sistema/Site com sistema para instalação/Site Completo/administrator/components/$find$pasta_local"
        caminho3="/var/www/html/sistema/administrator/components/$find$pasta_local"
    fi
    if [[ $pasta == *"comissao"* ]]; then
        find="com_comissao/"
        replace=""
        pasta_local=${pasta/$find/$replace}
        caminho="/media/daniel_alves/Novo volume/Sistema/AS Comissao/AS Comissao3/com_comissao/admin/$pasta_local"
        caminho2="/media/daniel_alves/Novo volume/Sistema/Site com sistema para instalação/Site Completo/administrator/components/$find$pasta_local"
        caminho3="/var/www/html/sistema/administrator/components/$find$pasta_local"
    fi
    if [[ $pasta == *"relatorios"* ]]; then
        find="com_asrelatorios/"
        replace=""
        pasta_local=${pasta/$find/$replace}        
        caminho="/media/daniel_alves/Novo volume/Sistema/AS Relatorios/AS Relatorios 3/com_asrelatorios/admin/$pasta"
        caminho2="/media/daniel_alves/Novo volume/Sistema/Site com sistema para instalação/Site Completo/administrator/components/$find$pasta_local"
        caminho3="/var/www/html/sistema/administrator/components/$find$pasta_local"
    fi
    echo "caminho = "$caminho
    echo "caminho2 = "$caminho2
    echo "caminho3 = "$caminho3
    if [ -d "$caminho" ]; then
        HOST="vps4.nunesti.com"
        USER="grupointernet"
        PASS="3Caras&1Fera#"
        SOURCEFOLDER="$caminho"
        TARGETFOLDER="$pasta"
        
        if [ "$nomearquivo" != "*" ]; then
            echo "Copiando para a pasta $pasta"
            echo "$nomearquivo" "$caminho"
            cp "$nomearquivo" "$caminho"
            cp "$nomearquivo" "$caminho2"
            cp "$nomearquivo" "$caminho3"
            # Acessa o Servidor FTP e manda o arquivo
            lftp -u $USER,$PASS $HOST << EOF
                set ftp:ssl-allow no;
                cd httpdocs/sistema/administrator/components/$pasta
                put "$nomearquivo"
                bye
EOF
        else
        cp -r "$diretorio_atual/." "$caminho"
        cp -r "$diretorio_atual/." "$caminho2"
        cp -r "$diretorio_atual/." "$caminho3"
        # Acessa o Servidor FTP e manda a pasta toda
            lftp -u $USER,$PASS $HOST << EOF
                set ftp:ssl-allow no;
                cd httpdocs/sistema/administrator/components/$pasta
                mput "$SOURCEFOLDER/*"            
EOF
        fi
    fi
fi

while true; do
    read -p 'Deseja atualizar o Solucaom? ' atsol;
    case $atsol in
        [Ss]* ) break;;
        [Nn]* ) break;;
        * ) echo "Por favor responda s ou n.";
    esac
done
if [ "$atsol" == "s" ] || [ "$atsol" == "S" ]; then
    echo "Atualizando Solucaom"
    if [[ $pasta == *"nvoice"* ]]; then
        find="com_vminvoice3/"
        replace=""
        pasta_local=${pasta/$find/$replace}
        caminho="/media/daniel_alves/Novo volume/Sistema/Vendas/VM Invoice 3/Vm Invoice3/$pasta_local"
    fi
    if [[ $pasta == *"ompra"* ]]; then
        find="com_vmcompra3/"
        replace=""
        pasta_local=${pasta/$find/$replace}
        caminho="/media/daniel_alves/Novo volume/Sistema/Compras/VM Compra 3/Vm Compra3/$pasta_local"
    fi
    if [[ $pasta == *"inancas"* ]]; then
        find="com_asfinancas/"
        replace=""
        pasta_local=${pasta/$find/$replace}        
        caminho="/media/daniel_alves/Novo volume/Sistema/AS Finanças/As Finanças 3/admin/$pasta_local"
    fi
    if [[ $pasta == *"comissao"* ]]; then
        find="com_comissao/"
        replace=""
        pasta_local=${pasta/$find/$replace}
        caminho="/media/daniel_alves/Novo volume/Sistema/AS Comissao/AS Comissao3/com_comissao/admin/$pasta_local"
    fi
    if [[ $pasta == *"relatorios"* ]]; then
        find="com_asrelatorios/"
        replace=""
        pasta_local=${pasta/$find/$replace}        
        caminho="/media/daniel_alves/Novo volume/Sistema/AS Relatorios/AS Relatorios 3/com_asrelatorios/admin/$pasta"
    fi
    echo "caminho = "$caminho
    if [ -d "$caminho" ]; then
        HOST="vps4.nunesti.com"
        USER="grupointernet"
        PASS="3Caras&1Fera#"
        SOURCEFOLDER="$caminho"
        TARGETFOLDER="$pasta"
        
        if [ "$nomearquivo" != "*" ]; then
            echo "Copiando para a pasta $pasta"
            cp "$nomearquivo" "$caminho"
            # Acessa o Servidor FTP e manda o arquivo
            lftp -u $USER,$PASS $HOST << EOF
                set ftp:ssl-allow no;
                cd httpdocs/solucaom/administrator/components/$pasta
                put "$nomearquivo"
                bye
EOF
        else
        cp -r "$diretorio_atual/." "$caminho"
        # Acessa o Servidor FTP e manda a pasta toda
            lftp -u $USER,$PASS $HOST << EOF
                set ftp:ssl-allow no;
                cd httpdocs/solucaom/administrator/components/$pasta
                mput "$SOURCEFOLDER/*"            
EOF
        fi
    fi
fi

while true; do
    read -p 'Deseja atualizar o NSmart? ' atns;
    case $atns in
        [Ss]* ) break;;
        [Nn]* ) break;;
        * ) echo "Por favor responda s ou n.";
    esac
done
if [ "$atns" == "s" ] || [ "$atns" == "S" ]; then
    echo "Atualizando NSmart"
    if [[ $pasta == *"nvoice"* ]]; then
        find="com_vminvoice3/"
        replace=""
        pasta_local=${pasta/$find/$replace}
        caminho="/media/daniel_alves/Novo volume/Sistema/Vendas/VM Invoice 3/Vm Invoice3/$pasta_local"
    fi
    if [[ $pasta == *"ompra"* ]]; then
        find="com_vmcompra3/"
        replace=""
        pasta_local=${pasta/$find/$replace}
        caminho="/media/daniel_alves/Novo volume/Sistema/Compras/VM Compra 3/Vm Compra3/$pasta_local"
    fi
    if [[ $pasta == *"inancas"* ]]; then
        find="com_asfinancas/"
        replace=""
        pasta_local=${pasta/$find/$replace}        
        caminho="/media/daniel_alves/Novo volume/Sistema/AS Finanças/As Finanças 3/admin/$pasta_local"
    fi
    if [[ $pasta == *"comissao"* ]]; then
        find="com_comissao/"
        replace=""
        pasta_local=${pasta/$find/$replace}
        caminho="/media/daniel_alves/Novo volume/Sistema/AS Comissao/AS Comissao3/com_comissao/admin/$pasta_local"
    fi
    if [[ $pasta == *"relatorios"* ]]; then
        find="com_asrelatorios/"
        replace=""
        pasta_local=${pasta/$find/$replace}        
        caminho="/media/daniel_alves/Novo volume/Sistema/AS Relatorios/AS Relatorios 3/com_asrelatorios/admin/$pasta"
    fi
    echo "caminho = "$caminho
    if [ -d "$caminho" ]; then
        HOST="vps4.nunesti.com"
        USER="nsmart"
        PASS="3Caras&1Fera#"
        SOURCEFOLDER="$caminho"
        TARGETFOLDER="$pasta"
        
        if [ "$nomearquivo" != "*" ]; then
            echo "Copiando para a pasta $pasta"
            cp "$nomearquivo" "$caminho"
            # Acessa o Servidor FTP e manda o arquivo
            lftp -u $USER,$PASS $HOST << EOF
                set ftp:ssl-allow no;
                cd httpdocs/site/administrator/components/$pasta
                put "$nomearquivo"
                bye
EOF
        else
        cp -r "$diretorio_atual/." "$caminho"
        # Acessa o Servidor FTP e manda a pasta toda
            lftp -u $USER,$PASS $HOST << EOF
                set ftp:ssl-allow no;
                cd httpdocs/site/administrator/components/$pasta
                mput "$SOURCEFOLDER/*"            
EOF
        fi
    fi
fi

read -p "Arquivos replicados - Pressione enter para sair"
exit
