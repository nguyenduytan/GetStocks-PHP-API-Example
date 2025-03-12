# GetStocks API and Telegram Bot Webhook

This project demonstrates how to interact with the GetStocks API and integrate it with a Telegram Bot via a webhook. It allows users to download files from supported providers (e.g., Freepik, Shutterstock) by sending links to a Telegram Bot.

## Features

1. **Single Link Processing**:
   - When a user sends a single link (e.g., `https://www.freepik.com/free-vector/wedding-couple-love_719453.htm`), the bot uses the `getInfo` API to retrieve supported types and presents them as inline keyboard options.
   - Upon selecting a type, it processes the download request with `getLink` and `checkDownloadStatus`, returning a download link when ready.

2. **Multiple Links Processing**:
   - When multiple links are sent (separated by newlines), the bot processes each link directly using `getLink` with default `ispre = 1` and no type, then checks status with `checkDownloadStatus`.

3. **Error Handling**:
   - Invalid links, API errors, or timeouts are reported back to the user via Telegram messages.

4. **Link Limits**:
   - Maximum number of links is configurable via `MAX_INPUT_LINKS` (default: 5). Exceeding this limit triggers an error message.

## Prerequisites

- **PHP**: Version 7.4 or higher.
- **Composer**: Required to install dependencies ([getcomposer.org](https://getcomposer.org/)).
- **Guzzle HTTP Client**: Used for API requests.
- **Telegram Bot Token**: Obtain from [@BotFather](https://t.me/BotFather) on Telegram.
- **GetStocks API Token**: Register at [getstocks.net](https://getstocks.net/), login, and retrieve the token from your email.

## Installation

1. **Clone the Repository**:
   ```bash
   git clone https://github.com/yourusername/your-repo.git
   cd your-repo
