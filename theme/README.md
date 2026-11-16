# Дочерняя тема `hello-elementor-child`

Дочерняя тема для [Hello Elementor](https://wordpress.org/themes/hello-elementor/) (проверено на 3.5.2 и Elementor 4.4.0).

С v3.2 тема держит то, что действует на весь сайт, — чтобы оно оставалось и при выключенном плагине `nevehairus-site` (плагин теперь стилизует только свои виджеты): [research/07-calm-v3.2.md](../research/07-calm-v3.2.md) §12.5.

- **`functions.php`**: выключает reset.css и theme.css темы Hello, её ссылку пропуска и заголовок страницы на страницах из Elementor, Google Fonts Elementor; печатает ссылку пропуска «דילוג לתוכן הראשי» на `wp_body_open` @5; оборачивает содержимое шаблонов Elementor «Full Width» и «Canvas» в `<main id="content" class="site-main" tabindex="-1">` (шаблоны Hello уже дают `main#content`); подключает `style.css` после `elementor-frontend`.
- **`style.css`**: `box-sizing`, `body` (фон бумаги, цвет, шрифт с системными запасными, `overflow-x: clip`), кольцо фокуса, подчёркивание ссылок, стили ссылки пропуска, контейнер страниц не из Elementor, ссылки и списки подвала Theme Builder (подвал собран из штатных виджетов Elementor, §12.6: списки без маркеров, строка-ссылка не ниже 44 px, на телефоне две колонки ссылок — класс `nh-footer-links`), системный `prefers-reduced-motion`.

Ставить **вместе** с плагином 0.2.0: иначе на время вернётся CSS Hello, а ссылки пропуска окажется две или ни одной.

На тестовый сайт её ставит `python3 -I scripts/test_site.py setup`: собирает zip из этой папки, устанавливает Hello Elementor из каталога wordpress.org (если её нет) и активирует дочернюю тему. **Внимание:** `setup` не трогает уже установленную дочернюю тему, поэтому обновлённые файлы нужно залить отдельно (открытый вопрос для `test_site.py`).
