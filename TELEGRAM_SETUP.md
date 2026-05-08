# Telegram setup

This site supports two Telegram directions:

- Send an existing blog article to Telegram from `admin.php`.
- Create a new blog post by sending a message to your bot in the Telegram app.

## 1. Create the bot

1. Open Telegram and talk to `@BotFather`.
2. Create a bot with `/newbot`.
3. Copy the bot token.

## 2. Configure the site

1. Open `admin.php`.
2. Go to `Telegram`.
3. Paste the bot token.
4. Save once.

## 3. Create blog posts from Telegram

Telegram webhooks require a public HTTPS URL. Local USBWebserver at `localhost` cannot receive Telegram webhook calls from the internet. Upload this site to a free PHP host first, then use the hosted admin page.

After hosting:

1. Open the hosted `admin.php?view=telegram`.
2. Press `Activate webhook`.
3. Send `/id` to your bot in Telegram.
4. Paste the returned user ID into `Allowed Telegram user IDs`.
5. Save again.

Now send a message to the bot:

```text
My blog title
This is the blog post text.

It can have multiple paragraphs.
```

The first line becomes the title. Everything below it becomes the post content. Use `/draft` before the title if you want to save a draft:

```text
/draft My draft title
Draft content here.
```

## 4. Send site posts to Telegram

This is optional. It is useful if you also want the admin to share a blog article to a channel or chat.

For a public/private channel:

1. Add the bot to the channel.
2. Make the bot an admin with permission to post messages.
3. In the admin screen, use the channel username as the chat ID, for example `@mychannel`.

For a private chat or group:

1. Send a message to the bot first.
2. Open this URL in your browser, replacing `TOKEN`:
   `https://api.telegram.org/botTOKEN/getUpdates`
3. Copy the `chat.id` value into the admin screen.

After this, each published article has a `Send Telegram` button. You can also enable automatic sending for new published articles created in the admin.

## Free 24/7 hosting

For this PHP site, the simplest free 24/7 option is a free PHP host such as InfinityFree. Upload the files from this webroot to the hosting account, then use the hosted `admin.php` page instead of the local USBWebserver page.

Notes:

- Telegram's Bot API is free and works via HTTPS requests.
- A PHP host with HTTPS is enough for Telegram-to-blog posting; no always-running bot process is needed when using webhooks.
- Hosts like Render can run apps for free, but free web services may sleep after inactivity, so they are less ideal if you need the admin page instantly available all day.

References:

- Telegram Bot API: https://core.telegram.org/bots/api
- Telegram bots introduction: https://core.telegram.org/bots
- InfinityFree PHP hosting: https://www.infinityfree.com/
- Render free hosting notes: https://render.com/free
