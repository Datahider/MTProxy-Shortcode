# MTProxy QR Shortcode

WordPress-плагин для вывода MTProxy-ссылки в виде QR-кода и/или обычной ссылки через шорткоды.

## Возможности

- Получает MTProxy-ссылку с удалённого endpoint (plain text или JSON).
- Генерирует и кэширует QR-код.
- Поддерживает fallback-картинку, если ссылка/QR недоступны.
- Поддерживает два шорткода:
  - `[mtproxy_qr]` — вывод QR (опционально со ссылкой под ним).
  - `[mtproxy_link]` — вывод только ссылки.
- Поддерживает кастомные CSS-классы для контейнера, QR и блока ссылки.

## Установка

1. Скопируйте `mtproxy-shortcode.php` в папку плагина, например:
   `wp-content/plugins/mtproxy-shortcode/`.
2. В админке WordPress откройте **Плагины** и активируйте **MTProxy QR Shortcode**.
3. Перейдите в **Settings → MTProxy QR** и заполните настройки:
   - `URL endpoint с прокси-ссылкой`
   - `Таймаут запроса`
   - `Размер QR`
   - `TTL кэша`
   - `URL fallback-картинки` (опционально)
   - `Показывать ссылку под QR`

## Использование

### 1) QR-код (и при необходимости ссылка)

Базовый вариант:

```text
[mtproxy_qr]
```

С параметрами:

```text
[mtproxy_qr size="320" class="proxy-widget" qr_class="proxy-widget__qr" link_container_class="proxy-widget__link" show_link="1"]
```

Параметры `[mtproxy_qr]`:

- `size` — размер QR в px (120–1200).
- `class` — CSS-класс(ы) внешнего контейнера.
- `qr_class` — CSS-класс(ы) для `<img>` с QR.
- `link_container_class` — CSS-класс(ы) контейнера ссылки под QR.
- `show_link` — `1` или `0` (показывать ссылку под QR).

### 2) Только ссылка

```text
[mtproxy_link]
```

С параметрами:

```text
[mtproxy_link class="proxy-only-link"]
```

Параметры `[mtproxy_link]`:

- `class` — CSS-класс(ы) контейнера ссылки.

## Пример CSS

```css
/* Обёртка блока с QR */
.mtproxy-qr {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}

/* Изображение QR */
.mtproxy-qr-image {
  border-radius: 12px;
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.15);
}

/* Контейнер ссылки под QR */
.mtproxy-qr-link {
  font-size: 14px;
  line-height: 1.4;
}

/* Сама ссылка */
.mtproxy-qr-link-anchor {
  color: #0b57d0;
  text-decoration: none;
  border-bottom: 1px dashed currentColor;
}

.mtproxy-qr-link-anchor:hover {
  color: #084298;
  border-bottom-style: solid;
}

/* Пример пользовательских классов из shortcode-атрибутов */
.proxy-widget {
  padding: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
}
```

## Требования

- WordPress 6.0+
- PHP 7.4+
