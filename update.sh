#!/bin/bash

# Написано: Proxy007
# Канал: @Proxy007
# Группа: @Proxy007Gap

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
                if [ -d "/var/www/html/Proxy007Bot" ]; then
                    if [ -f "/var/www/html/Proxy007Bot/install/kaspernux.install" ]; then
                        if [ -s "/var/www/html/Proxy007Bot/install/kaspernux.install" ]; then
                            colorized_echo green "Пожалуйста, подождите, идет обновление. . ."
                            # процесс обновления!
                            sudo apt update && apt upgrade -y
                            colorized_echo green "Сервер успешно обновлен. . .\n"
                            sudo apt install curl -y
                            sudo apt install jq -y
                            sleep 2
                            mv /var/www/html/Proxy007Bot/install/kaspernux.install /var/www/html/kaspernux.install
                            sleep 1
                            rm -r /var/www/html/Proxy007Bot/
                            colorized_echo green "\nВсе файлы и папки удалены для обновления бота. . .\n"

                            git clone https://github.com/kaspernux/PROXYRU.git /var/www/html/Proxy007Bot/
                            sudo chmod -R 777 /var/www/html/Proxy007Bot/
                            mv /var/www/html/kaspernux.install /var/www/html/Proxy007Bot/install/kaspernux.install
                            sleep 2
                            
                            content=$(cat /var/www/html/Proxy007Bot/install/kaspernux.install)
                            token=$(echo "$content" | jq -r '.token')
                            dev=$(echo "$content" | jq -r '.dev')
                            domain=$(echo "$content" | jq -r '.main_domin')
                            db_name=$(echo "$content" | jq -r '.db_name')
                            db_username=$(echo "$content" | jq -r '.db_username')
                            db_password=$(echo "$content" | jq -r '.db_password')

                            source_file="/var/www/html/Proxy007Bot/config.php"
                            destination_file="/var/www/html/Proxy007Bot/config.php.tmp"
                            replace=$(cat "$source_file" | sed -e "s/\[\*TOKEN\*\]/${token}/g" -e "s/\[\*DEV\*\]/${dev}/g" -e "s/\[\*DB-NAME\*\]/${db_name}/g" -e "s/\[\*DB-USER\*\]/${db_username}/g" -e "s/\[\*DB-PASS\*\]/${db_password}/g")
                            echo "$replace" > "$destination_file"
                            mv "$destination_file" "$source_file"

                            sleep 2
                            
                            curl --location "https://${domain}/Proxy007Bot/sql/sql.php?db_password=${db_password}&db_name=${db_name}&db_username=${db_username}"
                            echo -e "\n"
                            TEXT_MESSAGE="✅ Ваш бот успешно обновлен до последней версии."$'\n\n'"#️⃣ Информация о боте :"$'\n\n'"▫️токен: <code>${token}</code>"$'\n'"▫️администратор: <code>${dev}</code> "$'\n'"▫️домен: <code>${domain}</code>"$'\n'"▫️db_name: <code>${db_name}</code>"$'\n'"▫️db_username: <code>${db_username}</code>"$'\n'"▫️db_password: <code>${db_password}</code>"$'\n\n'"🔎 - @Proxy007 | @Proxy007Gap"
                            curl -s -X POST "https://api.telegram.org/bot${token}/sendMessage" -d chat_id="${dev}" -d text="${TEXT_MESSAGE}" -d parse_mode="html"

                            sleep 2
                            clear
                            echo -e "\n\n"
                            colorized_echo green "[+] Бот Proxy007 успешно обновлен"
                            colorized_echo green "[+] Канал в Telegram: @Proxy007 || Группа в Telegram: @Proxy007Gap\n\n"
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
                    colorized_echo red "Папка Proxy007Bot для процесса обновления не найдена, сначала установите бота!"
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
                if [ -d "/var/www/html/Proxy007Bot" ]; then
                    colorized_echo green "\n[+] Пожалуйста, подождите, удаление. . .\n"
                    rm -r /var/www/html/Proxy007Bot/

                    sleep 2

                    TEXT_MESSAGE="❌ Бот Proxy007 успешно удален -> @Proxy007 | @Proxy007Gap"
                    curl -s -X POST "https://api.telegram.org/bot${TOKEN}/sendMessage" -d chat_id="${CHAT_ID}" -d text="${TEXT_MESSAGE}"

                    sleep 2
                    echo -e "\n"
                    colorized_echo green "[+] Бот Proxy007 успешно удален"
                    colorized_echo green "[+] Канал в Telegram: @Proxy007 || Группа в Telegram: @Proxy007Gap"
                    echo -e "\n"
                else
                    echo -e "\n"
                    colorized_echo red "Папка Proxy007Bot для процесса обновления не найдена, сначала установите бота!"
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
