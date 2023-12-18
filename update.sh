#!/bin/bash

# Написано: Proxygram
# Канал: @Proxygram
# Группа: @ProxygramHub

if [ "$(id -u)" -ne 0 ]; then
    echo -e "\033[33mПожалуйста, запустите от имени root\033[0m"
    exit
fi

wait 

colorized_echo() {
    local color=$1
    local text=$2
    
    case $color in
        "red")
        printf "\e[91m${text}\e[0m\n";;
        "green")
        printf "\e[92m${text}\e[0m\n";;
        "yellow")
        printf "\e[93m${text}\e[0m\n";;
        "blue")
        printf "\e[94m${text}\e[0m\n";;
        "magenta")
        printf "\e[95m${text}\e[0m\n";;
        "cyan")
        printf "\e[96m${text}\e[0m\n";;
        *)
            echo "${text}"
        ;;
    esac
}

colorized_echo green "\n[+] - Пожалуйста, подождите несколько секунд!"

echo " "

question="Пожалуйста, выберите ваше действие?"
actions=("Обновить Бота" "Удалить Бота" "Пожертвовать" "Выйти")

select opt in "${actions[@]}"
do
    case $opt in 
        "Обновить Бота")
            echo -e "\n"
            read -p "Вы уверены, что хотите обновить? [y/n] : " answer
            if [ "$answer" != "${answer#[Yy]}" ]; then
                if [ -d "/home/grambot/web/tg.proxygram.io/public_html/Proxygram" ]; then
                    if [ -f "/home/grambot/web/tg.proxygram.io/public_html/Proxygram/install/kaspernux.install" ]; then
                        if [ -s "/home/grambot/web/tg.proxygram.io/public_html/Proxygram/install/kaspernux.install" ]; then
                            colorized_echo green "Пожалуйста, подождите, идет обновление. . ."
                            # процесс обновления!
                            sudo apt update && apt upgrade -y
                            colorized_echo green "Сервер успешно обновлен. . .\n"
                            sudo apt install curl -y
                            sudo apt install jq -y
                            sleep 2
                            mv /home/grambot/web/tg.proxygram.io/public_html/Proxygram/install/kaspernux.install /home/grambot/web/tg.proxygram.io/public_html/kaspernux.install
                            sleep 1
                            rm -r /home/grambot/web/tg.proxygram.io/public_html/Proxygram/
                            colorized_echo green "\nВсе файлы и папки удалены для обновления бота. . .\n"

                            git clone https://github.com/kaspernux/PROXYRU.git /home/grambot/web/tg.proxygram.io/public_html/Proxygram/
                            sudo chmod -R 777 /home/grambot/web/tg.proxygram.io/public_html/Proxygram/
                            mv /var/www/html/kaspernux.install /home/grambot/web/tg.proxygram.io/public_html/Proxygram/install/kaspernux.install
                            sleep 2
                            
                            content=$(cat /home/grambot/web/tg.proxygram.io/public_html/Proxygram/install/kaspernux.install)
                            token=$(echo "$content" | jq -r '.token')
                            dev=$(echo "$content" | jq -r '.dev')
                            domain=$(echo "$content" | jq -r '.main_domin')
                            db_name=$(echo "$content" | jq -r '.db_name')
                            db_username=$(echo "$content" | jq -r '.db_username')
                            db_password=$(echo "$content" | jq -r '.db_password')

                            source_file="/home/grambot/web/tg.proxygram.io/public_html/Proxygram/config.php"
                            destination_file="/home/grambot/web/tg.proxygram.io/public_html/Proxygram/config.php.tmp"
                            replace=$(cat "$source_file" | sed -e "s/\[\*TOKEN\*\]/${token}/g" -e "s/\[\*DEV\*\]/${dev}/g" -e "s/\[\*DB-NAME\*\]/${db_name}/g" -e "s/\[\*DB-USER\*\]/${db_username}/g" -e "s/\[\*DB-PASS\*\]/${db_password}/g")
                            echo "$replace" > "$destination_file"
                            mv "$destination_file" "$source_file"

                            sleep 2
                            
                            curl --location "https://${domain}/public_html/Proxygram/sql/sql.php?db_password=${db_password}&db_name=${db_name}&db_username=${db_username}"
                            echo -e "\n"
                            TEXT_MESSAGE="✅ Ваш бот успешно обновлен до последней версии."$'\n\n'"#️⃣ Информация о боте :"$'\n\n'"▫️токен: <code>${token}</code>"$'\n'"▫️администратор: <code>${dev}</code> "$'\n'"▫️домен: <code>${domain}</code>"$'\n'"▫️db_name: <code>${db_name}</code>"$'\n'"▫️db_username: <code>${db_username}</code>"$'\n'"▫️db_password: <code>${db_password}</code>"$'\n\n'"🔎 - @Proxygram | @ProxygramHUB"
                            curl -s -X POST "https://api.telegram.org/bot${token}/sendMessage" -d chat_id="${dev}" -d text="${TEXT_MESSAGE}" -d parse_mode="html"

                            sleep 2
                            clear
                            echo -e "\n\n"
                            colorized_echo green "[+] Бот Proxygram успешно обновлен"
                            colorized_echo green "[+] Канал в Telegram: @Proxygram || Группа в Telegram: @ProxygramHUB\n\n"
                            colorized_echo green "Информация о вашем боте:\n"
                            colorized_echo blue "[+] токен: ${token}"
                            colorized_echo blue "[+] администратор: ${dev}"
                            colorized_echo blue "[+] домен: ${domain}"
                            colorized_echo blue "[+] db_name: ${db_name}"
                            colorized_echo blue "[+] db_username: ${db_username}"
                            colorized_echo blue "[+] db_password: ${db_password}"
                            echo -e "\n"
                        else
                            echo -e "\n"
                            colorized_echo red "Файл kaspernux.install пуст!"
                            echo -e "\n"
                            exit 1
                        fi
                    else
                        echo -e "\n"
                        colorized_echo red "Файл kaspernux.install не найден, процесс обновления отменен!"
                        echo -e "\n"
                        exit 1
                    fi
                else
                    echo -e "\n"
                    colorized_echo red "Папка PROXYGRAM для процесса обновления не найдена, сначала установите бота!"
                    echo -e "\n"
                    exit 1
                fi
            else
                echo -e "\n"
                colorized_echo red "Обновление отменено!"
                echo -e "\n"
                exit 1
            fi

            break;;
        "Удалить Бота")
            echo -e "\n"
            read -p "Вы уверены, что хотите удалить? [y/n] : " answer
            if [ "$answer" != "${answer#[Yy]}" ]; then
                if [ -d "/home/grambot/web/tg.proxygram.io/public_html/Proxygram" ]; then
                    colorized_echo green "\n[+] Пожалуйста, подождите, удаление. . .\n"
                    rm -r /home/grambot/web/tg.proxygram.io/public_html/Proxygram/

                    sleep 2

                    TEXT_MESSAGE="❌ Бот Proxygram успешно удален -> @Proxygram | @ProxygramHUB"
                    curl -s -X POST "https://api.telegram.org/bot${TOKEN}/sendMessage" -d chat_id="${CHAT_ID}" -d text="${TEXT_MESSAGE}"

                    sleep 2
                    echo -e "\n"
                    colorized_echo green "[+] Бот Proxygram успешно удален"
                    colorized_echo green "[+] Канал в Telegram: @Proxygram || Группа в Telegram: @ProxygramHUB"
                    echo -e "\n"
                else
                    echo -e "\n"
                    colorized_echo red "Папка Proxygram для процесса обновления не найдена, сначала установите бота!"
                    echo -e "\n"
                    exit 1
                fi
            else
                echo -e "\n"
                colorized_echo red "Удаление отменено!"
                echo -e "\n"
                exit 1
            fi

            break;;
        "Пожертвовать")
            echo -e "\n"
            colorized_echo green "[+] Bank Meli: 6037998195739130\n\n[+] Tron (TRX): TAwNcAYrHp2SxhchywA6NhUEF4aVwJHufD\n\n[+] ETH, BNB, MATIC network (ERC20, BEP20): 0x36c5109885b59Ddd0cA4ea497765d326eb48396F\n\n[+] Bitcoin network: bc1qccunjllf2guca7dhwyw2x3u80yh4k0hg88yvpk" 
            echo -e "\n"
            exit 0

            break;;
        "Выйти")
            echo -e "\n"
            colorized_echo green "Вышел!"
            echo -e "\n"

            break;;
            *) echo "Неверный вариант!"
    esac
done
