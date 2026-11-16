# Дочерняя тема `hello-elementor-child`

Дочерняя тема для [Hello Elementor](https://wordpress.org/themes/hello-elementor/). В ней нет ни стилей, ни кода: дизайн, шрифты и скрипты приходят из плагина `nevehairus-site`, который сам отключает reset и theme CSS темы Hello. Тема нужна, чтобы обновления Hello ничего не стирали и было место для кода сайта, если он понадобится.

На тестовый сайт её ставит `python3 -I scripts/test_site.py setup`: собирает zip из этой папки, устанавливает Hello Elementor из каталога wordpress.org (если её нет) и активирует дочернюю тему.
