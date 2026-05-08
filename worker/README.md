# Telegram Blog Worker

This Cloudflare Worker receives Telegram webhook updates and commits new blog posts to GitHub.

## Required secrets

Set these in Cloudflare Workers as secrets:

- `TELEGRAM_BOT_TOKEN`: token from `@BotFather`.
- `TELEGRAM_SECRET_TOKEN`: any random secret string. Use the same value when registering the Telegram webhook.
- `TELEGRAM_ALLOWED_USER_IDS`: comma-separated Telegram user IDs allowed to create posts.
- `GITHUB_TOKEN`: GitHub fine-grained personal access token with write access to repository contents.

Plain variables are in `wrangler.toml`:

- `GITHUB_OWNER`
- `GITHUB_REPO`
- `GITHUB_BRANCH`
- `PUBLISH_FROM_TELEGRAM`

## Telegram webhook

After deploying the Worker, register the webhook:

```bash
curl "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://YOUR-WORKER.YOUR-SUBDOMAIN.workers.dev","secret_token":"YOUR_TELEGRAM_SECRET_TOKEN","allowed_updates":["message"],"drop_pending_updates":true}'
```

Send `/id` to the bot to get your Telegram user ID.

Send a post like:

```text
My title
My post content.
```

Use `/draft` to save an unpublished post.

## Manage posts from Telegram

Available commands:

```text
/list
/delete slug
/publish slug
/draftify slug
/edit slug
New title
New content
```

Use `/list` to see the slugs. Every change commits to GitHub and triggers the static site rebuild.
