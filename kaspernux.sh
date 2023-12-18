#!/bin/bash

# Написано: PROXYGRAM
# Канал: @ProxygramHUB
# Бот: @ProxygramCA_bot

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

colorized_echo green "\n[+] - Подождите несколько часов, устанавливается робот панели пчёл. . ."

# процесс обновления !
sudo apt update && apt upgrade -y
colorized_echo green "Сервер успешно обновлен . . .\n"

# установка пакетов !
PACKAGES=(
    mysql-server 
    libapache2-mod-php 
    lamp-server^ 
    php-mbstring 
    apache2 
    php-zip 
    php-gd 
    php-json 
    php-curl 
)

colorized_echo green " Установка необходимых пакетов. . ."

for i in "${PACKAGES[@]}"
    do
        dpkg -s $i &> /dev/null
        if [ $? -eq 0 ]; then
            colorized_echo yellow "Пакет $i в настоящее время установлен на вашем сервере!"
        else
            apt install $i -y
            if [ $? -ne 0 ]; then
                colorized_echo red "Пакет $i не удалось установить."
                exit 1
            fi
        fi
    done

# установка еще !
echo 'phpmyadmin phpmyadmin/app-password-confirm password Proxygram' | debconf-set-selections
echo 'phpmyadmin phpmyadmin/reconfigure-webserver multiselect apache2' | debconf-set-selections
echo 'phpmyadmin phpmyadmin/mysql/admin-pass password Proxygram' | debconf-set-selections
echo 'phpmyadmin phpmyadmin/mysql/app-pass password Proxygram' | debconf-set-selections
echo 'phpmyadmin phpmyadmin/dbconfig-install boolean true' | debconf-set-selections
sudo apt-get install phpmyadmin -y
sudo ln -s /etc/phpmyadmin/apache.conf /etc/apache2/conf-available/phpmyadmin.conf
sudo a2enconf phpmyadmin.conf
sudo systemctl restart apache2

wait

sudo apt-get install -y php-soap
sudo apt-get install libapache2-mod-php

# обработка служб !
sudo systemctl enable mysql.service
sudo systemctl start mysql.service
sudo systemctl enable apache2
sudo systemctl start apache2

ufw allow 'Apache Full'
sudo systemctl restart apache2

colorized_echo green "Установка PROXYGRAM . . ."

sleep 2

sudo apt install sshpass
sudo apt-get install pwgen
sudo apt-get install -y git
sudo apt-get install -y wget
sudo apt-get install -y unzip
sudo apt install curl -y
sudo apt-get install -y php-ssh2
sudo apt-get install -y libssh2-1-dev libssh2-1

sudo systemctl restart apache2.service

wait

git clone https://github.com/kaspernux/PROXYRU.git /home/grambot/web/tg.proxygram.io/public_html/Proxygram
sudo chmod -R 777 /home/grambot/web/tg.proxygram.io/public_html/Proxygram
colorized_echo green "\n\tВсе файлы/папки робота PROXYGRAM успешно установлены на вашем сервере!"

wait

clear
echo -e " \n"

echo -e "\n[+] Настройка SSL/TLS для вашего домена\n"

# Запрос пользователя для указания домена
read -p "[+] Введите домен без [http:// | https://]: " domain

# Проверка наличия указанного домена
if [ -z "$domain" ]; then
    echo -e "[!] Ошибка: Домен не указан. Выход."
    exit 1
else
    DOMAIN="$domain"
fi

# Информирование пользователя и ожидание
echo -e "\n[+] Хорошо, продолжаем..."
echo -e "[+] Пожалуйста, подождите!"
sleep 2

# Разрешение портов 80 и 443 через UFW
sudo ufw allow 80
sudo ufw allow 443 

# Установка необходимых пакетов
sudo apt-get update
sudo apt-get -y install letsencrypt
sudo apt-get -y install certbot python3-certbot-apache

# Включение таймера certbot для автоматического обновления
sudo systemctl enable certbot.timer

# Получение SSL/TLS-сертификата с использованием certbot
sudo certbot certonly --standalone --agree-tos --preferred-challenges http -d $DOMAIN

# Настройка Apache с использованием полученного сертификата
sudo certbot --apache --agree-tos --preferred-challenges http -d $DOMAIN

# Очистка экрана для чистого вывода
clear
echo -e " \n"

wait

read -p "[+] Введите [root (MySql)] пароль пользователя: " ROOT_PASSWORD

# Генерация безопасных случайных значений
randdbpass=$(openssl rand -base64 8 | tr -dc 'a-zA-Z0-9' | head -c 10)
randdbdb=$(pwgen -A 8 1)
randdbname=$(openssl rand -base64 8 | tr -dc 'a-zA-Z0-9' | head -c 4)
dbname="GramBot_${randdbpass}"

colorized_echo green "[+] Введите имя пользователя базы данных MySQL  (По умолчанию -> Enter) :"
printf "[+] Имя пользователя по умолчанию [${randdbdb}] :"
read dbuser
if [ "$dbuser" = "" ]; then
    dbuser=$randdbdb
else
    dbuser=$dbuser
fi

colorized_echo green "[+] Введите пароль для пользователя базы данных MySQL (по умолчанию: -> Enter) :"
printf "[+] Пароль по умолчанию [${randdbpass}] :"
read dbpass
if [ "$dbpass" = "" ]; then
    dbpass=$randdbpass
else
    dbpass=$dbpass
fi

# Создание базы данных и пользователя MySQL
sudo mysql -u root -p"$ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS $randdbdb;"
sudo mysql -u root -p"$ROOT_PASSWORD" -e "CREATE USER IF NOT EXISTS '$dbuser'@'%' IDENTIFIED BY '$dbpass';"
sudo mysql -u root -p"$ROOT_PASSWORD" -e "GRANT ALL PRIVILEGES ON $randdbdb.* TO '$dbuser'@'%';"
sudo mysql -u root -p"$ROOT_PASSWORD" -e "FLUSH PRIVILEGES;"

# Уведомление пользователя об успешном создании базы данных
colorized_echo green "\n[+] База данных MySQL '$randdbdb', пользователь '$dbuser' с паролем '$dbpass' успешно созданы для вашего бота!"
colorized_echo green "\n[+] ВАЖНО !!! Зампомните эти данные для настройки Бота !"


wait

# получить информацию о боте и пользователе !
printf "\n\e[33m[+] \e[36mТокен бота: \033[0m"
read TOKEN
printf "\e[33m[+] \e[36mChat id: \033[0m"
read CHAT_ID
printf "\e[33m[+] \e[36mВведите домен без [https:// | http://]: \033[0m"
read DOMAIN
echo " "

if [ 'http' in "$DOMAIN" ]; then
    colorized_echo red "Неверный ввод!"
    exit 1
fi

if [ "$TOKEN" = "" ] || [ "$DOMAIN" = "" ] || [ "$CHAT_ID" = "" ]; then
    colorized_echo red "Неверный ввод!"
    exit 1
fi

wait
sleep 2

config_address="/home/grambot/web/tg.proxygram.io/public_html/Proxygram/install/kaspernux.install"

if [ -f "$config_address" ]; then
    rm "$config_address"
fi

clear
echo -e "\n"
colorized_echo green "[+] Пожалуйста, подождите . . .\n"
sleep 1

# добавить информацию в файл
# touch('/home/grambot/web/tg.proxygram.io/public_html/Proxygram/install/kaspernux.install')
echo "{\"development\":\"@Proxygram\",\"install_location\":\"server\",\"main_domin\":\"${DOMAIN}\",\"token\":\"${TOKEN}\",\"dev\":\"${CHAT_ID}\",\"db_name\":\"${dbname}\",\"db_username\":\"${randdbdb}\",\"db_password\":\"${randdbpass}\"}" > /home/grambot/web/tg.proxygram.io/public_html/Proxygram/install/kaspernux.install

source_file="/home/grambot/web/tg.proxygram.io/public_html/Proxygram/config.php"
destination_file="/home/grambot/web/tg.proxygram.io/public_html/Proxygram/config.php.tmp"
replace=$(cat "$source_file" | sed -e "s/\[\*TOKEN\*\]/${TOKEN}/g" -e "s/\[\*DEV\*\]/${CHAT_ID}/g" -e "s/\[\*DB-NAME\*\]/${dbname}/g" -e "s/\[\*DB-USER\*\]/${dbuser}/g" -e "s/\[\*DB-PASS\*\]/${dbpass}/g")
echo "$replace" > "$destination_file"
mv "$destination_file" "$source_file"

sleep 2

# процесс curl
colorized_echo blue "Статус базы данных:"
curl --location "https://${DOMAIN}/public_html/Proxygram/sql/sql.php?db_password=${dbpass}&db_name=${dbname}&db_username=${dbuser}"

colorized_echo blue "\n\nСтатус Webhook:"
curl -F "url=https://${DOMAIN}/public_html/Proxygram/index.php" "https://api.telegram.org/bot${TOKEN}/setWebhook"

colorized_echo blue "\n\nСтатус отправки сообщения:"
TEXT_MESSAGE="✅ Робот PROXYGRAM успешно установлен!"
curl -s -X POST "https://api.telegram.org/bot${TOKEN}/sendMessage" -d chat_id="${CHAT_ID}" -d text="${TEXT_MESSAGE}"
echo -e "\n\n"

sleep 1
colorized_echo green "[+] Робот PROXYGRAM успешно установлен"
colorized_echo green "[+] Канал в Telegram: @ProxygramHUB || Бот в Telegram: @ProxygramCA_bot"
echo -e "\n"