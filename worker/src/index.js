const GITHUB_API = "https://api.github.com";

export default {
  async fetch(request, env) {
    if (request.method === "GET") {
      return json({ ok: true, service: "telegram-blog-worker" });
    }

    if (request.method !== "POST") {
      return json({ ok: false, error: "method_not_allowed" }, 405);
    }

    const receivedSecret = request.headers.get("X-Telegram-Bot-Api-Secret-Token") || "";
    if (!env.TELEGRAM_SECRET_TOKEN || receivedSecret !== env.TELEGRAM_SECRET_TOKEN) {
      return json({ ok: false, error: "forbidden" }, 403);
    }

    let update;
    try {
      update = await request.json();
    } catch {
      return json({ ok: true });
    }

    const message = update.message;
    const text = (message?.text || message?.caption || "").trim();
    const chatId = message?.chat?.id;
    const userId = message?.from?.id;

    if (!message || !text || !chatId || !userId) return json({ ok: true });

    if (/^\/id(?:@\w+)?$/i.test(text)) {
      await telegramReply(env, chatId, `Your Telegram user ID is <code>${escapeHtml(String(userId))}</code>.`);
      return json({ ok: true });
    }

    if (/^\/help(?:@\w+)?$/i.test(text)) {
      await telegramReply(env, chatId, "Send:\n\nTitle on the first line\nBlog text below it\n\nUse /draft before the title to save an unpublished post.");
      return json({ ok: true });
    }

    if (!isAllowedUser(env, userId)) {
      await telegramReply(env, chatId, "This Telegram user is not allowed. Send /id and add that ID to the Worker secrets.");
      return json({ ok: true });
    }

    const post = parsePost(text, env.PUBLISH_FROM_TELEGRAM !== "false");
    if (!post) {
      await telegramReply(env, chatId, "Send a title on the first line and the post content below it.");
      return json({ ok: true });
    }

    try {
      const result = await createGitHubPost(env, post);
      const status = post.published ? "published" : "saved as draft";
      await telegramReply(env, chatId, `Post ${status}: <b>${escapeHtml(post.title)}</b>\n${escapeHtml(result.html_url || result.path)}`);
    } catch (error) {
      await telegramReply(env, chatId, `Could not create the post: ${escapeHtml(error.message)}`);
    }

    return json({ ok: true });
  },
};

function parsePost(text, publishByDefault) {
  let published = publishByDefault;
  const command = text.match(/^\/(post|draft)(?:@\w+)?\s*(.*)$/is);
  if (command) {
    published = command[1].toLowerCase() === "post";
    text = command[2].trim();
  }

  const [firstLine, ...rest] = text.replace(/\r\n/g, "\n").replace(/\r/g, "\n").split("\n");
  const title = (firstLine || "").trim().slice(0, 120);
  const content = rest.join("\n").trim() || title;
  if (!title) return null;

  return {
    title,
    content,
    published,
    date: new Date().toISOString().slice(0, 10),
  };
}

async function createGitHubPost(env, post) {
  const owner = required(env.GITHUB_OWNER, "GITHUB_OWNER");
  const repo = required(env.GITHUB_REPO, "GITHUB_REPO");
  const branch = env.GITHUB_BRANCH || "main";
  const token = required(env.GITHUB_TOKEN, "GITHUB_TOKEN");
  const slug = await uniqueSlug(env, slugify(post.title));
  const path = `posts/${slug}.txt`;
  const body = [
    `title: ${post.title}`,
    `date: ${post.date}`,
    `published: ${post.published ? "true" : "false"}`,
    "tags: telegram",
    "---",
    post.content,
  ].join("\n");

  const response = await fetch(`${GITHUB_API}/repos/${owner}/${repo}/contents/${path}`, {
    method: "PUT",
    headers: githubHeaders(token),
    body: JSON.stringify({
      message: `Add Telegram post: ${post.title}`,
      content: base64Encode(body),
      branch,
    }),
  });

  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(data.message || `GitHub returned ${response.status}`);
  return { path, html_url: data.content?.html_url };
}

async function uniqueSlug(env, base) {
  const owner = required(env.GITHUB_OWNER, "GITHUB_OWNER");
  const repo = required(env.GITHUB_REPO, "GITHUB_REPO");
  const branch = env.GITHUB_BRANCH || "main";
  const token = required(env.GITHUB_TOKEN, "GITHUB_TOKEN");

  for (let i = 1; i < 100; i++) {
    const slug = i === 1 ? base : `${base}-${i}`;
    const response = await fetch(`${GITHUB_API}/repos/${owner}/${repo}/contents/posts/${slug}.txt?ref=${encodeURIComponent(branch)}`, {
      headers: githubHeaders(token),
    });
    if (response.status === 404) return slug;
    if (!response.ok) throw new Error(`Could not check slug availability: ${response.status}`);
  }
  return `${base}-${Date.now()}`;
}

function githubHeaders(token) {
  return {
    "Accept": "application/vnd.github+json",
    "Authorization": `Bearer ${token}`,
    "Content-Type": "application/json",
    "User-Agent": "telegram-blog-worker",
    "X-GitHub-Api-Version": "2022-11-28",
  };
}

async function telegramReply(env, chatId, text) {
  if (!env.TELEGRAM_BOT_TOKEN) return;
  await fetch(`https://api.telegram.org/bot${env.TELEGRAM_BOT_TOKEN}/sendMessage`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      chat_id: chatId,
      text,
      parse_mode: "HTML",
      disable_web_page_preview: true,
    }),
  });
}

function isAllowedUser(env, userId) {
  return String(env.TELEGRAM_ALLOWED_USER_IDS || "")
    .split(/[,\s]+/)
    .filter(Boolean)
    .includes(String(userId));
}

function slugify(value) {
  const slug = value.toLowerCase().trim().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
  return slug || "telegram-post";
}

function base64Encode(value) {
  const bytes = new TextEncoder().encode(value);
  let binary = "";
  for (let i = 0; i < bytes.length; i += 0x8000) {
    binary += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
  }
  return btoa(binary);
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function required(value, name) {
  if (!value) throw new Error(`${name} is missing`);
  return value;
}

function json(value, status = 200) {
  return new Response(JSON.stringify(value), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}
