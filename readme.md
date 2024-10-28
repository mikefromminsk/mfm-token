MFM Token - это проект, предоставляющий набор инструментов и утилит для управления токенами в веб-приложении.

## Getting Started
Убедитесь, что на вашем компьютере установлено следующее программное обеспечение:

- Node.js (v14.x или новее)
- npm (v6.x или новее)

Для установки проекта выполните следующую команду:
```sh
npm i mfm-token
```
Далее нужно пройти по ссылке и создать базовый токен:
```sh
curl -X GET http://localhost/mfm-token/init.php
```

## User Guide

### Генерация пароля
"Пароль" - это комбинация ключа для расшифровки последнего хеша пароля и хеша нового пароля, разделенных двоеточием. Для нового токена это будет просто хеш нового пароля.
Pass это объединение ключа для расшифровки последнего хеша пароля и хеша нового пароля разделенным двоеточием.
для нового токена это будет просто хеш нового пароля.
md5(md5(password)) = 5f4dcc3b5aa765d61d8327deb882cf99
для последующих транзакций пароль будет рассчитываться так:
md5(password):md5(password) + prev_hash
для исключения повторения хешей у разных пользователей пароль состоит из нескольких частей.
1. domain - домен пользователя
2. address - адрес пользователя
3. password - пароль пользователя
4. prev_hash - предыдущий хеш ключа

использование md5 хеша является уязвимостью, переход на sha256 планируется в будущем.

1. create token:
[https://localhost/mfm-token?domain=catcoin&address=admin](https://localhost/mfm-token?domain=catcoin&address=admin):
    аддрес аккаунта который совершил первую транзакцию будет считаться владельцем токена.
2. получение профиля токена
3. получение баланса токена
[https://localhost/mfm-token?domain=catcoin&address=admin](https://localhost/mfm-token?domain=catcoin&address=admin):
4. create account
5. send token to account
6. delegate account
7. получение списка транзакций

## Development contracts
Контракты нужно разрабатывать на языке php.
1. необходимо унаследовать скрипт /mfm-token/utils.php
2. Получение параметров с помощью утилиты /mfm-data/params.php
    - подробное описание параметров можно найти в документации [mfm-db](https://github.com/mikefromminsk/mfm-db)
3. проверка параметров на допустимые значения
    - в любой момент контракта можно вызвать функцию error() для завершения выполнения контракта с ошибкой
4. отправка токенов на адрес
    - для отправки токенов на адрес необходимо вызвать функцию sendToken() - необходимо знать что amount может быть только не более чем с 2 знаками после замятой.
5. делегирование аккаунта
    - для делегирования аккаунта необходимо вызвать функцию delegateAccount()
6. коммит изменений
    - сохранение всех данных в базе данных должно происходить только после удачного выполнения контракта. Поэтому все данные которые должны быть сохранены в базу данных должны находиться в оперативной памяти до конца выполнения контракта.

Пример контракта:
```php
<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/mfm-token/utils.php";

// получение параметров
$domain = get_required(domain);
$from_address = get_required(from_address);
$to_address = get_required(to_address);
$amount = get_int_required(amount);
$pass = get_required(pass);
$delegate = get_string(delegate);

// код контракта
tokenSend($domain, $from_address, $to_address, $amount, $pass, $delegate);

// сохранение изменений
commit();
```

## References
- [GitHub repository](https://github.com/mikefromminsk/mfm-token)
- [NPM repository](https://www.npmjs.com/package/mfm-token)




