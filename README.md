## Инструкция по настройке и установке робота на хостинг (Cпинел)

- Войдите в бота-отца [@BotFather](https://t.me/BotFather) и отправьте команду `/new_bot`, чтобы создать нового робота и получить токен.

- Войдите в свой хостинг и перейдите в раздел [ `MySQL® Database Wizard` ], создайте новую базу данных и активируйте все необходимые разрешения.

- Перейдите в папку [ `public_html` ] и создайте в этой папке новую папку (по вашему усмотрению).

- Загрузите все файлы и папки робота Proxy007 Panel точно и правильно в папку, которую вы создали.

- Запустите файл `install.html`. Пример адреса для запуска: (где в этом примере `Domain.com` соответствует вашему домену, а `folder` - названию папки, которую вы создали)
```bash
https://Domain.com/folder/install/install.html
```

- Введите все запрошенные входные данные на странице точно и корректно, а затем нажмите кнопку **Зарегистрировать и установить робота**.

- Вы можете получить свой числовой идентификатор через бота [@UserInfoBot](https://t.me/userinfobot).

- После заполнения всех необходимых полей и нажатия кнопки **Зарегистрировать и установить робота** вам будет отправлено успешное сообщение в роботе.

- Вы можете войти в панель управления роботом с помощью команды `/panel` или `panel`.

## Инструкция по настройке и установке робота на сервере

- Войдите на свой сервер.

- Отправьте следующую команду для установки робота.
```bash
bash <(curl -s https://raw.githubusercontent.com/kaspernux/PROXYRU/main/kaspernux.sh)
```

- После выполнения этой команды подождите некоторое время, чтобы перейти к этапу ввода домена.

- Введите свой домен без `http` и `https` (обязательно активирован `SSL` для домена).

- Подождите после ввода домена, чтобы перейти к этапу создания базы данных.

- Когда вы достигнете этапа базы данных, вам будет предложено ввести данные базы данных. Если вы хотите создать все по умолчанию, нажмите `enter` в обоих полях для этапа базы данных.

- После завершения этой части ваша база данных будет создана, и на следующем этапе у вас будут запрошены три информации (токен | chat_id | domain), которые вам нужно ввести.

- После ввода этой информации робот Proxy007 будет установлен на вашем сервере.

- После установки вам будет отправлено сообщение о успешной установке в роботе.

- Войдите в робота и запустите команду `/start`.

## Инструкция по обновлению робота

- Чтобы обновить робот до последней версии, просто выполните следующую команду:
```bash
bash <(curl -s https://raw.githubusercontent.com/kaspernux/PROXYRU/main/update.sh)
```

- После выполнения этой команды для обновления отправьте команду `1`, а затем `y`.

## Часто задаваемые вопросы

- **Обязательное участие**: Обязательное участие робота устанавливается через панель управления роботом и не имеет ограничений, это означает, что вы можете зарегистрировать до 5 обязательных участников.

- **Невозможность регистрации панели/сервера в роботе**: Есть много причин, по которым робот не может войти в вашу панель, например, отсутствие полного доступа к `CURL` или ошибки в тексте описания.

- **Ошибка настройки веб-хука при установке**: Эта ошибка возникает, когда отправленный вами токен неверный или ваш хостинг не может отправить запрос на сайт Telegram для установки веб-хука, поскольку причиной неудачного запроса является закрытый доступ к `curl` или ваш хостинг находится в Иране.

- **Ошибка установки из-за неправильного токена**: Как видно из текста ошибки, отправленный вами токен для установки неверен.

- **Ошибка в данных базы данных**: Эта ошибка возникает, когда отправленные вами данные базы данных неверны (`name`, `username`, `password`).

## Необходимые разрешения для установки на хостинге

- Доступ к `CURL`
- Доступ к `curl_exec`
- Открытые порты, например `8000`
- Необходимые разрешения для создания/удаления/редактирования файлов
- Версия хостинга `7.4` (в скором времени будет работать на всех версиях)

## Важные моменты

- Ни в коем случае не вносите специальные изменения в исходный код. Если у вас есть специальные предпочтения, вы можете оставить свои комментарии в [телеграмм-группе](

https://t.me/Proxy007) Proxy007.

- Обязательно создайте нового робота для установки, не используйте предыдущие токены.

- Введите данные базы данных правильно.

- Если вы запускаете робот на хостинге, убедитесь, что у домена активирован `SSL`.

- При запуске на хостинге он должен быть иностранным.

- При запуске на сервере вам нужно знать пароль `root`.

## **Поддерживаемые панели в роботе**

- Marzban
```bash
sudo bash -c "$(curl -sL https://github.com/Gozargah/Marzban-scripts/raw/master/marzban.sh)" @ install
```
- Sanaei
```bash
bash <(curl -Ls https://raw.githubusercontent.com/mhsanaei/3x-ui/master/install.sh)
```

## Инструкция по созданию робота в BotFather (шаг за шагом)

- Войдите в бота [@BotFather](https://t.me/BotFather) и запустите робот `/start`.

- Отправьте команду `/newbot`, чтобы создать нового робота.

- Отправьте имя вашего робота.

- Отправьте имя пользователя вашего робота (последнее имя пользователя должно быть `bot`).

- Если все данные, отправленные правильно, ваш робот будет создан.

- Пример токена робота:
```bash
66332901756:AAFsGVqaydeIeQJsqCcVbhSJ9fiyBLtR9VU0s
```

## Функции робота PROXURU

🤖 PROXYRU: Лучший российский робот для покупки и продажи прокси-сервисов через удобную панель управления. Обработка множества серверов для Shadowsocks и других протоколов прокси. Совершенное сочетание удобства, многофункциональности и безопасности! Поднимите свои прокси-возможности на новый уровень с PROXYRU! 🛡️💻🔒

🌐 Встроенный веб-интерфейс
🔌 Полностью функциональный бэкэнд через REST API
🖥️ Поддержка нескольких узлов для распределения инфраструктуры и масштабируемости
🔒 Поддержка протоколов Vmess, VLESS, Trojan и Shadowsocks
🔄 Мультипротокольность для одного пользователя
👥 Мультипользовательский режим на одном входящем соединении
🔗 Мульти-входящие соединения на одном порту (поддержка резервных вариантов)
📶 Ограничения по трафику и дате окончания
🔄 Периодические ограничения по трафику (например, ежедневные, еженедельные и т.д.)
🔗 Совместимость с подписочными ссылками V2ray (такими как V2RayNG, OneClick, Nekoray и др.), Clash и ClashMeta
🌐 Автоматический генератор ссылок и QR-кодов для обмена
📊 Мониторинг системы и статистика трафика
🔧 Настройка конфигурации xray
🔐 Поддержка TLS и REALITY
🤖 Интегрированный Telegram Bot
💻 Интегрированный интерфейс командной строки (CLI)
🎨 Стильный и продуманный дизайн 
🖥️ Мульти-панель 
📊 Мульти-план 
➕ Добавление панели 
➕ Добавление плана с пользовательскими полями 
🔌 Умное подключение 
📈 Полное состояние сервера/панели 
📄 Полная информация о пользователе
🚫 Блокировка пользователя 
🔓 Разблокировка пользователя 
✉️ Отправка сообщения пользователю 
📨 Массовая рассылка 
📩 Массовая пересылка 
🔘 Безграничное обязательное участие 
💳 Платежные шлюзы RubPay - Платежные системы России (Банки, QIWI, Юмани) 
💱 Платежный шлюз NOWPayments - Крипто валюты 
⚙️ Настройка текстов робота 
📊 Моментальный отчет о роботе 
📈 Полное управление статистикой робота 
💵 Полное управление разделом платежных шлюзов 
📚 Полное управление разделом руководства по подключению 
🛑 Управление антиспамом робота 
🧪 Управление тестовым аккаунтом 
🔗 Умная ссылка 
🏷️ Создание QR-кода для услуги 
🔄 Полное управление протоколами 
📊 Полное управление панелью/сервером 
➡️ Полное управление добавленными планами (удаление/изменение имени/изменение объема и т.д.) 📈
➕ Добавление администраторов без ограничений 👤
❌ Удаление администраторов 👤
👀 Просмотр списка администраторов 👤
📩 Полное уведомление о покупке услуги и т.д. 🛒
💬 Онлайн поддержка в роботе 🤖


## Поддержка

Для поддержки нашего проекта, Вы сможете посетить наш обменник OBMEN.CENTER и 
Obmen.center - сервис для покупки/продажи и обмена криптовалюты в фиат и наоборот
Обменять валюту вы можете на нашем сайте obmen.center или в телеграмме @Obmencenter_bot

## Канал и группа Proxy007 Panel

**Обязательно присоединяйтесь к каналу и группе Kaspernux Panel.**
- Канал: [@Vpn007](https://t.me/Proxy007)
- Группа: [@Proxy007Gap](https://t.me/Proxy007Gap)
- Бот: [@Proxy007Bot](https://t.me/Proxy007Bot)