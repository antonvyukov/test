# Private Repo Updater

WordPress-плагин, который подключает **плагины и темы из закрытых Git-репозиториев** к обычным обновлениям WordPress. Обновление ставится одной кнопкой на экранах «Плагины» и «Темы», как с wordpress.org.

Поддерживаются:

- **GitHub** (github.com и GitHub Enterprise)
- **GitLab** (gitlab.com и self-hosted)
- **Gitea / Forgejo**
- **Bitbucket Cloud**
- любой сервер с **JSON-файлом** версии (`version` + `download_url`) — для собственной системы доставки

## Установка

1. Скопируйте папку плагина в `wp-content/plugins/private-repo-updater`.
2. Включите **Private Repo Updater**.
3. Откройте **Настройки → Private Repo Updates**.

Репозиторий должен содержать **корень плагина или темы** (то, что WordPress кладёт в `wp-content/plugins/имя` или `themes/имя`), а не монорепозиторий со случайной вложенностью.

## Как добавить закрытый репозиторий GitHub

1. Создайте [Fine-grained Personal Access Token](https://github.com/settings/tokens?type=beta) с правом **Contents: Read** на нужный репозиторий (для classic token — scope `repo`).
2. На экране плагина:
   - Type: Plugin или Theme
   - Installed slug: `my-plugin/my-plugin.php` или `my-theme`
   - Provider: GitHub
   - Repository: `owner/repo`
   - Access token: ваш PAT
   - Version source: **Latest release** (рекомендуется)

3. Нажмите **Check for updates now**. Если в релизе тег новее установленной версии, WordPress покажет обновление.

Токен после сохранения не показывается. Поле можно оставить пустым при редактировании — старый токен сохранится.

Альтернатива без хранения токена в базе:

```php
// wp-config.php
define( 'PRU_ACCESS_TOKEN', 'github_pat_…' );
```

Константа используется, если у источника нет собственного токена.

## Другие системы

| Провайдер | Host | Версия | Скачивание |
|-----------|------|--------|------------|
| GitHub Enterprise | `https://github.company.com` | releases / tags / branch | zipball или zip-ассет релиза |
| GitLab | пусто = gitlab.com, иначе URL инстанса | releases / tags / branch | `archive.zip` |
| Gitea / Forgejo | обязателен URL | releases / tags / branch | archive API |
| Bitbucket | bitbucket.org | теги (или branch) | `get/{tag}.zip`. Токен: `user:app-password` или access token |
| JSON | — | JSON-файл | поле `download_url` |

JSON-формат (совместим с Plugin Update Checker):

```json
{
  "version": "1.2.0",
  "download_url": "https://example.com/plugin-1.2.0.zip",
  "homepage": "https://example.com/plugin",
  "sections": { "changelog": "Fixes…" }
}
```

## Источник версии

- **Latest release** — последний GitHub/GitLab/Gitea release. Если отметить «Prefer a .zip asset», скачивается прикреплённый zip, а не исходники.
- **Latest version tag** — наибольший semver среди тегов (`v1.2.3` и `1.2.3` оба понимаются).
- **Branch** — читается заголовок `Version:` из главного файла плагина или `style.css` темы на указанной ветке.

## Программная регистрация

```php
add_filter( 'pru_sources', function ( $sources ) {
    $sources[] = array(
        'id'         => 'my-plugin',
        'label'      => 'My Plugin',
        'type'       => 'plugin',
        'slug'       => 'my-plugin/my-plugin.php',
        'provider'   => 'github',
        'repository' => 'my-org/my-plugin',
        'channel'    => 'release',
        'token'      => defined( 'MY_PLUGIN_GITHUB_TOKEN' ) ? MY_PLUGIN_GITHUB_TOKEN : '',
        'readonly'   => true,
    );
    return $sources;
} );
```

## Безопасность

- Управлять источниками могут только пользователи с правом `update_plugins`.
- Токены в базе шифруются через `AUTH` salt WordPress и не выводятся обратно в форму.
- Для GitHub Authorization отправляется только на `api.github.com` (или ваш GHE). Редирект на `codeload.github.com` идёт **без** токена — так и нужно для приватных zipball.
- Плагин должен оставаться активным: без него WordPress не знает, откуда обновлять ваши пакеты.

## Требования

- WordPress 5.8+
- PHP 7.4+
- openssl

## Проверка без WordPress

```bash
php tests/self-check.php
```
