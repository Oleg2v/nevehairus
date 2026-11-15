# Технический ресёрч: Elementor MCP, виджеты V4, WordPress 7.x, uPress, Hello Elementor

**Дата:** 2026-11-15
**Контекст:** тестовый сайт: WordPress 7.1.4 (иврит), Elementor и Elementor Pro 4.4.0 с включённым V4 (Atomic Editor), сейчас тема Twenty Twenty-Five (будет Hello Elementor), хостинг uPress (ezCache, «סוכן וורדפרס»). План: 6–8 собственных премиум-виджетов в своём плагине, сборка страниц через Elementor MCP, JSON-шаблоны как резервная копия.

**Как читать документ**
- Каждый факт снабжён ссылкой. Если подтверждения нет, стоит пометка **«не проверено»**. Пометкой **(вывод)** отмечены мои умозаключения из фактов.
- **«по коду 4.4.0»** означает, что факт прочитан в исходниках релизного тега 4.4.0 в SVN-репозитории wordpress.org (`plugins.svn.wordpress.org/elementor/tags/4.4.0/…`). Это официальная поставка плагина, но не документация: поведение может измениться без объявления.
- **«in-repo docs»** означает документацию в ветке `main` GitHub-репозитория Elementor (`docs/atomic-builder/…`). Это не developers.elementor.com, и она может опережать релиз.
- Сайты проекта (nevehairus.com, nevehairus.ussl.co.il) не открывались. Ничего не устанавливалось, вход никуда не выполнялся.

---

## Краткое резюме

1. **Elementor MCP встроен в сам Elementor начиная с 4.3.0 (2026-09-22)** ([changelog](https://plugins.svn.wordpress.org/elementor/trunk/changelog.txt)) и заметно расширен в 4.4.0 (2026-11-09). Отдельного «облачного» сервера нет. Elementor регистрирует 38 abilities Core (включая условный `manage-revisions`) через WordPress Abilities API и отдаёт их через встроенную копию **WordPress MCP Adapter 0.7.1** по **HTTP**. Endpoint: `/wp-json/mcp/mcp-adapter-default-server`, авторизация через **Application Password** (заголовок `Authorization: Basic …`) (по коду 4.4.0).
2. **MCP собирает атомарные элементы (V4). Классические виджеты поддерживаются только из жёсткого списка «карт»** (Nav Menu, Counter, Gallery, WooCommerce и т. п.). Наши будущие виджеты на `\Elementor\Widget_Base` MCP **не сможет вставлять и настраивать**: так следует из кода 4.4.0, официального заявления нет. Их придётся ставить в редакторе вручную или через импорт JSON.
3. **Стабильность MCP средняя.** Метки «beta» в официальных источниках нет, но меньше чем за 2 месяца вышли 3 патч-релиза с MCP-фиксами (4.3.3, 4.3.4, 4.3.6) и минорный 4.4.0 с доработками. На GitHub открыто около десятка MCP-issues, в том числе регрессии 4.4.0 с динамическими тегами (#38144, #38191). MCP годится как ускоритель черновиков при обязательных бэкапах и ручной проверке.
4. **Официального API для собственных атомарных (V4) виджетов нет.** 24.06.2026 Elementor написал: «We won't be releasing an API soon… we do not recommend attempting to integrate…» ([#32950](https://github.com/orgs/elementor/discussions/32950)). С августа 2026 в репозитории есть только in-repo docs с `Atomic_Widget_Base`. Классический путь `Widget_Base` + хук `elementor/widgets/register` в 4.4.0 работает и описан на developers.elementor.com.
5. **Дизайн-токены V4.** Переменные (Color/Font/Size) и глобальные классы хранятся в активном Kit. Их можно синхронизировать в V3 Global Colors/Fonts, и тогда классические виджеты используют их как «глобальные». С 4.0.0 экспорт и импорт шаблонов и Website Templates включает классы и переменные.
6. **WordPress:** 7.0 вышел 20.05.2026, 7.1 вышел 19.08.2026, 7.1.4 (28.10.2026) — актуальная версия, 7.2 запланирован на 08.12.2026. Минимальный PHP 7.4, рекомендуемый 8.3+. **PHP 8.4 официально поддерживается WP 7.0/7.1.** Явного заявления Elementor о поддержке 8.4 нет: требования рекомендуют 8.3+, на GitHub открыты issues с deprecation-уведомлениями под 8.4.
7. **uPress:** **REST API по умолчанию выключен** (включается во вкладке «אבטחה»). Это критично для MCP, у которого REST-endpoint. ezCache имеет Development Mode (1/2/4/8/24 ч или постоянно). Бэкапы ежедневные с историей 30 дней. Код на shared-хостинге запускать нельзя, значит SSH/WP-CLI, по всей видимости, нет. PHP меняется в «הגדרות» → «ניהול הגדרות PHP», версия 8.4 доступна.
8. **«סוכן וורדפרס» в публичной документации uPress не найден.** Как он работает, по какому расписанию, как попасть в белый список и как его отключить — **не проверено**. Опубликован только список запрещённых плагинов.
9. **Тема:** Hello Elementor 3.5.2 (03.11.2026, «Tested up to: 7.1») плюс официальная дочерняя тема `hello-theme-child`. Hello Biz «скоро уйдёт в режим поддержки», для нового проекта его брать не стоит.

---

## 1. Elementor MCP

### 1.1 Что это и с какой версии

- **Что это.** MCP-подключение, через которое внешние ИИ-инструменты (Claude, Codex, Cursor) строят и редактируют контент Elementor. Результат — нативная редактируемая структура Elementor, а не код ([elementor.com/mcp](https://elementor.com/mcp/)). В справке: внешние клиенты могут «read page structures, build Atomic elements, manage global classes and variables, connect dynamic data, and update site templates» ([help: connect](https://elementor.com/help/how-to-connect-elementor-to-an-ai-tool-using-mcp/)).
- **Версия появления.** Core **4.3.0 (2026-09-22)**: «New: Introducing Elementor MCP…» и «New: Introducing a dedicated Elementor MCP page…» ([changelog Core](https://plugins.svn.wordpress.org/elementor/trunk/changelog.txt)). Pro **4.3.0 (2026-09-22)**: «Expanded Elementor MCP with Pro capabilities, including Theme Builder templates, Popups, and Components» ([changelog Pro](https://elementor.com/pro/changelog/)). Бета 4.3 анонсирована 01.09.2026 ([discussion #37152](https://github.com/orgs/elementor/discussions/37152)).
- **Развитие:**
  - Core 4.3.3 (2026-09-30), 4.3.4 (2026-10-05), 4.3.6 (2026-11-02): исправления MCP ([changelog Core](https://plugins.svn.wordpress.org/elementor/trunk/changelog.txt)).
  - Core **4.4.0 (2026-11-09)**: поддержка части legacy-виджетов (Counter, Progress Bar, Social Icons, Image Gallery, Image Carousel), переименование элементов в Structure, управление экспериментами, очистка кэша, System Info, информация о лицензии, загрузка изображений в медиатеку, **откат изменений MCP через ревизии**, WebMCP для форм ([changelog Core](https://plugins.svn.wordpress.org/elementor/trunk/changelog.txt)).
  - Pro 4.4.0: поддержка MCP для Countdown, Progress Tracker, Gallery и WooCommerce-виджетов, Popup Triggers/Advanced Rules, просмотр и управление отправками форм ([changelog Pro](https://elementor.com/pro/changelog/)).
- **Требования по справке:** WordPress 6.8+, Elementor Core и Pro 4.3.0+ ([help: connect](https://elementor.com/help/how-to-connect-elementor-to-an-ai-tool-using-mcp/)).
  - Нюанс (вывод): модуль активен, только если есть класс `McpAdapter` и функция `wp_register_ability` (по коду 4.4.0, [modules/mcp/module.php](https://plugins.svn.wordpress.org/elementor/tags/4.4.0/modules/mcp/module.php)). Abilities API появился в ядре в WP 6.9 ([developer.wordpress.org](https://developer.wordpress.org/news/2025/11/introducing-the-wordpress-abilities-api/)), а плагин MCP Adapter требует WP 6.9 ([wordpress.org/plugins/mcp-adapter](https://wordpress.org/plugins/mcp-adapter/)). Фактически нужен WP 6.9+. Для WP 7.1.4 это не проблема.
- **Без Pro и без Angie.** «The MCP can create and edit pages, sections and theme parts with only Elementor installed» ([elementor.com/mcp, FAQ](https://elementor.com/mcp/)). Pro-элементы требуют Pro. Pro 4.3.0+ открывает Forms, Popups, Loops, eCommerce-виджеты, Components, Dynamic Content, Headers и Footers ([help: build & edit](https://elementor.com/help/how-to-build-and-edit-your-site-using-elementor-mcp/)).

### 1.2 Как устроено: Abilities API + MCP Adapter + связь с Angie

- **Две системы** ([in-repo docs: mcp/overview.md](https://github.com/elementor/elementor/blob/main/docs/atomic-builder/mcp/overview.md)):
  1. **PHP abilities.** Серверные инструменты и ресурсы регистрируются через WordPress Abilities API (`wp_register_ability`) и отдаются внешним MCP-клиентам через MCP Adapter. Хуки: `wp_abilities_api_categories_init` (категория `elementor`), `wp_abilities_api_init`, `mcp_adapter_init`.
  2. **JS-реестр в редакторе** (пакет `@elementor/editor-mcp`). Связывает инструменты редактора с **Angie** и **WebMCP**. Документ подчёркивает, что эти системы не надо смешивать.
- **Встроенные зависимости в 4.4.0** ([vendor/composer/installed.json](https://plugins.svn.wordpress.org/elementor/tags/4.4.0/vendor/composer/installed.json)):
  - `wordpress/mcp-adapter` **v0.7.1**
  - `wordpress/php-mcp-schema` v0.2.0
  - `elementor/elementor-mcp-composer` 1.0.20
  - Elementor несёт свою копию адаптера. Отдельный плагин MCP Adapter ставить не нужно.
  - Сам MCP Adapter с версии 0.7.0 объявил использование «as a bundled library» устаревшим ([wordpress.org/plugins/mcp-adapter](https://wordpress.org/plugins/mcp-adapter/)). Поэтому в будущем возможны изменения.
- **Серверы** (по коду 4.4.0, [Server_Bootstrap.php](https://plugins.svn.wordpress.org/elementor/tags/4.4.0/vendor/elementor/elementor-mcp-composer/src/Mcp/Server_Bootstrap.php)):
  - Abilities Elementor добавляются в **дефолтный сервер адаптера** `mcp-adapter-default-server`. Это рекомендованный endpoint: `rest_url('mcp/mcp-adapter-default-server')` → `https://<домен>/wp-json/mcp/mcp-adapter-default-server`.
  - Дополнительно создаётся сервер `elementor-mcp-server` (REST-namespace `elementor`, маршрут `mcp`) с транспортом `HttpTransport`. В коде он описан так: «This is the legacy Elementor MCP endpoint: switch to the default MCP server».
- **Angie** — отдельный бесплатный плагин-агент Elementor ([wordpress.org/plugins/angie](https://wordpress.org/plugins/angie/)): версия 1.1.19, требует WP 6.9+, Tested up to 7.1.4, более 100 000 установок.
  - Angie находит abilities, зарегистрированные через `wp_register_ability()`. В версии 1.1.14 появилась «Angie MCP» (подключение «any AI client» через Angie). В 1.1.19 улучшена совместимость с настройками Elementor MCP. SDK: [github.com/elementor/angie-sdk](https://github.com/elementor/angie-sdk).
  - Для Elementor MCP Angie **нужен** для подключения abilities других плагинов (WooCommerce, Yoast, ACF), custom widgets/apps, кастомизации WP Admin, массовых операций с контентом и «Full design systems» ([elementor.com/mcp](https://elementor.com/mcp/)).
  - На той же странице карточка «Edits and redesigns» помечена «Requires Angie», а FAQ говорит, что правка страниц работает без Angie. В коде 4.4.0 ability `elementor/manage-elements` есть в Core. Это противоречие официальной страницы, проверить на практике.
  - По данным беты 4.3 Angie должен быть установлен, активирован и залогинен ([#37152](https://github.com/orgs/elementor/discussions/37152)).

### 1.3 Транспорт, endpoint, аутентификация

| Параметр | Значение | Источник |
|---|---|---|
| Транспорт Elementor MCP | HTTP (`HttpTransport` MCP Adapter) | [Server_Bootstrap.php (4.4.0)](https://plugins.svn.wordpress.org/elementor/tags/4.4.0/vendor/elementor/elementor-mcp-composer/src/Mcp/Server_Bootstrap.php) |
| Endpoint для клиентов | `https://<домен>/wp-json/mcp/mcp-adapter-default-server` (поле `serverUrl` в сгенерированных данных) | [CredentialsController.php (4.4.0)](https://plugins.svn.wordpress.org/elementor/tags/4.4.0/vendor/elementor/elementor-mcp-composer/src/Admin/CredentialsController.php) |
| STDIO | Адаптер поддерживает STDIO через WP-CLI: `wp mcp-adapter serve --server=… --user=…`. Нужен shell на сервере, на shared-хостинге uPress, по всей видимости, недоступно (см. 5.5) | [MCP Adapter README](https://github.com/WordPress/mcp-adapter), [CLI Usage Guide](https://github.com/WordPress/mcp-adapter/blob/trunk/docs/guides/cli-usage.md) |
| Аутентификация | WordPress **Application Password**. Кнопка «Generate Prompt» создаёт пароль с именем `Elementor MCP - <Клиент> (<дата UTC>)` и отдаёт `encodedCredentials = base64(логин:пароль)` для заголовка `Authorization: Basic …` | [CredentialsController.php (4.4.0)](https://plugins.svn.wordpress.org/elementor/tags/4.4.0/vendor/elementor/elementor-mcp-composer/src/Admin/CredentialsController.php), [help: connect](https://elementor.com/help/how-to-connect-elementor-to-an-ai-tool-using-mcp/) |
| Условие для App Passwords | По умолчанию доступны только на сайтах по HTTPS. Управляются фильтрами `wp_is_application_passwords_available(_for_user)`. Появились в WP 5.6 | [make.wordpress.org: App Passwords guide](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/) |
| Права | Включать MCP и генерировать пароль может только пользователь с `manage_options` («Site admins only»). Агент наследует права пользователя | [CredentialsController.php](https://plugins.svn.wordpress.org/elementor/tags/4.4.0/vendor/elementor/elementor-mcp-composer/src/Admin/CredentialsController.php), [elementor.com/mcp](https://elementor.com/mcp/), [help: connect](https://elementor.com/help/how-to-connect-elementor-to-an-ai-tool-using-mcp/) |
| Отзыв доступа | Один клиент: Users → Profile → Application Passwords → Revoke. Весь сайт: Elementor → Elementor MCP → «Turn off MCP access» | [help: connect](https://elementor.com/help/how-to-connect-elementor-to-an-ai-tool-using-mcp/) |

Ещё по аутентификации:
- Каждое нажатие «Generate Prompt» создаёт **новый** пароль приложения (по коду 4.4.0). Лишние пароли нужно периодически отзывать.
- С WP 7.2 (сейчас бета) при создании пароля приложения отправляется письмо-уведомление ([WP 7.2 Beta 5](https://wordpress.org/news/2026/11/wordpress-7-2-beta-5/)).
- Если тестовый сайт закрыт серверной HTTP Basic-аутентификацией, возможен конфликт двух заголовков `Authorization` (вывод). В 4.3.2 есть фикс «Improved compatibility with sites protected by HTTP Basic Authentication» ([changelog](https://plugins.svn.wordpress.org/elementor/trunk/changelog.txt)), но полное решение **не проверено**.

### 1.4 Подключение Claude Code и Claude Desktop

**Шаги в WordPress** ([help: connect](https://elementor.com/help/how-to-connect-elementor-to-an-ai-tool-using-mcp/)):
1. Elementor → Elementor MCP.
2. «Enable MCP access».
3. Вкладка клиента (Claude Code / Claude Desktop / Codex / Cursor / Other).
4. Подтвердить галочку о создании Application Password.
5. «Generate Prompt» → «Copy prompt».
6. Вставить промпт в ИИ-инструмент.

В 4.3.4 улучшены инструкции для macOS, Windows и Linux ([changelog](https://plugins.svn.wordpress.org/elementor/trunk/changelog.txt)).

**Какие конфиги генерирует Elementor 4.4.0** (по коду, [mcp-page.js](https://plugins.svn.wordpress.org/elementor/tags/4.4.0/vendor/elementor/elementor-mcp-composer/assets/build/mcp-page.js)):

- **Claude Code**, файл `~/.claude.json` (Windows: `%USERPROFILE%\.claude.json`):
  ```json
  {
    "mcpServers": {
      "<имя-сайта>-elementor": {
        "type": "http",
        "url": "https://<домен>/wp-json/mcp/mcp-adapter-default-server",
        "headers": { "Authorization": "Basic <base64(логин:пароль_приложения)>" }
      }
    }
  }
  ```
  То же через CLI ([code.claude.com/docs/en/mcp](https://code.claude.com/docs/en/mcp)): `claude mcp add --transport http <name> <url> --header "Authorization: Basic <…>"`. В документации пример дан с `Bearer`. `Basic` — тот же механизм заголовка (вывод).
  - Если задан заголовок `Authorization`, Claude Code использует его и не переходит на OAuth.
  - Области хранения: `local` (по умолчанию, в `~/.claude.json` под путём проекта), `project` (файл `.mcp.json` в корне проекта, попадает в git), `user` (`~/.claude.json`).
  - В `.mcp.json` поддерживается подстановка `${VAR}` в `url` и `headers`. Проверка: `claude mcp list` или `/mcp` (там же).
  - Конфиг Elementor с `mcpServers` на верхнем уровне `~/.claude.json` по сути равен user-scope (вывод).
- **Claude Desktop**, файл `~/Library/Application Support/Claude/claude_desktop_config.json` (Windows: `%APPDATA%\Claude\claude_desktop_config.json`). Elementor ставит локальный STDIO→HTTP мост `mcp-remote` через `npx`, поэтому на машине нужен Node.js (вывод):
  ```json
  {
    "mcpServers": {
      "<имя-сайта>-elementor": {
        "command": "npx",
        "args": ["-y", "mcp-remote", "https://<домен>/wp-json/mcp/mcp-adapter-default-server",
                 "--header", "Authorization: Basic <base64>"]
      }
    }
  }
  ```
- **Альтернатива для Claude Desktop: «custom connector»** (Customize → Connectors → Add custom connector) ([support.claude.com](https://support.claude.com/en/articles/11175166-getting-started-with-custom-connectors-using-remote-mcp)):
  - запросы идут **из облака Anthropic**, а не с компьютера пользователя;
  - сервер должен быть доступен из интернета по HTTPS на порту 443;
  - фиксированные учётные данные в заголовках поддерживаются, но **Basic-auth явно не упомянута, не проверено**.
- Для Codex (`~/.codex/config.toml`), Cursor (`~/.cursor/mcp.json`) и «Other» генерируются аналогичные HTTP-конфиги (по коду 4.4.0).

### 1.5 Какие операции поддерживаются (abilities Core 4.4.0)

Полный список ID получен из исходников 4.4.0 ([modules/mcp/module.php](https://plugins.svn.wordpress.org/elementor/tags/4.4.0/modules/mcp/module.php), [папка abilities](https://plugins.svn.wordpress.org/elementor/tags/4.4.0/modules/mcp/abilities/)). Звёздочка (*) означает ability из списка `Mcp_V4_Gate`: без включённого Atomic Editor он возвращает ошибку `elementor_v4_required` ([mcp-v4-gate.php](https://plugins.svn.wordpress.org/elementor/tags/4.4.0/modules/mcp/utils/mcp-v4-gate.php)).

| Группа | Abilities |
|---|---|
| Страницы и документы | `elementor/create-page` (создаёт **черновик** страницы или поста, шаблоны `elementor_canvas` и т. п.), `elementor/update-page-settings`, `elementor/publish-document` (публикует страницу, пост, шаблон темы или попап; правки опубликованных страниц до вызова хранятся в автосохранении), `elementor/create-preview-link`*, `elementor/get-page-structure`, `elementor/list-posts` |
| Сборка и правка | `elementor/build-composition`* (XML-структура, стили, классы, interactions), `elementor/manage-elements`* (пакет до 50 операций: update/move/duplicate…), `elementor/list-widget-schemas`*, `elementor/get-widget-schemas`*, `elementor/list-dynamic-tags`*, `elementor/find-icons`*, `elementor/list-assets`, `elementor/upload-media`, `elementor/request-media-upload` |
| Дизайн-система | `elementor/manage-global-variable`*, `elementor/manage-global-variable-guide`*, `elementor/manage-classes`*, `elementor/reorder-classes`*, `elementor/manage-default-styles`*, `elementor/get-default-styles`*, ресурсы `elementor/global-classes-resource`*, `elementor/global-variables-resource`* |
| Компоненты и интеракции | `elementor/list-components`*, `elementor/manage-component`*, `elementor/interactions-schema-resource`* |
| Ресурсы и подсказки | `elementor/list-resources`, `elementor/read-resource`, `elementor/style-best-practices`, `elementor/wordpress-best-practices` |
| Обслуживание | `elementor/get-system-info`, `elementor/get-license-status`, `elementor/list-experiments`, `elementor/manage-experiments` (умеет включить Atomic Editor), `elementor/clear-cache`, `elementor/enable-agent-ready`, `elementor/agent-ready-scan` |
| Откат | `elementor/manage-revisions`: работает, если активен скрытый эксперимент `e_mcp_revision_snapshots` (статус Beta, по умолчанию активен) |

- **Pro-функции.** Theme Builder (headers, footers, single, archive, 404, условия показа), Popups, Dynamic data, Components ([help: build & edit](https://elementor.com/help/how-to-build-and-edit-your-site-using-elementor-mcp/)). Документация Core ссылается на Pro-abilities `elementor/list-site-parts` и `elementor/manage-site-parts` ([build-composition.md (4.4.0)](https://plugins.svn.wordpress.org/elementor/tags/4.4.0/modules/mcp/static-resources/abilities/build-composition.md)). Полный список Pro-abilities **не проверено**: код Pro не публичный.
- **Глобальные классы и переменные.** Справка: «Create and assign global CSS classes, reorder class hierarchies, manage typography presets and color variables… set default styles for HTML tags» ([help: build & edit](https://elementor.com/help/how-to-build-and-edit-your-site-using-elementor-mcp/)).
- **Шаблоны.** Создание шаблонов Theme Builder поддерживается (Pro). Отдельных abilities для **экспорта или импорта JSON-шаблонов** в Core 4.4.0 нет (по коду 4.4.0). `elementor/get-page-structure` с `include_content` возвращает дерево с settings и стилями (CSS), но это не формат экспорта Elementor.

### 1.6 Ограничения

- **Только V4-макеты для правки существующих сайтов.** «Yes you can, but only if they were built with atomic elements and layouts (V4)… Soon the MCP will also support older Widget layouts (V3)» ([elementor.com/mcp, FAQ](https://elementor.com/mcp/)).
- **V3-виджеты только по «картам».** В 4.4.0 карты заведены для `nav-menu`, `counter`, `progress`, `image-gallery`, `image-carousel`, `social-icons`, `countdown`, `progress-tracker`, `gallery`, 8 WooCommerce-виджетов и `theme-post-title`, `theme-archive-title`, `theme-post-content`, `theme-post-excerpt`, `theme-post-featured-image` ([registered-maps.php (4.4.0)](https://plugins.svn.wordpress.org/elementor/tags/4.4.0/modules/mcp/abilities/appliers/v3/maps/registered-maps.php)).
  - В реестре карт нет хуков или фильтров для сторонних карт (по коду 4.4.0).
  - `list-widget-schemas` отдаёт все V4-виджеты (с `atomic_props_schema`), а V3 — только при наличии карты ([list-widget-schemas-ability.php](https://plugins.svn.wordpress.org/elementor/tags/4.4.0/modules/mcp/abilities/list-widget-schemas-ability.php), [widget-context-helper.php](https://plugins.svn.wordpress.org/elementor/tags/4.4.0/modules/mcp/abilities/utils/widget-context-helper.php)).
  - Подсказка самого MCP: «anything it does not list must be edited manually in the Elementor editor» ([build-composition.md](https://plugins.svn.wordpress.org/elementor/tags/4.4.0/modules/mcp/static-resources/abilities/build-composition.md)).
  - **Следствие (вывод): собственные классические виджеты MCP не настроит. Собственные атомарные виджеты попадут в список**, если зарегистрированы с props-schema (см. раздел 2).
- **Всё сохраняется черновиком.** «Every generation lands as a draft, and nothing goes live until you review and publish it» ([elementor.com/mcp](https://elementor.com/mcp/)).
- **Конфликт правок.** Если агент сохраняет страницу, открытую в редакторе, появляется Conflict Warning с вариантами Force Save или Reload ([help: build & edit](https://elementor.com/help/how-to-build-and-edit-your-site-using-elementor-mcp/)).
- **Зависимость от модели.** Результат зависит от ИИ-модели, а сам MCP-сервер фиксирован (сотрудник Elementor, [#37152](https://github.com/orgs/elementor/discussions/37152)).

### 1.7 Статус и известные проблемы

- **Статус.** В changelog функция подана как «New» в стабильном релизе 4.3.0. Метки «beta» на [elementor.com/mcp](https://elementor.com/mcp/) и в справке нет. Beta-статус есть только у механизма отката через ревизии (`e_mcp_revision_snapshots`, [module.php](https://plugins.svn.wordpress.org/elementor/tags/4.4.0/modules/mcp/module.php)). Объявленные планы: «Coming soon: Widget (V3) layouts and Skills» ([elementor.com/mcp](https://elementor.com/mcp/)).
- **Открытые и недавние issues** на GitHub, статус по поиску на 2026-11-15. Это сообщения пользователей, многие ещё без триажа:

| Issue | Дата | Статус | Суть |
|---|---|---|---|
| [#38191](https://github.com/elementor/elementor/issues/38191) | 2026-11-13 | open | `build-composition` молча отбрасывает текстовые динамические теги и возвращает success (4.4.0) |
| [#38144](https://github.com/elementor/elementor/issues/38144) | 2026-11-10 | open | Регрессия 4.4.0: `manage-elements` отвергает существующие V4-документы (`invalid_value`), особенно с динамическими привязками. Ответа мейнтейнеров нет |
| [#38190](https://github.com/elementor/elementor/issues/38190) | 2026-11-13 | open | Agent Ready (4.4.0) превращает все 404 в 302 на /404 и ломает /.well-known и sitemap |
| [#37863](https://github.com/elementor/elementor/issues/37863) | 2026-10-28 | open | Дефолтный сервер адаптера пишет 3 ошибки «ability does not exist» на каждый REST-запрос, даже при выключенном MCP |
| [#37776](https://github.com/elementor/elementor/issues/37776) | 2026-10-20 | open | `get-widget-schemas` / `list-widget-schemas` падают (`sanitize_settings()` null) при включённом Atomic Editor |
| [#37759](https://github.com/elementor/elementor/issues/37759) | 2026-10-19 | open | `manage-elements`: стиль V3-виджета с вложенным селектором уходит в custom_css без обёртки |
| [#37736](https://github.com/elementor/elementor/issues/37736) | 2026-10-16 | open | `manage-elements` сбрасывает шаблон живой страницы на Default |
| [#37607](https://github.com/elementor/elementor/issues/37607) | 2026-10-06 | open | `POST /wp-json/elementor/mcp/` висит около 65 с на ответах 202 |
| [#37558](https://github.com/elementor/elementor/issues/37558) | 2026-10-04 | open | Правки Angie/MCP пропадают, теряется история |
| [#37547](https://github.com/elementor/elementor/issues/37547) | 2026-10-03 | open | Встроенный mcp-adapter глушит отдельный плагин MCP Adapter (404 `rest_no_route`) даже при выключенном модуле MCP |
| [#37514](https://github.com/elementor/elementor/issues/37514) | 2026-09-29 | open | Сбой с OpenAI Codex на `notifications/initialized` |
| [#37390](https://github.com/elementor/elementor/issues/37390) | 2026-09-22 | open | `tools/list`, `prompts/list`, `resources/list` → -32603 после initialize |
| [#37613](https://github.com/elementor/elementor/issues/37613), [#37516](https://github.com/elementor/elementor/issues/37516) | 2026-10-06 / 2026-09-30 | closed | Бесконечные переподключения и зависание endpoint на GET (исправлены) |

**Выводы по разделу 1**
- Elementor MCP — это не отдельный сервис, а связка «Abilities API + встроенный MCP Adapter 0.7.1» с HTTP-endpoint на сайте и авторизацией через Application Password. Claude Code подключается напрямую (`type: http`), Claude Desktop — через `npx mcp-remote` или облачный custom connector.
- Функция молодая (около 2 месяцев), активно патчится, и в 4.4.0 есть свежие регрессии. Использовать её стоит для быстрой сборки **атомарных** секций и дизайн-системы, с бэкапом перед сессией и ручной проверкой черновиков. Динамические теги через MCP на 4.4.0 лучше не трогать.
- Классические виджеты, включая наши будущие, MCP в 4.4.0 не настраивает (по коду).

**Источники (раздел 1):**
- https://elementor.com/help/how-to-connect-elementor-to-an-ai-tool-using-mcp/
- https://elementor.com/help/how-to-build-and-edit-your-site-using-elementor-mcp/
- https://elementor.com/mcp/
- https://plugins.svn.wordpress.org/elementor/trunk/changelog.txt
- https://elementor.com/pro/changelog/
- https://plugins.svn.wordpress.org/elementor/tags/4.4.0/modules/mcp/module.php
- https://plugins.svn.wordpress.org/elementor/tags/4.4.0/modules/mcp/utils/mcp-v4-gate.php
- https://plugins.svn.wordpress.org/elementor/tags/4.4.0/modules/mcp/abilities/appliers/v3/maps/registered-maps.php
- https://plugins.svn.wordpress.org/elementor/tags/4.4.0/modules/mcp/abilities/list-widget-schemas-ability.php
- https://plugins.svn.wordpress.org/elementor/tags/4.4.0/modules/mcp/static-resources/abilities/build-composition.md
- https://plugins.svn.wordpress.org/elementor/tags/4.4.0/vendor/composer/installed.json
- https://plugins.svn.wordpress.org/elementor/tags/4.4.0/vendor/elementor/elementor-mcp-composer/src/Mcp/Server_Bootstrap.php
- https://plugins.svn.wordpress.org/elementor/tags/4.4.0/vendor/elementor/elementor-mcp-composer/src/Admin/CredentialsController.php
- https://plugins.svn.wordpress.org/elementor/tags/4.4.0/vendor/elementor/elementor-mcp-composer/assets/build/mcp-page.js
- https://github.com/elementor/elementor/blob/main/docs/atomic-builder/mcp/overview.md
- https://github.com/orgs/elementor/discussions/37152
- https://github.com/WordPress/mcp-adapter (README) и https://github.com/WordPress/mcp-adapter/blob/trunk/docs/guides/cli-usage.md
- https://wordpress.org/plugins/mcp-adapter/
- https://wordpress.org/plugins/angie/ и https://github.com/elementor/angie-sdk
- https://code.claude.com/docs/en/mcp
- https://support.claude.com/en/articles/11175166-getting-started-with-custom-connectors-using-remote-mcp
- https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/
- GitHub issues: #38191, #38144, #38190, #37863, #37776, #37759, #37736, #37607, #37558, #37547, #37514, #37390, #37613, #37516 (https://github.com/elementor/elementor/issues/…)

---

## 2. Собственные виджеты в Elementor 4.x при включённом V4

### 2.1 Есть ли официальный API для собственных атомарных виджетов? Нет

- Обсуждение «Plugin Development Documentation & API Access for Elementor v4» ([#32950](https://github.com/orgs/elementor/discussions/32950)):
  - 02.10.2025, мейнтейнер: «At the moment we cannot offer any early documentation… We are actively changing pretty much everything».
  - 29.01.2026: «Expect it after 4.0 release or later».
  - **24.06.2026: «We won't be releasing an API soon… we do not recommend attempting to integrate with components and features for the new Atomic Editor… When we are ready, we will announce the new API extensively».** Более поздних ответов сотрудников в обсуждении нет.
- Developer Update 4.0 (19.03.2026) и 4.1 (19.05.2026) на developers.elementor.com про API атомарных виджетов ничего не сообщают ([4.0 update](https://developers.elementor.com/elementor-editor-4-0-developers-update/), [4.1 update](https://developers.elementor.com/elementor-editor-4-1-developers-update/)).
- На developers.elementor.com есть только **описание JSON-структуры** атомарных виджетов (`elType`, `widgetType: e-heading`, settings в формате `{ "$$type": …, "value": … }`) ([data-structure/atomic-widgets](https://developers.elementor.com/docs/data-structure/atomic-widgets)).

### 2.2 Неофициальный путь: in-repo docs (с августа 2026)

- В репозитории Elementor есть «In-repo reference for Elementor v4. For v3 addon development see developers.elementor.com» ([docs/atomic-builder/README.md](https://github.com/elementor/elementor/blob/main/docs/atomic-builder/README.md)). Папку переименовали из `docs/v4` 03.08.2026 ([PR #36747](https://github.com/elementor/elementor/pull/36747)), «extension skills» задокументированы 23.08.2026 ([PR #36810](https://github.com/elementor/elementor/pull/36810)).
- **Как зарегистрировать атомарный виджет** по этим документам ([authoring-widgets.md](https://github.com/elementor/elementor/blob/main/docs/atomic-builder/atomic-widgets/authoring-widgets.md), [пример create-atomic-widget.md](https://github.com/elementor/elementor/blob/main/docs/atomic-builder/examples/create-atomic-widget.md)):
  - класс-наследник `Atomic_Widget_Base` (`Elementor\Modules\AtomicWidgets\Elements\Base`) с `define_props_schema()`, `define_atomic_controls()`, `get_element_type()` (например, `e-my-greeting`) и Twig-шаблоном через трейт `Has_Template`;
  - регистрация тем же хуком `elementor/widgets/register`, контейнеры — через `elementor/elements/elements_registered`;
  - нужен эксперимент `e_atomic_elements`, на новых сайтах с 4.0.0 он включён автоматически ([atomic-widgets/overview.md](https://github.com/elementor/elementor/blob/main/docs/atomic-builder/atomic-widgets/overview.md)).
- Документ [skills-scope.md](https://github.com/elementor/elementor/blob/main/docs/atomic-builder/skills-scope.md) относит «create-atomic-widget» к **External**, то есть к тому, что можно сделать из стороннего плагина на публичных хуках. Это противоречит рекомендации сотрудников от июня 2026. **Риск (вывод):** API не объявлен стабильным, и между минорными версиями возможны поломки. Пример: миграции схем prop-типов ([migration/backward-compatibility.md](https://github.com/elementor/elementor/blob/main/docs/atomic-builder/migration/backward-compatibility.md)).

### 2.3 Классический `\Elementor\Widget_Base` в 4.4: поддерживается

- **Регистрация** по официальной документации: хук `elementor/widgets/register` и `$widgets_manager->register( new My_Widget() )` ([developers: Add New Widget](https://developers.elementor.com/docs/widgets/add-new-widget)).
- **Совместимость с V4.** «Legacy widgets and Atomic Elements can be used together on the same page… Nothing breaks» ([V4 FAQ](https://elementor.com/products/website-builder/v4-faq/)). «Updating to 4.0 will not affect your current live sites», а атомарные элементы сосуществуют с v3-виджетами ([4.0 developers update](https://developers.elementor.com/elementor-editor-4-0-developers-update/)).
- **Устаревшие API.** В плане депрекаций 3.5 для `register_widget_type()`, `register_control()`, `register_tag()`, хуков `elementor/widgets/widgets_registered`, `elementor/controls/controls_registered` и `elementor/dynamic_tags/register_tags` указаны жёсткая депрекация в 3.9.0 и **удаление в 4.3.0** ([v3.5 Planned Deprecations](https://developers.elementor.com/v3-5-planned-deprecations/)).
  - **По коду 4.4.0 они всё ещё на месте, но с deprecation-уведомлениями** ([widgets.php](https://plugins.svn.wordpress.org/elementor/tags/4.4.0/includes/managers/widgets.php), [controls.php](https://plugins.svn.wordpress.org/elementor/tags/4.4.0/includes/managers/controls.php), [dynamic-tags/manager.php](https://plugins.svn.wordpress.org/elementor/tags/4.4.0/core/dynamic-tags/manager.php)).
  - Использовать только новые хуки: `elementor/widgets/register`, `elementor/controls/register`, `elementor/dynamic_tags/register` и метод `register()`.
- **Категории.** Хук `elementor/elements/categories_registered` и `$elements_manager->add_category( 'slug', [ 'title' => …, 'icon' => … ] )`. Виджет указывает категорию в `get_categories()`. Категория видна в панели, только если в ней есть хотя бы один виджет ([developers: Widget Categories](https://developers.elementor.com/docs/widgets/widget-categories)).
  - Атомарные встроенные элементы лежат в категории `v4-elements` ([in-repo overview](https://github.com/elementor/elementor/blob/main/docs/atomic-builder/atomic-widgets/overview.md)).
  - В 4.3.0 из панели убрана секция «WordPress» ([changelog](https://plugins.svn.wordpress.org/elementor/trunk/changelog.txt)).
  - Других изменений в регистрации категорий для 4.x не найдено (**не проверено**, что их нет).
- **Контролы и динамические теги для классических виджетов.** Хуки `elementor/controls/register` и `elementor/dynamic_tags/register` в 4.4.0 работают (по коду 4.4.0). Для атомарных элементов есть отдельные фильтры `elementor/atomic/dynamic-tags/select_control_*` (по [skills-scope.md](https://github.com/elementor/elementor/blob/main/docs/atomic-builder/skills-scope.md)).
- **Полезные методы виджета** ([developers: Widget Structure](https://developers.elementor.com/docs/widgets/widget-structure)):
  - `has_widget_inner_wrapper(): bool`: документация рекомендует возвращать `false` у новых виджетов, чтобы убрать лишнюю обёртку `.elementor-widget-container` ([Widget DOM Optimization](https://developers.elementor.com/docs/widgets/widget-inner-wrapper));
  - `is_dynamic_content(): bool`: по умолчанию все виджеты считаются динамическими и не кэшируются. Вернуть `false` можно для статичного вывода, тогда сработает Element Caching ([Widget Output Caching](https://developers.elementor.com/docs/widgets/widget-output-caching)).

### 2.4 Скрипты и стили только на страницах с виджетом

По официальной документации ([developers: Widget Dependencies](https://developers.elementor.com/docs/widgets/widget-dependencies)):
- В виджете объявить `get_script_depends(): array` и `get_style_depends(): array`. Они должны возвращать **уже зарегистрированные** хэндлы.
- Регистрировать через `wp_register_script()` / `wp_register_style()` на `wp_enqueue_scripts` для фронтенда. Не `wp_enqueue_*`, иначе ассеты загрузятся на всех страницах (вывод).
- Elementor строит дерево зависимостей и подключает только то, что нужно виджетам на текущей странице. Зависимости подключаются при рендере, в том числе в iframe превью редактора.
- Можно использовать встроенные хэндлы `swiper` и `elementor-frontend`. Скрипты только для панели редактора подключаются на `elementor/editor/before_enqueue_scripts` или `elementor/editor/after_enqueue_scripts`.

### 2.5 Как классические виджеты выглядят и редактируются при V4

- В V4 Atomic Elements выводятся вверху панели элементов, а legacy-виджеты остаются в своих категориях ([V4 FAQ](https://elementor.com/products/website-builder/v4-faq/)).
- Legacy-виджеты редактируются старой панелью (контролы), атомарные — React-пакетами Editor V2. Legacy-контент не мигрируется автоматически ([in-repo what-is-v4.md](https://github.com/elementor/elementor/blob/main/docs/atomic-builder/getting-started/what-is-v4.md)).
- **Классы и переменные V4 к legacy-виджетам не применяются** ([V4 FAQ](https://elementor.com/products/website-builder/v4-faq/)). Мост — синхронизация переменных в Global Colors/Fonts (см. раздел 3). Выгода в производительности при смешивании v3 и v4 «may vary» (там же).
- MCP не видит классические сторонние виджеты (см. 1.6), а атомарные с props-schema видит (по коду 4.4.0).

**Выводы по разделу 2**
- Для 6–8 премиум-виджетов надёжный путь — **классический `Widget_Base`**: официальный, документированный, совместимый с V4-страницами. Цена — нет классов и переменных V4 внутри виджета (только V3 Global Colors/Fonts, с которыми синхронизируются V4-переменные) и нет настройки через MCP.
- **Атомарные виджеты** дают нативную стилизацию V4 и видимость в MCP, но сделаны на неофициальном API. Elementor прямо не рекомендует такую интеграцию (июнь 2026). Если идти этим путём, то только для 1–2 простых презентационных виджетов, с фиксированной версией Elementor и регресс-тестами при каждом обновлении.

**Источники (раздел 2):**
- https://github.com/orgs/elementor/discussions/32950
- https://developers.elementor.com/elementor-editor-4-0-developers-update/
- https://developers.elementor.com/elementor-editor-4-1-developers-update/
- https://developers.elementor.com/docs/data-structure/atomic-widgets
- https://github.com/elementor/elementor/blob/main/docs/atomic-builder/README.md
- https://github.com/elementor/elementor/blob/main/docs/atomic-builder/atomic-widgets/overview.md
- https://github.com/elementor/elementor/blob/main/docs/atomic-builder/atomic-widgets/authoring-widgets.md
- https://github.com/elementor/elementor/blob/main/docs/atomic-builder/examples/create-atomic-widget.md
- https://github.com/elementor/elementor/blob/main/docs/atomic-builder/skills-scope.md
- https://github.com/elementor/elementor/blob/main/docs/atomic-builder/getting-started/what-is-v4.md
- https://github.com/elementor/elementor/blob/main/docs/atomic-builder/migration/backward-compatibility.md
- https://github.com/elementor/elementor/pull/36747, https://github.com/elementor/elementor/pull/36810
- https://developers.elementor.com/docs/widgets/add-new-widget
- https://developers.elementor.com/docs/widgets/widget-dependencies
- https://developers.elementor.com/docs/widgets/widget-categories
- https://developers.elementor.com/docs/widgets/widget-structure
- https://developers.elementor.com/docs/widgets/widget-inner-wrapper
- https://developers.elementor.com/docs/widgets/widget-output-caching
- https://developers.elementor.com/v3-5-planned-deprecations/
- https://plugins.svn.wordpress.org/elementor/tags/4.4.0/includes/managers/widgets.php
- https://plugins.svn.wordpress.org/elementor/tags/4.4.0/includes/managers/controls.php
- https://plugins.svn.wordpress.org/elementor/tags/4.4.0/core/dynamic-tags/manager.php
- https://elementor.com/products/website-builder/v4-faq/

---

## 3. Elementor V4: глобальные классы, переменные, Kit, импорт/экспорт, ограничения

### 3.1 Хронология (Core changelog)

- **3.31.0 (2025-08-05):** появились Variables. 3.32.0: конвертация локальных стилей в Global Class. **3.33.0 (2025-11-10):** Variables Manager.
- **4.0.0 (2026-03-30):**
  - Atomic Editor получил статус **Stable** и включается **по умолчанию на новых сайтах**;
  - синхронизация атомарных Variables и Classes с Global Styles;
  - импорт и экспорт Classes и Variables через шаблоны и Website Templates.
- **4.1.0 (2026-05-26):** панель **Design System** (переменные и классы в одном месте), импорт и экспорт из неё, лимит классов поднят до 1000, переменные в Effects, Filters и Box Shadow. Минимальный WP 6.8.
- **4.2.0 (2026-07-20):** Grid, фикс «Global Classes can be shared across multiple Kits instead of being separated per Kit».
- **4.3.0 (2026-09-22):** **Default Styles** — глобальные стили для HTML-тегов.
- **4.4.0 (2026-11-09):** Default Styles добавлены в импорт и экспорт сайта.

Источник: [changelog Core](https://plugins.svn.wordpress.org/elementor/trunk/changelog.txt).

### 3.2 Переменные (design tokens)

- **Типы:** Color, Font, Size. Создаются в Variables Manager: Top Bar → ⋯ → Design System → вкладка Variables, или через иконку переменных в панели элемента ([help: Variables Manager](https://elementor.com/help/variables-manager/)).
- **Хранение** ([in-repo variables/overview.md](https://github.com/elementor/elementor/blob/main/docs/atomic-builder/variables/overview.md)):
  - токены привязаны к Kit и лежат в мета `_elementor_global_variables`;
  - на `:root` выводятся как CSS-переменные по **label** (`var(--brand-gold)`), внутренний id имеет вид `e-gv-*`;
  - лимит 1000 переменных на Kit;
  - при экспорте попадают в `global-variables.json`.
- **Права.** По сводке поиска по help-центру Elementor администраторы создают и редактируют переменные, а не-админы могут только применять существующие. Конкретная страница-источник не установлена: на странице [Variables Manager](https://elementor.com/help/variables-manager/) этого нет, **не проверено**.

### 3.3 Глобальные классы

- Хранятся как CPT `e_global_class` (одна запись на класс) плюс мета Kit (порядок, метки, синхронизация в V3 Global Fonts). Есть черновое (preview) и опубликованное состояние. В элементах класс указывается по внутреннему id `g-*`, в разметке — по label. При экспорте попадают в `global-classes.json` ([in-repo global-classes/data-model.md](https://github.com/elementor/elementor/blob/main/docs/atomic-builder/global-classes/data-model.md)).
- Приоритет глобальных классов определяется **порядком создания**, а не порядком применения к элементу. Для переопределений сотрудник рекомендует локальные классы ([#37152](https://github.com/orgs/elementor/discussions/37152)). Порядок можно менять (MCP `elementor/reorder-classes`).

### 3.4 Глобальные цвета и шрифты в V4 и отношение к Kit / Site Settings V3

- В V4 цвета и шрифты задаются **переменными** (Color/Font) и **классами** (например, типографические). Для HTML-тегов есть **Default Styles** (4.3.0) ([changelog](https://plugins.svn.wordpress.org/elementor/trunk/changelog.txt)).
- **Site Settings (Kit) V3 остаются.** Глобальные шрифты и цвета, theme style, layout, lightbox и фон экспортируются в блоке «Settings & Configurations» ([help: Website Templates](https://elementor.com/help/import-and-export-elementor-website-templates/)). Переменные V4 — отдельная от V3 Site Settings сущность, но тоже привязаны к Kit ([in-repo variables/overview.md](https://github.com/elementor/elementor/blob/main/docs/atomic-builder/variables/overview.md)).
- **Синхронизация V4 → V3** ([help: sync variables](https://elementor.com/help/how-to-sync-variables-and-global-elements/)):
  - в Variables Manager открыть ⋯ у переменной → «Sync to Global Colors» или «Sync to Global Fonts» → Save Changes;
  - после этого переменная выбирается как глобальный цвет или шрифт в классических виджетах;
  - ограничение: «Other font characteristics like color, size and weight cannot be synced»;
  - при отключении синхронизации V3-виджеты могут подставить подходящий глобальный цвет или шрифт.
- В 4.0 официально: Color Variables → v3 Global Colors, Typography Classes → v3 Global Fonts ([V4 FAQ](https://elementor.com/products/website-builder/v4-faq/), [4.0 developers update](https://developers.elementor.com/elementor-editor-4-0-developers-update/)).

### 3.5 Импорт и экспорт в 4.x

| Что | Как | Формат | Источник |
|---|---|---|---|
| Весь сайт или Kit («Website Templates») | Elementor → Editor → Home → Tools → Website Templates → Export/Import. Части: Content, Templates, Settings & Configurations, custom files/code (Pro+), темы (Pro+), плагины. Можно сохранить в облачную библиотеку (Advanced+) | ZIP | [help (обновлено 2026-09-15)](https://elementor.com/help/import-and-export-elementor-website-templates/) |
| Только классы и переменные | Тот же экспорт: оставить «Settings & configurations» → Edit → только Classes and Variables → Export as zip. По умолчанию классы и переменные **входят** в экспорт сайта | ZIP | [help (обновлено 2026-04-26)](https://elementor.com/help/how-to-export-and-import-variables-and-classes/) |
| Design System (V4) | Иконка Design System в Top Bar → ⋯ → Export/Import. Выборочный импорт невозможен. При дублях: заменить или оставить. Нужен Atomic Editor | ZIP | [help (обновлено 2026-05-20)](https://elementor.com/help/how-to-import-and-export-design-systems/) |
| Отдельный шаблон или страница | Template Library → Templates → ⋯ → Export. Импорт `.json` или `.zip`. С 4.0.0 шаблоны переносят и Classes/Variables | JSON (по [developers: Data Structure](https://developers.elementor.com/docs/data-structure/)) | [help: Template Library (2026-09-15)](https://elementor.com/help/template-library/), [changelog 4.0.0](https://plugins.svn.wordpress.org/elementor/trunk/changelog.txt) |

- **Структура JSON шаблона** ([developers: General Structure](https://developers.elementor.com/docs/data-structure/general-structure)):
  - корень `{ "title", "type", "version", "page_settings", "content" }`, актуальная `version` — `0.4`;
  - типы: `page`, `post`, `header`, `footer`, `error-404`, `popup`;
  - атомарные элементы хранят settings как `{ "$$type", "value" }` ([atomic-widgets](https://developers.elementor.com/docs/data-structure/atomic-widgets)).
- **Внутри ZIP** экспорт переменных и классов оформлен файлами `global-variables.json` и `global-classes.json` (in-repo docs: [variables](https://github.com/elementor/elementor/blob/main/docs/atomic-builder/variables/overview.md), [classes](https://github.com/elementor/elementor/blob/main/docs/atomic-builder/global-classes/data-model.md)). Полный состав ZIP по официальной справке **не проверено**.
- **Связность JSON (вывод).** Атомарные элементы ссылаются на классы и переменные по внутренним id (`g-*`, `e-gv-*`). Перед импортом страниц на чистый сайт сначала нужно перенести дизайн-систему (Kit или Design System ZIP), либо экспортировать шаблоны вместе с классами и переменными (4.0.0+).
- **Известные баги переноса.**
  - Исправлены: «Atomic Variables synced from Atomic Classes are missing after site import» (4.2.1) и «Draft classes are deleted when importing a Kit» (4.3.0) ([changelog](https://plugins.svn.wordpress.org/elementor/trunk/changelog.txt)).
  - Открыт: [#38144](https://github.com/elementor/elementor/issues/38144) (MCP и существующие V4-документы).

### 3.6 Ограничения V4 (что уже атомарное, что нет)

- **Атомарные сейчас** (по changelog и in-repo каталогу, [changelog](https://plugins.svn.wordpress.org/elementor/trunk/changelog.txt), [elements-catalog.md](https://github.com/elementor/elementor/blob/main/docs/atomic-builder/atomic-widgets/elements-catalog.md)):
  - Core: Heading, Paragraph, Button, Image, SVG, Divider, YouTube, Video (self-hosted, 4.0.0), Tabs, Div Block, Flexbox, Grid (4.2.0), Accordion и Background Video (4.3.0), List и Google Maps (4.4.0);
  - Pro: **Atomic Forms** (4.0.0, поля расширены в 4.1.0 и 4.4.0: Redirect/Popup After Submit, Honeypot, reCAPTCHA score), **Loop** (Pro 4.2.0), **Carousel** (Pro 4.4.0), Components и Interactions ([changelog Pro](https://elementor.com/pro/changelog/)).
- **Остаются V3.** Остальные виджеты, в том числе Pro. Косвенное подтверждение: MCP 4.4.0 поддерживает Nav Menu, Counter, Progress, Image Gallery, Image Carousel, Social Icons, Countdown, Progress Tracker, Gallery, WooCommerce-виджеты и Theme-виджеты (Post Title, Archive Title, Post Content, Post Excerpt, Featured Image) именно как **V3-виджеты** через «карты» ([registered-maps.php](https://plugins.svn.wordpress.org/elementor/tags/4.4.0/modules/mcp/abilities/appliers/v3/maps/registered-maps.php)). Testimonials Carousel на сентябрь 2026 в MCP не поддерживался ([#37152](https://github.com/orgs/elementor/discussions/37152)). Полного официального списка «ещё не атомарных» виджетов нет (**не проверено**).
- **Прочие ограничения.**
  - Классы и переменные нельзя применить к V3-виджетам ([V4 FAQ](https://elementor.com/products/website-builder/v4-faq/)).
  - Atomic Paragraph и Heading — это один тег. Rich inline HTML внутри только «планируется» (сотрудник, [#37152](https://github.com/orgs/elementor/discussions/37152)).
  - FAQ (изменён 2026-08-23) всё ещё пишет, что Grid и Loop «в разработке», хотя они вышли в 4.2.0. FAQ местами устарел.
- **Открытые V4-issues, которые могут задеть проект** (сообщения пользователей):
  - [#37299](https://github.com/elementor/elementor/issues/37299): CSS-файлы атомарных стилей периодически отдают 404, кэш сбрасывается на каждый запрос (важно при ezCache и Varnish);
  - [#37788](https://github.com/elementor/elementor/issues/37788): стили на переменных пропадают на канвасе до перезагрузки;
  - [#36205](https://github.com/elementor/elementor/issues/36205): HTTP 508 / Resource Limit на слабых Apache-серверах;
  - [#38116](https://github.com/elementor/elementor/issues/38116): таймауты сохранения шаблонов в 4.3.6.

**Выводы по разделу 3**
- Дизайн-систему проекта (цвета, шрифты, размеры) задавать **переменными V4** и сразу синхронизировать цвета и шрифты в V3 Global Colors/Fonts, чтобы наши классические виджеты могли использовать те же токены.
- Резервная копия должна состоять из трёх слоёв: Website Template ZIP (Kit, классы, переменные, Default Styles), Design System ZIP и JSON отдельных шаблонов и страниц. Восстанавливать в порядке «дизайн-система → шаблоны → страницы».

**Источники (раздел 3):**
- https://plugins.svn.wordpress.org/elementor/trunk/changelog.txt
- https://elementor.com/pro/changelog/
- https://elementor.com/help/variables-manager/
- https://elementor.com/help/how-to-sync-variables-and-global-elements/
- https://elementor.com/help/how-to-export-and-import-variables-and-classes/
- https://elementor.com/help/how-to-import-and-export-design-systems/
- https://elementor.com/help/import-and-export-elementor-website-templates/
- https://elementor.com/help/template-library/
- https://developers.elementor.com/docs/data-structure/ , https://developers.elementor.com/docs/data-structure/general-structure , https://developers.elementor.com/docs/data-structure/atomic-widgets
- https://developers.elementor.com/elementor-editor-4-0-developers-update/ , https://developers.elementor.com/elementor-editor-4-1-developers-update/
- https://elementor.com/products/website-builder/v4-faq/
- https://github.com/elementor/elementor/blob/main/docs/atomic-builder/variables/overview.md
- https://github.com/elementor/elementor/blob/main/docs/atomic-builder/global-classes/data-model.md
- https://github.com/elementor/elementor/blob/main/docs/atomic-builder/atomic-widgets/elements-catalog.md
- https://github.com/orgs/elementor/discussions/37152
- https://plugins.svn.wordpress.org/elementor/tags/4.4.0/modules/mcp/abilities/appliers/v3/maps/registered-maps.php
- GitHub issues: #37299, #37788, #36205, #38116, #38144

---

## 4. WordPress 7.x

### 4.1 Релизы (подтверждено)

| Версия | Дата | Источник |
|---|---|---|
| 6.9 «Gene» | 2025-12-02 | [wordpress.org/news releases](https://wordpress.org/news/category/releases/) |
| **7.0 «Armstrong»** | **2026-05-20** | [анонс 7.0](https://wordpress.org/news/2026/05/armstrong/) |
| **7.1 «Mary Lou»** | **2026-08-19** | [анонс 7.1](https://wordpress.org/news/2026/08/mary-lou/) |
| 7.1.1 / 7.1.2 / 7.1.3 | 2026-09-17 / 2026-09-22 / 2026-10-06 | [releases](https://wordpress.org/news/category/releases/) |
| **7.1.4** | **2026-10-28**, отмечена как `latest` в API | [7.1.4](https://wordpress.org/news/2026/10/wordpress-7-1-4/), [version-check API](https://api.wordpress.org/core/version-check/1.7/), [stable-check API](https://api.wordpress.org/core/stable-check/1.0/) |
| 7.2 | Beta 5 вышла 2026-11-10, финал запланирован на **2026-12-08** | [7.2 Beta 5](https://wordpress.org/news/2026/11/wordpress-7-2-beta-5/) |

### 4.2 Что важно для проекта

- **Abilities API.**
  - Серверная часть появилась в **WP 6.9**: `wp_register_ability()` на хуке `wp_abilities_api_init`, REST `wp-json/wp-abilities/v1` ([developer.wordpress.org](https://developer.wordpress.org/news/2025/11/introducing-the-wordpress-abilities-api/)).
  - **7.0** добавил клиентскую JS-часть `@wordpress/abilities` и `@wordpress/core-abilities` с UI и командной палитрой ([7.0 Field Guide](https://make.wordpress.org/core/2026/05/14/wordpress-7-0-field-guide/), [dev note](https://make.wordpress.org/core/2026/03/24/client-side-abilities-api-in-wordpress-7-0/)).
  - **7.1:** фильтры жизненного цикла выполнения, единый флаг публичности `public`, фильтрация через `wp_get_abilities()`, подготовка JSON Schema для клиентов ([7.1 Field Guide](https://make.wordpress.org/core/2026/08/05/wordpress-7-1-field-guide/), [dev note про public-флаг](https://make.wordpress.org/core/2026/08/04/a-unified-public-exposure-flag-for-abilities-in-wordpress-7-1/)).
- **MCP Adapter не входит в ядро.** Это отдельный пакет или плагин «AI Building Blocks»: v0.7.1, требует WP 6.9+, Tested up to 7.1.4, транспорты HTTP и STDIO. Abilities выставляются в MCP по opt-in через `meta.public` / `meta.mcp.public` ([wordpress.org/plugins/mcp-adapter](https://wordpress.org/plugins/mcp-adapter/), [README](https://github.com/WordPress/mcp-adapter)). Elementor 4.4.0 приносит свою копию (раздел 1.2), ставить отдельный плагин параллельно опасно ([#37547](https://github.com/elementor/elementor/issues/37547)).
- **AI в ядре 7.0.** WP AI Client и Connectors API, провайдеры Anthropic, Google и OpenAI в Settings → Connectors ([7.0 Field Guide](https://make.wordpress.org/core/2026/05/14/wordpress-7-0-field-guide/), [dev note AI Client](https://make.wordpress.org/core/2026/03/24/introducing-the-ai-client-in-wordpress-7-0/)). Для работы Elementor MCP это не требуется (вывод).
- **Редактор.**
  - 7.0: iframe-редактор принудителен, только когда все блоки имеют API v3+; новая схема админки «Modern»; Command Palette (Ctrl/⌘+K) ([7.0 Field Guide](https://make.wordpress.org/core/2026/05/14/wordpress-7-0-field-guide/), [анонс 7.0](https://wordpress.org/news/2026/05/armstrong/)).
  - **7.1:** «The post editor is now fully iframed for all themes», в том числе с legacy meta boxes. Больше всего это затрагивает page builders и плагины с метабоксами ([анонс 7.1](https://wordpress.org/news/2026/08/mary-lou/), [dev note](https://make.wordpress.org/core/2026/08/03/iframed-editor-changes-in-wordpress-7-1/)). Elementor заявил совместимость с 7.1 в 4.2.4 (2026-08-31) ([changelog](https://plugins.svn.wordpress.org/elementor/trunk/changelog.txt)).
  - В 7.1 не включена real-time collaboration, React 19 отложен ([7.1 Field Guide](https://make.wordpress.org/core/2026/08/05/wordpress-7-1-field-guide/)).
- **Application Passwords.**
  - Появились в 5.6 и работают по HTTPS ([guide](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/)).
  - С 6.8 хэшируются BLAKE2b ([dev note 6.8](https://make.wordpress.org/core/2025/02/17/wordpress-6-8-will-use-bcrypt-for-password-hashing/)).
  - В 7.0 и 7.1 изменений в field guides не найдено (**не проверено**, что их нет).
  - В **7.2** при создании пароля будет приходить письмо ([7.2 Beta 5](https://wordpress.org/news/2026/11/wordpress-7-2-beta-5/)).
- **wp_mail / PHPMailer.**
  - **6.9:** WP сам задаёт envelope sender (Return-Path): адрес из заголовка `From`, иначе `wordpress@home_url()`. Менять через фильтр `wp_mail_from` ([dev note 6.9](https://make.wordpress.org/core/2025/11/18/more-reliable-email-in-wordpress-6-9/)).
  - **7.0:** PHPMailer обновлён до **7.0.2** с исправлением бага с Sender-адресом ([7.0 Field Guide](https://make.wordpress.org/core/2026/05/14/wordpress-7-0-field-guide/)).
  - Для форм Elementor Pro важно, чтобы домен From совпадал с доменом сайта и SPF был настроен (вывод).

### 4.3 PHP

- **Минимум.** WP 7.0 поднял минимальную версию до **PHP 7.4** и прекратил поддержку 7.2 и 7.3 ([7.0 Field Guide](https://make.wordpress.org/core/2026/05/14/wordpress-7-0-field-guide/), [объявление](https://make.wordpress.org/core/2026/01/09/dropping-support-for-php-7-2-and-7-3/)). API отдаёт `php_version: 7.4` для 7.1.4 ([version-check](https://api.wordpress.org/core/version-check/1.7/)).
- **Рекомендация WordPress:** «PHP version 8.3 or greater» ([wordpress.org/about/requirements](https://wordpress.org/about/requirements/)).
- **Совместимость WP 7.0 и 7.1:** PHP 7.4, 8.0, 8.1, 8.2, 8.3, **8.4** и 8.5 отмечены «Y». Ярлык «beta support» упразднён в мае 2026 ([handbook, изменено 2026-11-04](https://make.wordpress.org/core/handbook/references/php-compatibility-and-wordpress-versions/)).
- **Elementor 4.4.0:** «Requires PHP: 7.4», «Tested up to: 7.1» ([readme 4.4.0](https://plugins.svn.wordpress.org/elementor/tags/4.4.0/readme.txt)). Справка: минимум 7.4 и «highly recommend using PHP 8.3+», про **8.4 ни слова**. Обновлено 2026-10-16, там же рекомендуют memory limit 512 МБ ([help: requirements](https://elementor.com/help/requirements/)).
  - Исправления под PHP 8.4: 3.32.0 и 3.34.0 ([changelog](https://plugins.svn.wordpress.org/elementor/trunk/changelog.txt)).
  - Открыты issues с deprecation-уведомлениями: [#36627](https://github.com/elementor/elementor/issues/36627) (PHP 8.4+, `Atomic_Global_Styles`, с 4.2.0), [#33001](https://github.com/elementor/elementor/issues/33001) (Pro Notes и Query Builder), [#32880](https://github.com/elementor/elementor/issues/32880) («Enable PHP 8.4 testing in CI»).
- **uPress предлагает PHP 8.4** (раздел 5.7).

**Выводы по разделу 4**
- WP 7.1.4 актуален. 7.2 выйдет 08.12.2026. После этого стоит проверить совместимость и не обновлять сразу, если у uPress включены автообновления.
- Для связки WP 7.1 + Elementor 4.4 консервативный выбор — **PHP 8.3** (рекомендуют и WordPress, и Elementor). PHP 8.4 допустим: WordPress его поддерживает, а у Elementor известны только deprecation-уведомления. Но официального «8.4 supported» от Elementor нет. При 8.4 держать `WP_DEBUG_DISPLAY=false` и смотреть лог.
- Abilities API в ядре даёт возможность (вывод, **не проверено** на практике) зарегистрировать **свои abilities** в плагине виджетов, если понадобится, чтобы агент управлял нашими виджетами через MCP.

**Источники (раздел 4):**
- https://wordpress.org/news/category/releases/
- https://wordpress.org/news/2026/05/armstrong/
- https://wordpress.org/news/2026/08/mary-lou/
- https://wordpress.org/news/2026/10/wordpress-7-1-4/
- https://wordpress.org/news/2026/11/wordpress-7-2-beta-5/
- https://api.wordpress.org/core/version-check/1.7/ , https://api.wordpress.org/core/stable-check/1.0/
- https://make.wordpress.org/core/2026/05/14/wordpress-7-0-field-guide/
- https://make.wordpress.org/core/2026/08/05/wordpress-7-1-field-guide/
- https://make.wordpress.org/core/2026/03/24/client-side-abilities-api-in-wordpress-7-0/
- https://make.wordpress.org/core/2026/03/24/introducing-the-ai-client-in-wordpress-7-0/
- https://make.wordpress.org/core/2026/08/04/a-unified-public-exposure-flag-for-abilities-in-wordpress-7-1/
- https://make.wordpress.org/core/2026/08/03/iframed-editor-changes-in-wordpress-7-1/
- https://make.wordpress.org/core/2026/01/09/dropping-support-for-php-7-2-and-7-3/
- https://make.wordpress.org/core/handbook/references/php-compatibility-and-wordpress-versions/
- https://wordpress.org/about/requirements/
- https://developer.wordpress.org/news/2025/11/introducing-the-wordpress-abilities-api/
- https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/
- https://make.wordpress.org/core/2025/02/17/wordpress-6-8-will-use-bcrypt-for-password-hashing/
- https://make.wordpress.org/core/2025/11/18/more-reliable-email-in-wordpress-6-9/
- https://wordpress.org/plugins/mcp-adapter/ , https://github.com/WordPress/mcp-adapter
- https://plugins.svn.wordpress.org/elementor/tags/4.4.0/readme.txt , https://elementor.com/help/requirements/
- GitHub issues: #36627, #33001, #32880, #37547

---

## 5. uPress (upress.co.il)

### 5.1 Кэш: ezCache, «режим разработки», Varnish, очистка

- **ezCache — плагин uPress** на wordpress.org, по объявлениям uPress заменил WP Rocket ([announcements](https://www.upress.co.il/announcements/)).
  - Stable tag **2.6.9**, «Requires PHP: 7.4», «Tested up to: 6.8». wordpress.org предупреждает, что плагин не тестировался с тремя последними мажорными версиями WP ([wordpress.org/plugins/ezcache](https://wordpress.org/plugins/ezcache/), [readme](https://plugins.svn.wordpress.org/ezcache/trunk/readme.txt)).
  - **Development Mode есть в самом плагине:** «Development Mode — temporarily disable cache». С версии **2.5.1** на Dashboard плагина выбирается длительность «1h/2h/4h/8h/24h/Permanent», показывается баннер с обратным отсчётом и есть кнопка Disable ([readme](https://plugins.svn.wordpress.org/ezcache/trunk/readme.txt)).
  - Включение: WP Admin → ezCache → Dashboard → блок Development Mode. Точное название пункта меню **не проверено**.
  - С 2.5.3 есть настройка «Send PURGE requests to Varnish on cache clear» (по умолчанию включена) ([readme](https://plugins.svn.wordpress.org/ezcache/trunk/readme.txt)).
- **«מצב פיתוח» на уровне панели uPress** тоже существует. Сайт переводят в режим разработки, чтобы работать «ללא תופעות לוואי של זיכרון מטמון». Рекомендуется задавать срок, по истечении которого режим выключится сам ([support: http→https](https://support.upress.co.il/general/wordpress-site-transfer-from-http-to-https/)). Точный путь в панели текстом в статье не указан, только скриншоты (**не проверено**).
- **Очистка кэша в панели:** «כל האתרים» → управление нужным сайтом → **«לשונית פיתוח»** → «ניהול כלי אחסון» → «כלי אחסון נוספים» → **«בצע ניקוי מטמון»** ([support: how to clear cache](https://support.upress.co.il/dev/how-to-clear-cache/)).
- **Varnish:** «ביצועים» → «הגדרות מתקדמות» → «הפעלת Varnish» и «ניהול הגדרות Varnish» (исключения по URL, параметрам, cookies и User-Agent) ([support: Varnish](https://support.upress.co.il/performance/%D7%94%D7%A4%D7%A2%D7%9C%D7%AA-%D7%95%D7%94%D7%92%D7%93%D7%A8%D7%AA-%D7%9E%D7%98%D7%9E%D7%95%D7%9F-varnish-cache/)).
  - По умолчанию кэшируются только GET и HEAD.
  - Исключены `/wp-admin`, `/wp-login.php`, корзина и checkout, а также cookies `wordpress_logged_in_`, `PHPSESSID`, `woocommerce_cart_hash`, `comment_author`.
  - Очистка выполняется через ezCache.
- Серверный кэш uPress называется «SuperCache» («כולל מטמון עמודים ומטמון מסדי נתונים») ([features](https://www.upress.co.il/features/)).

### 5.2 «סוכן וורדפרס» (WordPress agent)

- **В публичных материалах uPress не найден.** Проверены центр поддержки (категории WordPress, Общее, Безопасность, Разработка, Продвинутое, Бэкапы, Почта), FAQ, страница возможностей, объявления и блог.
- Англоязычный сайт uPress пишет: «This website does not offer a built-in AI builder, autonomous website agent or AI model credits» ([upress.io/ai-ready-wordpress](https://upress.io/ai-ready-wordpress/)). Это про ИИ-агента, а не про бота-модератора плагинов.
- **Подтверждено только следующее:**
  - есть **список запрещённых плагинов** («חלקית, ודינמית») ([upress.co.il/disallowed-plugins](https://www.upress.co.il/disallowed-plugins/)): кэш (W3 Total Cache, WP Super Cache, LiteSpeed Cache и др.), бэкапы (BackupWordPress, VersionPress и др.), оптимизация БД и «related posts», безопасность (Wordfence, iThemes, Sucuri, Limit Login Attempts и др.), а также WP phpMyAdmin, EWWW, TimThumb, Broken Link Checker. **Elementor, Angie, MCP Adapter и пользовательские плагины в списке нет**;
  - способ работы с исключениями: если не удаётся установить или активировать плагин, «פנו למחלקת השירות שלנו» (там же);
  - FAQ: «אנחנו לא מאפשרים להפעיל רק מספר מזערי של תוספים» ([FAQ](https://www.upress.co.il/faqs/));
  - автоматические обновления плагинов, о которых пишет поддержка, идут от самого WordPress, а не от uPress ([support: some plugins updated](https://support.upress.co.il/wordpress/some-plugins-updated/)).
- **Не проверено:** что именно делает «סוכן וורדפרס», как часто запускается, по каким критериям считает плагин «неразрешённым» (например, плагины не из каталога wordpress.org), есть ли белый список или отключение.

### 5.3 Перенаправления («הפניות»)

- Функция есть в панели. Среди прав роли «מפתח» указан пункт **«ניהול הפניות 301/302»** ([support: права при общем доступе](https://support.upress.co.il/website-settings/website-share-premissions/)).
- Есть статья про автоматическое перенаправление на www ([support: force-www](https://support.upress.co.il/website-settings/force-www/), содержимое не читал). В возможностях указан автоматический редирект всего трафика на HTTPS ([features](https://www.upress.co.il/features/)).
- **Пошаговой инструкции для «ניהול הפניות 301/302» не найдено, не проверено.**

### 5.4 Бэкапы

- **Ежедневные, история 30 дней:** «אנו מבצעים גיבויים יומיים לכל האתרים המאוחסנים אצלנו… כך שלכל אתר יש היסטוריית גיבויים של 30 ימים» ([FAQ](https://www.upress.co.il/faqs/)). Подсказка на странице тарифов: «אנחנו מגבים מידי יום באופן אוטומטי את האתר שלך כ-30 ימים לאחור» ([plans/managed](https://www.upress.co.il/plans/managed/)). То же на англоязычном сайте ([upress.io](https://upress.io/)).
- **Ручные бэкапы:** «Мой сайт» → вкладка «Бэкапы» → «Ручной бэкап» → «Управление ручными бэкапами» → «Создать бэкап». Это перевод: точные надписи на иврите не сверены. Восстановление полное (файлы и БД) или выборочное: только файлы, только БД или отдельные файлы ([support: create/restore](https://support.upress.co.il/backups/create-restore-backup/)). Можно скачать полный бэкап ([support: full backup](https://support.upress.co.il/backups/full-backup/)).

### 5.5 SSH / WP-CLI

- Официально: «מאחר ובשירות האחסון השיתופי של uPress אין גישה להריץ קוד ישירות בשרת נצטרך לבצע את הפעולה על המחשב הלוקאלי» ([support: Composer](https://support.upress.co.il/advanced/composer-install/)). То есть на shared-хостинге код на сервере не запускается.
- **SSH и WP-CLI в документации uPress не упоминаются.** Их отсутствие на shared-тарифах — вывод из цитаты выше. Для VPS **не проверено**.
- Есть альтернативы: файловый менеджер с привязкой Git-проекта ([support: Git](https://support.upress.co.il/dev/file-manager-git-project/)), FTP/FTPS и phpMyAdmin ([категория «פיתוח»](https://support.upress.co.il/category/dev/)), а также среды разработки на тарифах Professional, Business и VPS ([upress.io](https://upress.io/)).

### 5.6 Журнал исходящих писем

- **В документации uPress не найден, не проверено.**
- При проблемах с почтой поддержка советует плагин Check Email (WP Admin → Tools → Check email), сервис mail-tester.com и временное отключение SMTP-плагина ([support: outgoing mail](https://support.upress.co.il/emails/check-email-mail-tester/)). Есть статья про SPF ([support: SPF](https://support.upress.co.il/emails/spf-maill/)).

### 5.7 Смена версии PHP

- Путь: панель → **«לשונית הגדרות»** → **«ניהול הגדרות PHP»** → выбрать версию из установленных → **«שמירת הגדרות»**.
- «לאחרונה שוחררה גרסה 8.4 של PHP, והיא זמינה כעת לשימוש».
- Если нужной версии нет, uPress добавит её по обращению в поддержку. Совет: сначала проверить в среде разработки.

Источник: [support: change version](https://support.upress.co.il/general/change-version/).

### 5.8 REST API и «מצב התגוננות» — критично для MCP

- **REST API выключен по умолчанию:** «אפשרות ברירת המחדל שלנו היא לכבות את פרוטוקול ההתקשרות». Включается во вкладке «אבטחה» галочкой «הפעלת שימוש ב-REST API» ([support: REST API](https://support.upress.co.il/security/rest-api/)).
  - Endpoint Elementor MCP — это REST-маршрут `/wp-json/mcp/...` (раздел 1.3). Значит, **MCP, скорее всего, не заработает без этой галочки** (вывод).
  - Блокирует ли настройка запросы с Application Password (Basic), **не проверено**. Редактор Elementor с V4 на сайте работает, так что авторизованные через cookie запросы, видимо, проходят (вывод).
- **«מצב התגוננות»** ограничивает доступ к сайту текущей страной и включает дополнительные защитные настройки. Включается во вкладке «אבטחה» на заданный срок ([support: defensive mode](https://support.upress.co.il/security/defensive-mode/)). Облачный custom connector Claude ходит с серверов Anthropic (раздел 1.4), поэтому в этом режиме он может быть заблокирован (вывод, **не проверено**).

**Выводы по разделу 5**
- Перед подключением MCP нужно включить REST API в uPress и проверить, что запрос с Application Password доходит до `/wp-json/mcp/mcp-adapter-default-server`. На время разработки включить Development Mode в ezCache (и/или «מצב פיתוח» в панели) и чистить кэш после деплоя плагина.
- Поведение «סוכן וורדפרס» по отношению к нашему плагину (он не из каталога wordpress.org), к Angie и к любым MCP-плагинам **неизвестно**. Это главный инфраструктурный риск, его нужно снять до шага 3 (вопрос заказчику или в поддержку uPress — делает пользователь).
- Бэкапы есть (ежедневно, 30 дней), но перед каждой MCP-сессией стоит делать ручной бэкап из панели.

**Источники (раздел 5):**
- https://wordpress.org/plugins/ezcache/ , https://plugins.svn.wordpress.org/ezcache/trunk/readme.txt
- https://www.upress.co.il/announcements/
- https://support.upress.co.il/general/wordpress-site-transfer-from-http-to-https/
- https://support.upress.co.il/dev/how-to-clear-cache/
- https://support.upress.co.il/performance/%D7%94%D7%A4%D7%A2%D7%9C%D7%AA-%D7%95%D7%94%D7%92%D7%93%D7%A8%D7%AA-%D7%9E%D7%98%D7%9E%D7%95%D7%9F-varnish-cache/
- https://www.upress.co.il/features/
- https://upress.io/ai-ready-wordpress/ , https://upress.io/
- https://www.upress.co.il/disallowed-plugins/
- https://www.upress.co.il/faqs/
- https://support.upress.co.il/wordpress/some-plugins-updated/
- https://support.upress.co.il/wordpress/manage-wordpress-plugins/
- https://support.upress.co.il/website-settings/website-share-premissions/
- https://support.upress.co.il/website-settings/force-www/
- https://www.upress.co.il/plans/managed/
- https://support.upress.co.il/backups/create-restore-backup/ , https://support.upress.co.il/backups/full-backup/
- https://support.upress.co.il/advanced/composer-install/
- https://support.upress.co.il/dev/file-manager-git-project/ , https://support.upress.co.il/category/dev/
- https://support.upress.co.il/emails/check-email-mail-tester/ , https://support.upress.co.il/emails/spf-maill/
- https://support.upress.co.il/general/change-version/
- https://support.upress.co.il/security/rest-api/
- https://support.upress.co.il/security/defensive-mode/

---

## 6. Тема Hello Elementor (и Hello Biz / дочерняя тема)

- **Hello Elementor 3.5.2** от 2026-11-03: «Requires at least: 6.0», «Tested up to: 7.1», «Requires PHP: 7.4», тег `rtl-language-support` ([readme 3.5.2](https://themes.svn.wordpress.org/hello-elementor/3.5.2/readme.txt), [style.css 3.5.2](https://themes.svn.wordpress.org/hello-elementor/3.5.2/style.css), [Themes API](https://api.wordpress.org/themes/info/1.2/?action=theme_information&request[slug]=hello-elementor)).
  - Changelog: 3.5.2 «Updated WordPress compatibility to version 7.1», 3.5.0 (2026-08-25) «Updated WordPress compatibility to version 7.0».
- **Совместимость с Elementor 4.4.** В readme темы явного заявления нет (**не проверено**). На странице Hello Elementor включение V4 указано среди шагов: «Enable Editor V4 for pro CSS control» ([elementor.com/themes/hello-theme](https://elementor.com/themes/hello-theme/), изменено 2026-08-23).
- **Hello Biz 1.2.2** от 2026-05-24: «Tested up to: 6.8» ([readme](https://themes.svn.wordpress.org/hello-biz/1.2.2/readme.txt)).
  - Справка Elementor: **«Hello Biz will be moved into maintenance mode soon, no new features will be introduced. We recommend moving to Hello Themes instead»** (обновлено 2026-08-18) ([help: Hello Biz settings](https://elementor.com/help/edit-the-hello-biz-theme-settings/), [help: Hello Biz + Hello Plus](https://elementor.com/help/install-and-activate-hello-biz-and-hello-plus/)).
  - Ранее, в 3.28.4 (2025-04-22), Elementor сделал Hello Biz темой по умолчанию в онбординге ([changelog](https://plugins.svn.wordpress.org/elementor/trunk/changelog.txt)). Теперь курс сменился.
- **Дочерняя тема.** Официальный стартовый child-theme: [github.com/elementor/hello-theme-child](https://github.com/elementor/hello-theme-child), «Version: 2.0.0», «Template: hello-elementor». Фильтры `hello_elementor_*` (стили, мета description, skip-link, page title и др.) по документации предназначены для `functions.php` дочерней темы ([developers: Hello Elementor theme](https://developers.elementor.com/docs/hello-elementor-theme/)).
- **Рекомендации конкретно «для V4»** (какую тему брать именно под Atomic Editor) в официальных источниках не найдены (**не проверено**). Есть только упоминание V4 на странице Hello Elementor (см. выше).

**Выводы по разделу 6**
- Брать **Hello Elementor 3.5.2 с дочерней темой** `hello-theme-child`. Дочерняя тема нужна для небольших переопределений и фильтров `hello_elementor_*`, основная логика остаётся в нашем плагине.
- Hello Biz не использовать: тема уходит в режим поддержки и официально протестирована только до WP 6.8.

**Источники (раздел 6):**
- https://wordpress.org/themes/hello-elementor/ , https://themes.svn.wordpress.org/hello-elementor/3.5.2/readme.txt , https://themes.svn.wordpress.org/hello-elementor/3.5.2/style.css
- https://wordpress.org/themes/hello-biz/ , https://themes.svn.wordpress.org/hello-biz/1.2.2/readme.txt
- https://elementor.com/help/edit-the-hello-biz-theme-settings/
- https://elementor.com/help/install-and-activate-hello-biz-and-hello-plus/
- https://elementor.com/themes/hello-theme/
- https://github.com/elementor/hello-theme-child
- https://developers.elementor.com/docs/hello-elementor-theme/
- https://plugins.svn.wordpress.org/elementor/trunk/changelog.txt

---

## Выводы для плана работ (шаги 3–4)

> Допущение: шаг 3 — подготовка окружения и плагин собственных виджетов, шаг 4 — сборка страниц через MCP и резервирование JSON. Если в общем плане шаги нумеруются иначе, пункты переносятся без изменений.

### Шаг 3 — окружение и плагин виджетов

1. **Снять риск «סוכן וורדפרס» до установки собственного плагина.**
   - Выяснить у uPress (делает пользователь), удаляет ли агент плагины не из каталога wordpress.org, как добавить исключение и что будет с Angie и MCP-плагинами. В публичных источниках ответа нет (раздел 5.2).
   - До ответа: держать ZIP плагина и git-репозиторий как источник истины и после каждой ночи проверять, на месте ли плагин.
   - По возможности вести разработку в среде разработки uPress (тарифы Professional, Business, VPS).
2. **Включить REST API в uPress** (вкладка «אבטחה» → «הפעלת שימוש ב-REST API») и убедиться, что «מצב התגוננות» не активен на время работы с MCP (раздел 5.8).
3. **PHP.** Выбрать 8.3 (консервативно) или 8.4 (поддерживается WP 7.1; у Elementor нет явного заявления, возможны deprecation-уведомления). Переключается в «הגדרות» → «ניהול הגדרות PHP». Включить `WP_DEBUG_LOG`, а вывод ошибок на экран оставить выключенным (разделы 4.3, 5.7).
4. **Тема.** Перейти с Twenty Twenty-Five на Hello Elementor 3.5.2 и `hello-theme-child`. Hello Biz не брать (раздел 6).
5. **Архитектура виджетов: классический `\Elementor\Widget_Base`** (раздел 2):
   - регистрация через `elementor/widgets/register`, своя категория через `elementor/elements/categories_registered`, при необходимости `elementor/controls/register` и `elementor/dynamic_tags/register`. Устаревшие хуки (`widgets_registered`, `register_widget_type` и т. п.) не использовать;
   - ассеты регистрировать (`wp_register_script/style` на `wp_enqueue_scripts`) и отдавать через `get_script_depends()` / `get_style_depends()`, тогда они загружаются только на страницах с виджетом;
   - `has_widget_inner_wrapper()` возвращает `false`, `is_dynamic_content()` возвращает `false` у статичных виджетов;
   - цвета и шрифты в контролах привязывать к Global Colors/Fonts, куда синхронизируются V4-переменные (раздел 3.4). Проверить RTL;
   - **(опционально)** если важно, чтобы MCP мог ставить наши блоки: в отдельной ветке сделать прототип одного атомарного виджета (`Atomic_Widget_Base`, in-repo docs). Учесть, что API не официальный и Elementor не рекомендует такую интеграцию (раздел 2.2).
6. **Кэш на время разработки.** ezCache → Dashboard → Development Mode на 4–24 ч и/или «מצב פיתוח» в панели. После деплоя: «בצע ניקוי מטמון» и Clear Cache в ezCache. Следить за [#37299](https://github.com/elementor/elementor/issues/37299) (404 атомарных CSS) (раздел 5.1).
7. **Не ставить отдельный плагин MCP Adapter** параллельно с Elementor 4.4 ([#37547](https://github.com/elementor/elementor/issues/37547)). **Не включать «Agent Ready»** до исправления [#38190](https://github.com/elementor/elementor/issues/38190).

### Шаг 4 — страницы через MCP и JSON-резерв

1. **Подключение.**
   - Elementor → Elementor MCP → Enable → вкладка «Claude Code» → Generate Prompt.
   - Конфиг держать в user- или local-области Claude Code (`~/.claude.json`). **Не коммитить** токен в `.mcp.json` репозитория: при необходимости использовать `${VAR}`.
   - Проверить подключение через `claude mcp list` или `/mcp`. Endpoint: `/wp-json/mcp/mcp-adapter-default-server` (раздел 1.4).
2. **Порядок сборки.**
   - Ручной бэкап в панели uPress.
   - Дизайн-система: переменные, классы и Default Styles через `elementor/manage-global-variable`, `manage-classes`, `manage-default-styles` или вручную. Затем синхронизация переменных в Global Colors/Fonts.
   - Каркас страниц из атомарных элементов: `create-page` → `build-composition` → правки через `manage-elements`.
   - **Наши виджеты** вставлять вручную в редакторе. MCP 4.4.0 их не настраивает (раздел 1.6).
   - Проверка черновика по превью → публикация.
3. **Обходить проблемные зоны 4.4.0.**
   - Динамические теги через MCP ([#38191](https://github.com/elementor/elementor/issues/38191), [#38144](https://github.com/elementor/elementor/issues/38144)).
   - После `manage-elements` проверять шаблон страницы (Page Layout) ([#37736](https://github.com/elementor/elementor/issues/37736)).
   - Не править одну страницу одновременно в редакторе и через агента.
   - Для отката держать включённым откат через ревизии (`elementor/manage-revisions`).
4. **JSON-резерв (три слоя)** после каждой вехи; хранить в git, в каталоге `templates/` проекта, без секретов:
   1. Website Template ZIP: Settings & Configurations, классы и переменные, Default Styles (4.4.0+).
   2. Design System ZIP (переменные и классы).
   3. Экспорт каждого шаблона и страницы из Template Library в `.json` (с 4.0.0 вместе с классами и переменными).
   Восстанавливать в порядке «дизайн-система → шаблоны → страницы», потому что элементы ссылаются на id классов и переменных (раздел 3.5). Автоматизировать экспорт через WP-CLI на uPress, по всей видимости, нельзя (раздел 5.5).
5. **Гигиена доступа.** После сессий отзывать лишние Application Passwords (каждый «Generate Prompt» создаёт новый). При простое выключать «Turn off MCP access». С WP 7.2 (08.12.2026) на создание пароля начнут приходить письма (раздел 1.3).
6. **Обновления.** Elementor 4.4.x активно патчит MCP. Перед обновлением Elementor/Pro или WP 7.2: бэкап, проверка на копии, сверка changelog.

---

## Что не удалось подтвердить

1. **uPress «סוכן וורדפרס»:** существование в публичной документации, расписание («каждый день»), критерии «неразрешённости», белый список и отключение. Ничего не найдено.
2. **uPress:**
   - пошаговая инструкция к «ניהול הפניות 301/302» (подтверждено только наличие функции);
   - журнал исходящих писем;
   - явное наличие или отсутствие SSH и WP-CLI (есть только косвенная цитата про shared-хостинг) и их наличие на VPS;
   - блокирует ли выключенный REST API запросы с Application Password;
   - влияет ли «מצב התגוננות» на MCP-клиентов;
   - точный путь к «מצב פיתוח» в панели;
   - кэширует ли Varnish или SuperCache ответы `/wp-json/`.
3. **Elementor MCP:**
   - официальная метка статуса (beta/stable) — её нет ни в одну сторону;
   - полный список Pro-abilities;
   - работа Basic-авторизации в облачных custom connectors Claude;
   - поведение MCP на RTL- и ивритском контенте;
   - противоречие на elementor.com/mcp («Edits and redesigns — Requires Angie» против FAQ «edit… with only Elementor installed»).
4. **Собственные виджеты и MCP:** вывод, что классические сторонние виджеты недоступны через MCP, а атомарные доступны, сделан по коду 4.4.0. Официальной документации на этот счёт нет.
5. **Официальный API атомарных виджетов:** отсутствует (подтверждено заявлением от 24.06.2026). In-repo docs не являются публичным контрактом.
6. **PHP 8.4 и Elementor 4.4:** официального заявления о поддержке нет. Есть рекомендация 8.3+ и открытые issues про deprecation-уведомления. Минимальные требования Elementor Pro к PHP в changelog Pro не указаны.
7. **Hello Elementor и Elementor 4.4:** явного заявления о совместимости нет, только «Tested up to: 7.1» для WordPress. Официальной рекомендации «какую тему брать под V4» нет.
8. **Экспорт:** полный состав Website Template ZIP по официальной справке (имена JSON-файлов взяты из in-repo docs). Права не-админов на переменные дословно на странице справки не подтверждены.
9. **Изменения Application Passwords в WP 7.0 и 7.1:** в field guides ничего не найдено. Отсутствие изменений не подтверждено.
