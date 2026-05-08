# leks blog

Small PHP blog with a Telegram bot integration.

## What works

- Write and publish posts from `admin.php`.
- Send published posts to Telegram.
- Create blog posts by sending messages to the Telegram bot.

## Hosting note

The old PHP admin/webhook flow is kept in the repository, but the recommended deployment is now static:

1. Telegram sends messages to a Cloudflare Worker.
2. The Worker commits new files to `posts/` in GitHub.
3. GitHub Actions runs `scripts/build-static.php`.
4. The generated site in `docs/` is deployed as static HTML.

GitHub Pages from a private repository requires a paid GitHub plan. If the repository must stay private on GitHub Free, use Cloudflare Pages for the static site instead.

## Telegram

See `TELEGRAM_SETUP.md` and `worker/README.md`.
