# Porto Child + Ollama LLM Bridge

A ready-to-use solution for the **Porto Child** child theme that switches Porto's built-in AI content generator from OpenAI to your own **Ollama** server with an OpenAI-compatible API.

The solution lets you keep using Porto's native interface (product descriptions, excerpts, meta titles/meta descriptions, outlines, etc.), but instead of the paid OpenAI API, requests are sent to a local or self-hosted server running `qwen3.6:27b-coding`.

---

## What the customization provides

- Replaces the OpenAI model with an Ollama model via Porto's native `porto_ai_model` filter.
- Substitutes a dummy API key because Porto requires a non-empty key field even when working with Ollama.
- Adapts the request body for Ollama:
  - disables streaming (`stream: false`);
  - disables the model's internal "thinking" (`think: false`);
  - removes OpenAI-specific parameters;
  - adds an instruction to the system prompt so the model returns clean text immediately.
- Redirects the HTTP request from `https://api.openai.com/v1/` to the Ollama server address.
- Increases timeouts: PHP request — 120 seconds, browser AJAX — 120 seconds.
- Preserves Porto's native values for `max_tokens` and `temperature` unless they are explicitly overridden by constants.

---

## Requirements

- WordPress with the **Porto** theme and the active **Porto Child** child theme.
- A Porto version with AI support and `porto_ai_*` filters.
- A working Ollama server with an OpenAI-compatible endpoint:

```text
http://HOST:PORT/v1/chat/completions
```

- An Ollama model loaded on the server. The default configuration uses:

```text
qwen3.6:27b-coding
```

- Network connectivity from the WordPress server to the Ollama server.
- A filled API key field in Porto's AI settings. In fact, it can be any non-empty string, for example `sk-ollama-local`.

---

## Installation

1. Copy the `functions.php` file into the child theme directory:

```text
wp-content/themes/porto-child/functions.php
```

2. If the child theme already has a `functions.php` file, move the block that starts with the following comment into it:

```php
// Связка Ollama + Porto
```

   The CSS loading block at the beginning of the file can be left as is — it is a standard part of the Porto child theme.

3. Open the theme settings:

```text
Porto → Theme Options → AI
```

   and enter any non-empty API key, for example:

```text
sk-ollama-local
```

4. Adjust the constants for your Ollama server.

5. Save the file and test generation through Porto's standard interface: post, page, or WooCommerce product → Porto AI block → Generate.

> Important: do not edit the parent `porto` theme. All changes live only in `porto-child`, so parent theme updates will not remove the customization.

---

## Settings

All settings are defined as constants at the beginning of the Ollama block. You can change them directly in `functions.php` or define them in `wp-config.php` before the theme is loaded.

| Constant | Default value | Purpose |
|---|---:|---|
| `PORTO_AI_MODEL` | `qwen3.6:27b-coding` | Model name in Ollama. Must exactly match `ollama list`. |
| `PORTO_OLLAMA_URL` | `http://127.0.0.1:11434/v1/` | Base OpenAI-compatible Ollama URL. Must end with `/v1/`. |
| `PORTO_OLLAMA_TIMEOUT` | `120` | PHP request timeout to Ollama, in seconds. |
| `PORTO_JS_TIMEOUT` | `120000` | Browser AJAX timeout in milliseconds. Must be at least `PORTO_OLLAMA_TIMEOUT * 1000`. |
| `PORTO_OLLAMA_DISABLE_THINKING` | `true` | Sends `think: false` to Ollama. Recommended to keep enabled. |
| `PORTO_OLLAMA_SYSTEM_HINT` | instruction string | Appended to Porto's system prompt and prevents the model from reasoning. |
| `PORTO_OLLAMA_MAX_TOKENS_OVERRIDE` | not set | If set to a number, hard-overrides `max_tokens` for all generation types. Porto values are used by default. |
| `PORTO_OLLAMA_TEMPERATURE_OVERRIDE` | not set | If set to a number, hard-overrides `temperature`. Porto values are used by default. |

### Example configuration

```php
define( 'PORTO_AI_MODEL', 'qwen3.6:27b-coding' );
define( 'PORTO_OLLAMA_URL', 'http://127.0.0.1:11434/v1/' );
define( 'PORTO_OLLAMA_TIMEOUT', 120 );
define( 'PORTO_JS_TIMEOUT', 120000 );
define( 'PORTO_OLLAMA_DISABLE_THINKING', true );

// For long texts, you can enable a forced limit:
// define( 'PORTO_OLLAMA_MAX_TOKENS_OVERRIDE', 2048 );

// For more predictable responses:
// define( 'PORTO_OLLAMA_TEMPERATURE_OVERRIDE', 0.7 );
```

### Porto values preserved by default

Until override constants are set, Porto's traditional parameters remain unchanged:

| Parameter | Content type | Porto value |
|---|---|---:|
| `max_tokens` | content | 2048 |
| `max_tokens` | excerpt | 64 |
| `max_tokens` | meta description | 265 |
| `max_tokens` | outline | 2048 |
| `temperature` | content | 0.9 |
| `temperature` | excerpt | 0.1 |
| `temperature` | meta description | 0.3 |

Exact values depend on the Porto theme version; after a theme update, it is worth re-checking generation.

---

## Preparing Ollama

On the Ollama server, run:

```bash
ollama pull qwen3.6:27b-coding
```

If Ollama runs on another machine, make sure it listens on more than `127.0.0.1`. For example:

```bash
OLLAMA_HOST=0.0.0.0:11222 ollama serve
```

Then check API availability from the server where WordPress runs:

```bash
curl -sS -X POST "http://HOST:PORT/v1/chat/completions" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "qwen3.6:27b-coding",
    "stream": false,
    "think": false,
    "messages": [
      {"role": "user", "content": "Say OK"}
    ]
  }'
```

The response should be JSON with a `choices[0].message.content` field.

---

## How the bridge works

1. The user clicks Generate in Porto's AI block.
2. Porto sends an AJAX request with `action=porto_ai_generate`.
3. The `porto_ai_model`, `porto_ai_openai_key`, and `porto_ai_request_body` filters prepare the request.
4. The `pre_http_request` hook intercepts only requests to `api.openai.com` and replaces the address with Ollama.
5. The Ollama response is returned to Porto in the format the theme expects.
6. An additional JS interceptor increases the AJAX timeout only for Porto AI requests on post and page screens.

Other external WordPress HTTP requests are not affected.

---

## Verification

1. The Ollama terminal should show `POST /v1/chat/completions` when Generate is pressed.
2. In the browser: `F12 → Network → Fetch/XHR` — the `admin-ajax.php` request with `action=porto_ai_generate` should return status `200`.
3. The response should contain generated text inserted into Porto's field.
4. Short prompts return quickly; descriptions of about 10 sentences generate reliably on the tested production hosting.

---

## Known limitations

- Generation of up to about **10 sentences** works reliably.
- Requests for **20 or more sentences** may fail with `Request time is out`. This is an infrastructure limitation: the web server/hosting may cut off long PHP requests even if increased timeouts are set in the code.
- If long texts are needed regularly, use one of the following approaches:
  - increase `fastcgi_read_timeout`, `proxy_read_timeout`, and `max_execution_time` on the hosting;
  - generate text in parts: introduction, main body, conclusion;
  - use a faster Ollama model;
  - implement a background generation queue separately if needed.
- The current version does not include an asynchronous queue: the request remains synchronous in order to preserve Porto's native logic and avoid breaking the theme interface.

---

## Troubleshooting common errors

| Symptom | What to check |
|---|---|
| `Request Failed` immediately after clicking | Ollama URL, port, model, endpoint availability from the WordPress server. |
| `cURL error 28` / timeout | Values of `PORTO_OLLAMA_TIMEOUT` and `PORTO_JS_TIMEOUT`, hosting limits, model speed. |
| `No content was generated` | Make sure `PORTO_OLLAMA_DISABLE_THINKING = true`; check that the model returns a non-empty `content`. |
| Text is cut off in the middle of a word | Increase `PORTO_OLLAMA_MAX_TOKENS_OVERRIDE`, but keep timeouts in mind. |
| 10 sentences work, 20 do not | Hosting or browser limit; reduce the volume per request or change server settings. |
| Porto reports a key error | The API Key field in Porto AI settings must be filled with any non-empty value. |

---

## Security

- The Ollama API does not require authorization by default. Do not expose the Ollama port to the public internet without a firewall, VPN, or protected tunnel.
- SSL verification is intentionally disabled in the code (`sslverify = false`) so the bridge works with local and tunnel addresses. Use only trusted channels.
- After updating Porto or Ollama, always re-test generation: the theme may change hook names or the request format.

---

## Repository structure

| File | Purpose |
|---|---|
| `functions.php` | Porto child theme file with standard style loading and the Ollama integration block. |
| `README.md` | Project description, installation, configuration, and troubleshooting. |
