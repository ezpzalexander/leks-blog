# leks blog

Small PHP blog with a Telegram bot integration.

## What works

- Write and publish posts from `admin.php`.
- Send published posts to Telegram.
- Create blog posts by sending messages to the Telegram bot.

## Hosting note

GitHub Pages cannot run this site because it only serves static files. This project needs PHP for the admin area and `telegram_webhook.php`.

Use PHP hosting with HTTPS for the Telegram webhook. A free PHP host such as InfinityFree is a better fit for the current implementation.

## Telegram

See `TELEGRAM_SETUP.md`.
