# Защита платных компонентов MODX3

Руководство по внедрению защиты транспортных пакетов для MODX Revolution 3.x с использованием API modstore.pro.

## Концепция

1. **При сборке пакета** — запрашивается ключ шифрования у modstore.pro
2. **Категория с элементами** (сниппеты, чанки, плагины) — шифруется AES-256-CBC
3. **При установке** — пакет запрашивает ключ расшифровки, проверяя лицензию для домена
4. **Без валидной лицензии** — установка прерывается

## Подготовка

### 1. Регистрация в modstore.pro

1. Создайте аккаунт разработчика на [modstore.pro](https://modstore.pro)
2. Загрузите первую версию компонента (можно без защиты)
3. Дождитесь модерации или попросите пока не публиковать
4. [Создайте ключ для сайта](https://modstore.pro/office/keys#office/keys/add) — он нужен для тестирования

### 2. Настройка провайдера в MODX

В админке MODX перейдите в **Система → Управление пакетами → Провайдеры**.

Провайдер `modstore.pro` должен содержать:
- **Username** — ваш email на modstore.pro
- **API Key** — ключ сайта из личного кабинета

## Структура файлов

```
_build/
├── build.php                    # Скрипт сборки (модифицированный)
├── config.inc.php               # Конфигурация с опцией encrypt
├── source/                      # Исходники элементов (шифруются)
│   ├── snippets/
│   ├── chunks/
│   └── plugins/
├── elements/                    # Определения элементов
└── resolvers/
    └── resolve.encryption.php   # Резолвер загрузки класса

core/components/COMPONENT/
└── src/
    └── Transport/
        └── EncryptedVehicle.php # Класс шифрования
```

## Интеграция

### Шаг 1: Скопируйте класс EncryptedVehicle

Скопируйте `EncryptedVehicle.php` в ваш компонент:

```
core/components/YOUR_COMPONENT/src/Transport/EncryptedVehicle.php
```

**Важно:** Замените namespace на ваш:

```php
namespace YourComponent\Transport;
```

### Шаг 2: Создайте резолвер

Скопируйте `resolve.encryption.php` в `_build/resolvers/`.

Измените путь к классу:

```php
$vehiclePath = MODX_CORE_PATH . 'components/YOUR_COMPONENT/src/Transport/EncryptedVehicle.php';
```

### Шаг 3: Перенесите исходники элементов

Скопируйте код сниппетов, чанков и плагинов в `_build/source/`:

```
_build/source/
├── snippets/
│   └── mysnippet.php
├── chunks/
│   └── mychunk.tpl
└── plugins/
    └── myplugin.php
```

Обновите пути в `_build/elements/*.php`:

```php
// Было:
'snippet' => getSnippetContent($sources['core'] . 'elements/snippets/mysnippet.php'),

// Стало:
'snippet' => getSnippetContent($sources['source'] . 'snippets/mysnippet.php'),
```

### Шаг 4: Модифицируйте build.php

Добавьте в конфигурацию:

```php
'encrypt' => true,  // Включить шифрование
```

Добавьте путь source:

```php
$sources = [
    // ...
    'source' => $root . '_build/source/',
    // ...
];
```

Добавьте блок шифрования после создания пакета (см. `build.example.php`).

### Шаг 5: Настройте атрибуты категории

```php
// Для зашифрованной категории
if (!empty($config['encrypt']) && defined('PKG_ENCODE_KEY')) {
    $attr['vehicle_class'] = 'YourComponent\\Transport\\EncryptedVehicle';
    $attr[xPDOTransport::ABORT_INSTALL_ON_VEHICLE_FAIL] = true;
}
```

## Порядок добавления в пакет

Порядок **критически важен**:

1. `EncryptedVehicle.php` — файл класса (xPDOFileVehicle, без шифрования)
2. `resolve.encryption.php` — загрузчик класса (xPDOScriptVehicle)
3. **Категория с элементами** — зашифрованный vehicle (EncryptedVehicle)
4. Остальные файлы и резолверы — стандартно
5. `resolve.encryption.php` повторно — для корректной деинсталляции

## Сборка пакета

```bash
php _build/build.php
```

При включённом шифровании в логе появится:

```
Encryption enabled, requesting key from modstore.pro...
Encryption key received successfully
Added EncryptedVehicle class to package
Added encryption resolver
...
Package encrypted!
```

## Проверка результата

1. Распакуйте созданный `transport.zip`
2. Откройте файл в папке `modCategory/*.vehicle`
3. Убедитесь, что вместо `object` есть `object_encrypted`

```php
// Зашифрованный vehicle содержит:
[
    'object_encrypted' => 'base64_encoded_encrypted_data...',
    'related_objects_encrypted' => 'base64_encoded_encrypted_data...',
]
```

## Установка зашифрованного пакета

1. Загрузите пакет в **Управление пакетами**
2. Перед установкой нажмите **Показать детали**
3. Выберите поставщика **modstore.pro**
4. Сохраните и установите

Без привязки к провайдеру установка завершится ошибкой:

```
Package provider not found. Please select modstore.pro as provider before installation.
```

## Ограничения

### Что защищено
- Код сниппетов (в БД после установки)
- Код чанков
- Код плагинов

### Что НЕ защищено
- Файлы `core/components/*/src/` — классы компонента
- Файлы `core/components/*/model/` — модели
- Файлы `assets/components/*/` — JS, CSS, изображения

### Известные уязвимости

1. **Дамп БД** — после установки код доступен в таблицах `modx_site_*`
2. **Открытые классы** — бизнес-логика в src/ не зашифрована
3. **Перехват ключа** — при модификации EncryptedVehicle.php

### Рекомендации по усилению

- Используйте **ionCube** для шифрования `src/*.php`
- Добавьте **runtime-проверки** лицензии в ключевых методах
- Реализуйте **watermarking** для отслеживания утечек

## FAQ

**Q: Что если modstore.pro недоступен?**
A: Установка невозможна. Сервер лицензий — единственный источник ключей.

**Q: Можно ли использовать свой сервер лицензий?**
A: Да, измените endpoint в `EncryptedVehicle::getDecodeKey()`.

**Q: Работает ли на MODX 2.x?**
A: Нет, этот класс использует namespaces MODX3. Для MODX2 см. каталог `protect/`.

**Q: Как тестировать без покупки?**
A: Если вы автор пакета на modstore.pro, ключ выдаётся без факта покупки.

## Файлы в этом каталоге

| Файл | Описание |
|------|----------|
| `README.md` | Эта инструкция |
| `EncryptedVehicle.php` | Класс шифрования (копировать в компонент) |
| `resolve.encryption.php` | Резолвер загрузки класса |
| `build.example.php` | Пример интеграции в build.php |

## Changelog

### v3.0.1 (2025-01)
- Исправлена совместимость с MODX3: `modTransportProvider::request()` возвращает PSR-7 Response
- Заменены методы `isError()`, `getError()`, `toXml()` на работу с PSR-7

### v3.0.0
- Начальная версия для MODX3

## Ссылки

- [API modstore.pro](https://modstore.pro/info/api)
- [Оригинальная статья о защите](https://modstore.pro/blog/encrypted-extras)
- [xPDO Transport](https://docs.modx.com/3.x/en/extending-modx/transport-packages)
