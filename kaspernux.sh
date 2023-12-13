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
echo 'phpmyadmin phpmyadmin/app-password-confirm password Proxy007' | debconf-set-selections
echo 'phpmyadmin phpmyadmin/reconfigure-webserver multiselect apache2' | debconf-set-selections
echo 'phpmyadmin phpmyadmin/mysql/admin-pass password Proxy007' | debconf-set-selections
echo 'phpmyadmin phpmyadmin/mysql/app-pass password Proxy007' | debconf-set-selections
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

colorized_echo green "Установка PROXYRU . . ."

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

git clone https://github.com/kaspernux/PROXYRU.git /var/www/html/PROXYRUBot
sudo chmod -R 777 /var/www/html/PROXURUBot/
colorized_echo green "\n\tВсе файлы/папки робота PROXYRU успешно установлены на вашем сервере!"

wait

clear
echo -e " \n"

read -p "[+] Введите домен без [http:// | https://]: " domain
if [ "$domain" = "" ]; then
    colorized_echo green "Хорошо, продолжаем. . ."
    colorized_echo green "Пожалуйста, подождите !"
    sleep 2
else
    DOMAIN="$domain"
fi

sudo ufw allow 80
sudo ufw allow 443 
sudo apt install letsencrypt -y
sudo apt-get -y install certbot python3-certbot-apache
sudo systemctl enable certbot.timer
sudo certbot certonly --standalone --agree-tos --preferred-challenges http -d $DOMAIN
sudo certbot --apache --agree-tos --preferred-challenges http -d $DOMAIN

wait
clear
echo -e " \n"

wait

read -p "[+] Введите пароль пользователя root (MySql): " ROOT_PASSWORD
randdbpass=$(openssl rand -base64 8 | tr -dc 'a-zA-Z0-9' | head -c 10)
randdbdb=$(pwgen -A 8 1)
randdbname=$(openssl rand -base64 8 | tr -dc 'a-zA-Z0-9' | head -c 4)
dbname="Proxy007_${randdbpass}"

colorized_echo green "Пожалуйста, введите имя пользователя базы данных (По умолчанию -> Ввод):"
printf "[+] Имя пользователя по умолчанию [${randdbdb}] :"
read dbuser
if [ "$dbuser" = "" ]; then
    dbuser=$randdbdb
else
    dbuser=$dbuser
fi

colorized_echo green "Пожалуйста, введите пароль базы данных (По умолчанию -> Ввод):"
printf "[+] Пароль по умолчанию [${randdbpass}] :"
read dbpass
if [ "$dbpass" = "" ]; then
    dbpass=$randdbpass
else
    dbpass=$dbpass
fi

sshpass -p $ROOT_PASSWORD mysql -u root -p -e "SET GLOBAL validate_password.policy = LOW;"
sshpass -p $ROOT_PASSWORD mysql -u root -p -e "CREATE DATABASE $dbname;" -e "CREATE USER '$dbuser'@'%' IDENTIFIED WITH mysql_native_password BY '$dbpass';GRANT ALL PRIVILEGES ON * . * TO '$dbuser'@'%';FLUSH PRIVILEGES;" -e "CREATE USER '$dbuser'@'localhost' IDENTIFIED WITH mysql_native_password BY '$dbpass';GRANT ALL PRIVILEGES ON * . * TO '$dbuser'@'localhost';FLUSH PRIVILEGES;"
sshpass -p $ROOT_PASSWORD mysql -u root -p -e "GRANT ALL PRIVILEGES ON *.* TO 'phpmyadmin'@'localhost' WITH GRANT OPTION;"

colorized_echo green "[+] База данных робота успешно создана!"

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

config_address="/var/www/html/Proxy007Bot/install/kaspernux.install"

if [ -f "$config_address" ]; then
    rm "$config_address"
fi

clear
echo -e "\n"
colorized_echo green "[+] Пожалуйста, подождите . . .\n"
sleep 1

# добавить информацию в файл
# touch('/var/www/html/Proxy007Bot/install/kaspernux.install')
echo "{\"development\":\"@Proxy007\",\"install_location\":\"server\",\"main_domin\":\"${DOMAIN}\",\"token\":\"${TOKEN}\",\"dev\":\"${CHAT_ID}\",\"db_name\":\"${dbname}\",\"db_username\":\"${randdbdb}\",\"db_password\":\"${randdbpass}\"}" > /var/www/html/Proxy007Bot/install/kaspernux.install

source_file="/var/www/html/Proxy007Bot/config.php"
destination_file="/var/www/html/Proxy007Bot/config.php.tmp"
replace=$(cat "$source_file" | sed -e "s/\[\*TOKEN\*\]/${TOKEN}/g" -e "s/\[\*DEV\*\]/${CHAT_ID}/g" -e "s/\[\*DB-NAME\*\]/${dbname}/g" -e "s/\[\*DB-USER\*\]/${dbuser}/g" -e "s/\[\*DB-PASS\*\]/${dbpass}/g")
echo "$replace" > "$destination_file"
mv "$destination_file" "$source_file"

sleep 2

# процесс curl
colorized_echo blue "Статус базы данных:"
curl --location "https://${DOMAIN}/Proxy007Bot/sql/sql.php?db_password=${dbpass}&db_name=${dbname}&db_username=${dbuser}"

colorized_echo blue "\n\nСтатус Webhook:"
curl -F "url=https://${DOMAIN}/Proxy007Bot/index.php" "https://api.telegram.org/bot${TOKEN}/setWebhook"

colorized_echo blue "\n\nСтатус отправки сообщения:"
TEXT_MESSAGE="✅ Робот Proxy007 успешно установлен!"
curl -s -X POST "https://api.telegram.org/bot${TOKEN}/sendMessage" -d chat_id="${CHAT_ID}" -d text="${TEXT_MESSAGE}"
echo -e "\n\n"

sleep 1
colorized_echo green "[+] Робот Proxy007 успешно установлен"
colorized_echo green "[+] Канал в Telegram: @Proxy007 || Группа в Telegram: @Proxy007Gap"
echo -e "\n"
