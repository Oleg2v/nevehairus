# Страницы и шаблоны Elementor (шаг 4)

## Что здесь

| Файл | Что это |
|---|---|
| `pages/home.json` | Главная (`דף הבית`, слаг `דף-הבית`), статическая главная страница сайта: 11 разворотов прототипа `index.html` |
| `pages/main-home.json` | «מעון ראשי – בית לחיים», слаг старого сайта `מעון-ראשי-בית-לחיים` ([redirects.csv](../content/redirects.csv)): 8 разворотов `program-main-home.html` |
| `pages/header.json`, `pages/footer.json` | Шаблоны Theme Builder (Elementor Pro): шапка `nh-masthead`, подвал `nh-colophon`, условие «весь сайт» |
| `media-map.json` | Имя фото → id вложения на тестовом сайте (пишет `test_site.py pages`, только номера) |

Файлы `pages/*.json` **генерируются, руками их не правят**: `python3 -I scripts/build_pages.py` берёт состав страниц из [`plugin/tests/fixtures/pages.php`](../plugin/tests/fixtures/pages.php) и настройки вариантов фикстур виджетов (через `plugin/tests/elementor_data.php` на стенде), затем проверяет рендер: каждый виджет и обе собранные страницы совпадают с фикстурами стенда байт в байт.

Поля файла: `key`, `title`, `slug`, `front_page`, `post_type`, `document_type` (`_elementor_template_type`), `template` (`_wp_page_template`), `conditions`, `page_settings` (`_elementor_page_settings`), `built_from` и `elementor_data` — значение `_elementor_data` в том виде, как его хранит Elementor 4.4.0: один контейнер (Full Width, колонка, gap 0, padding 0), в нём виджеты; у строк повторителей есть `_id`.

Токены подставляются при выкладке: `@media:<имя>` — фото из `prototype/assets/img/manifest.json`; `@page:<key>` — адрес нашей страницы; `@page-id:<key>` — её id (шапка отмечает «מסגרות המגורים» на странице дома); `@home:<путь>` — `home_url()`. Собственные токены ссылок плагина (`#privacy`, `#accessibility`, …) берут страницы из его настроек и для этих двух страниц не годятся.

## Как выложить на тестовый сайт

```bash
python3 -I scripts/build_pages.py              # пересобрать JSON и проверить рендер (без сервера)
python3 -I scripts/test_site.py pages          # план: что будет сделано (только чтение)
python3 -I scripts/test_site.py pages --go     # выполнить
```

`--go`: 1) загружает 18 фото (самый большой `.webp`, подразмеры делает WordPress) с заголовком `nh:<имя>` и alt из манифеста — уже загруженные находятся по заголовку и не грузятся снова; 2) одним шагом PHP создаёт или обновляет 4 записи, найденные по мета-полю `_nh_page_key` (чужие записи не трогаются), пишет мета Elementor, пересобирает кэш условий Theme Builder, делает главную статической главной, чистит CSS Elementor; 3) очищает ezCache, проверяет обе страницы (200, нет ошибок PHP, все виджеты, шапка и подвал) и печатает адреса. Старые значения и каждое изменение — в журнале `~/.config/nevehairus/test-site-changes.jsonl`. Повторный запуск безопасен.

## Важно

- **Правки этих страниц в редакторе Elementor на сайте перезапишет следующий `pages --go`.** Менять — в фикстурах или плагине и пересобирать.
- Обёртка шаблонов шапки и подвала в Pro — `div` (настройка документа «HTML Tag»): ориентиры `<header>`/`<footer>` дают наши виджеты, как в прототипе (SPEC D3), без двойных ориентиров.
- Настройки плагина (Settings → נווה האירוס: e-mail, страницы доступности и карты сайта, PDF входов) этот шаг не заполняет: пока их нет, в подвале нет пункта e-mail, а ссылки `#accessibility` и т. п. остаются якорями.
- Позже, после каждой вехи, — экспорт из Elementor (Website Template ZIP, Design System ZIP, JSON шаблонов). Секретов в экспортах быть не должно.
