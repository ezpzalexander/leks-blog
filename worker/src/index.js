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
      await telegramReply(env, chatId, helpText());
      return json({ ok: true });
    }

    if (!isAllowedUser(env, userId)) {
      await telegramReply(env, chatId, "This Telegram user is not allowed. Send /id and add that ID to the Worker secrets.");
      return json({ ok: true });
    }

    if (text.startsWith("/")) {
      try {
        const handled = await handleCommand(env, chatId, text);
        if (handled) return json({ ok: true });
      } catch (error) {
        await telegramReply(env, chatId, `Command failed: ${escapeHtml(error.message)}`);
        return json({ ok: true });
      }
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

async function handleCommand(env, chatId, text) {
  const [firstLine, ...rest] = text.replace(/\r\n/g, "\n").replace(/\r/g, "\n").split("\n");
  const parts = firstLine.trim().split(/\s+/);
  const command = (parts[0] || "").replace(/@\w+$/, "").toLowerCase();
  const slug = parts[1] || "";

  if (command === "/list") {
    const posts = await listGitHubPosts(env);
    if (!posts.length) {
      await telegramReply(env, chatId, "No posts yet.");
      return true;
    }
    const lines = posts.slice(0, 20).map((post) => `${post.published ? "published" : "draft"} - <code>${escapeHtml(post.slug)}</code> - ${escapeHtml(post.title)}`);
    await telegramReply(env, chatId, lines.join("\n"));
    return true;
  }

  if (command === "/delete") {
    requireSlug(slug);
    await deleteGitHubPost(env, slug);
    await telegramReply(env, chatId, `Deleted <code>${escapeHtml(slug)}</code>.`);
    return true;
  }

  if (command === "/publish" || command === "/draftify") {
    requireSlug(slug);
    const post = await getGitHubPost(env, slug);
    post.meta.published = command === "/publish" ? "true" : "false";
    await updateGitHubPost(env, slug, post, `Set ${slug} ${post.meta.published === "true" ? "published" : "draft"}`);
    await telegramReply(env, chatId, `${post.meta.published === "true" ? "Published" : "Drafted"} <code>${escapeHtml(slug)}</code>.`);
    return true;
  }

  if (command === "/edit") {
    requireSlug(slug);
    const body = rest.join("\n").trim();
    const edited = parsePost(body, true);
    if (!edited) throw new Error("Use /edit slug, then title on the next line and content below it.");
    const current = await getGitHubPost(env, slug);
    current.meta.title = edited.title;
    current.meta.published = edited.published ? "true" : "false";
    current.content = edited.content;
    await updateGitHubPost(env, slug, current, `Edit Telegram post: ${edited.title}`);
    await telegramReply(env, chatId, `Edited <code>${escapeHtml(slug)}</code>: <b>${escapeHtml(edited.title)}</b>.`);
    return true;
  }

  await telegramReply(env, chatId, helpText());
  return true;
}

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

async function listGitHubPosts(env) {
  const owner = required(env.GITHUB_OWNER, "GITHUB_OWNER");
  const repo = required(env.GITHUB_REPO, "GITHUB_REPO");
  const branch = env.GITHUB_BRANCH || "main";
  const token = required(env.GITHUB_TOKEN, "GITHUB_TOKEN");

  const response = await fetch(`${GITHUB_API}/repos/${owner}/${repo}/contents/posts?ref=${encodeURIComponent(branch)}`, {
    headers: githubHeaders(token),
  });
  if (response.status === 404) return [];
  const files = await response.json().catch(() => []);
  if (!response.ok) throw new Error(files.message || `GitHub returned ${response.status}`);

  const posts = [];
  for (const file of files.filter((item) => item.type === "file" && item.name.endsWith(".txt")).slice(0, 20)) {
    const slug = file.name.replace(/\.txt$/, "");
    const post = await getGitHubPost(env, slug);
    posts.push({
      slug,
      title: post.meta.title || slug,
      date: post.meta.date || "",
      published: post.meta.published === "true",
    });
  }
  posts.sort((a, b) => b.date.localeCompare(a.date));
  return posts;
}

async function getGitHubPost(env, slug) {
  const owner = required(env.GITHUB_OWNER, "GITHUB_OWNER");
  const repo = required(env.GITHUB_REPO, "GITHUB_REPO");
  const branch = env.GITHUB_BRANCH || "main";
  const token = required(env.GITHUB_TOKEN, "GITHUB_TOKEN");
  const path = `posts/${cleanSlug(slug)}.txt`;

  const response = await fetch(`${GITHUB_API}/repos/${owner}/${repo}/contents/${path}?ref=${encodeURIComponent(branch)}`, {
    headers: githubHeaders(token),
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(data.message || `Post not found: ${slug}`);

  const raw = base64Decode(data.content || "");
  return { ...parsePostFile(raw), sha: data.sha, path };
}

async function updateGitHubPost(env, slug, post, message) {
  const owner = required(env.GITHUB_OWNER, "GITHUB_OWNER");
  const repo = required(env.GITHUB_REPO, "GITHUB_REPO");
  const branch = env.GITHUB_BRANCH || "main";
  const token = required(env.GITHUB_TOKEN, "GITHUB_TOKEN");
  const path = `posts/${cleanSlug(slug)}.txt`;
  const body = postFileBody(post.meta, post.content);

  const response = await fetch(`${GITHUB_API}/repos/${owner}/${repo}/contents/${path}`, {
    method: "PUT",
    headers: githubHeaders(token),
    body: JSON.stringify({
      message,
      content: base64Encode(body),
      sha: post.sha,
      branch,
    }),
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(data.message || `GitHub returned ${response.status}`);
  return data;
}

async function deleteGitHubPost(env, slug) {
  const owner = required(env.GITHUB_OWNER, "GITHUB_OWNER");
  const repo = required(env.GITHUB_REPO, "GITHUB_REPO");
  const branch = env.GITHUB_BRANCH || "main";
  const token = required(env.GITHUB_TOKEN, "GITHUB_TOKEN");
  const post = await getGitHubPost(env, slug);

  const response = await fetch(`${GITHUB_API}/repos/${owner}/${repo}/contents/${post.path}`, {
    method: "DELETE",
    headers: githubHeaders(token),
    body: JSON.stringify({
      message: `Delete Telegram post: ${cleanSlug(slug)}`,
      sha: post.sha,
      branch,
    }),
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(data.message || `GitHub returned ${response.status}`);
}

async function createGitHubPost(env, post) {
  const owner = required(env.GITHUB_OWNER, "GITHUB_OWNER");
  const repo = required(env.GITHUB_REPO, "GITHUB_REPO");
  const branch = env.GITHUB_BRANCH || "main";
  const token = required(env.GITHUB_TOKEN, "GITHUB_TOKEN");
  const slug = await uniqueSlug(env, slugify(post.title));
  const path = `posts/${slug}.txt`;
  const body = postFileBody({
    title: post.title,
    date: post.date,
    published: post.published ? "true" : "false",
    tags: "telegram",
  }, post.content);

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

function parsePostFile(raw) {
  const [frontMatter, ...contentParts] = raw.split(/\n---\n/);
  const meta = {};
  for (const line of frontMatter.split("\n")) {
    const index = line.indexOf(":");
    if (index === -1) continue;
    meta[line.slice(0, index).trim()] = line.slice(index + 1).trim();
  }
  return { meta, content: contentParts.join("\n---\n").trim() };
}

function postFileBody(meta, content) {
  return [
    `title: ${meta.title || "Untitled"}`,
    `date: ${meta.date || new Date().toISOString().slice(0, 10)}`,
    `published: ${meta.published === "true" ? "true" : "false"}`,
    `tags: ${meta.tags || "telegram"}`,
    "---",
    content || "",
  ].join("\n");
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

function base64Decode(value) {
  const binary = atob(String(value).replace(/\s/g, ""));
  const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0));
  return new TextDecoder().decode(bytes);
}

function cleanSlug(value) {
  return String(value || "").toLowerCase().replace(/[^a-z0-9-]/g, "");
}

function requireSlug(slug) {
  if (!cleanSlug(slug)) throw new Error("Missing slug. Use /list to find the slug.");
}

function helpText() {
  return [
    "Create post:",
    "Title on the first line",
    "Blog text below it",
    "",
    "Commands:",
    "/list",
    "/delete slug",
    "/publish slug",
    "/draftify slug",
    "/edit slug",
    "New title",
    "New content",
    "",
    "Use /draft before a new title to create a draft.",
  ].join("\n");
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
