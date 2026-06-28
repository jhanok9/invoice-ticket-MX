# Invoice Tracker MX

Local PHP app for managing Mexican purchase tickets and automating CFDI e-invoicing via the Fougasse portal. Uses AI (Gemini) to extract ticket data from photos and Playwright to fill the portal forms automatically.

---

## Requirements

- macOS (uses Chrome DevTools Protocol and macOS paths)
- [Homebrew](https://brew.sh)
- Google Chrome
- A [Gemini API key](https://aistudio.google.com/apikey)

---

## Setup

### 1. Install system dependencies

```bash
brew bundle
```

This installs PHP, PostgreSQL 16, Node.js, and Google Chrome via `Brewfile`.

### 2. Install Node dependencies

```bash
npm install
```

### 3. Configure the app

```bash
cp includes/config.example.php includes/config.php
```

Edit `includes/config.php` and fill in your Gemini API key:

```php
define('GEMINI_API_KEY', 'your-key-here');
```

The database connection uses your macOS username automatically — no password needed for a local Homebrew PostgreSQL install.

### 4. Create the database

```bash
export PATH="/opt/homebrew/opt/postgresql@16/bin:$PATH"
createdb invoice_tracker
psql invoice_tracker -f includes/schema.sql
```

### 5. Start everything

```bash
# Start PostgreSQL (runs once; restarts automatically on login)
brew services start postgresql@16

# Start the PHP development server
export PATH="/opt/homebrew/bin:$PATH"
php -S localhost:8080 -t /path/to/invoice-tracker/ > /tmp/php-server.log 2>&1 &
```

Open [http://localhost:8080](http://localhost:8080).

---

## Chrome automation setup

The stamping script opens tabs in your existing Chrome using the Chrome DevTools Protocol.

**First time (and after Chrome updates):** double-click `start-chrome-debug.command` in Finder. This relaunches Chrome with remote debugging enabled on port 9222.

After that, Chrome stays open between sessions and the script connects to it automatically.

---

## Project structure

```
invoice-tracker/
├── index.php               → Redirects to tickets list
├── tickets.php             → All tickets with filters
├── ticket_detail.php       → View, edit, and stamp a ticket
├── upload.php              → Upload ticket photo + AI extraction
├── companies.php           → Buyer company CRUD
├── stamp.php               → Launches Playwright in background
│
├── api/
│   ├── extract.php         → Calls Gemini to extract ticket data
│   ├── save_ticket.php     → Insert ticket
│   ├── update_ticket.php   → Update ticket fields
│   ├── stamp_status.php    → Polling endpoint for stamp result
│   ├── attach_cfdi.php     → Manual XML/PDF attachment
│   └── upload_image.php    → Save uploaded image
│
├── includes/
│   ├── config.example.php  → Config template (copy to config.php)
│   ├── db.php              → PDO connection
│   ├── layout.php          → Shared navbar/HTML
│   └── schema.sql          → PostgreSQL schema (3 tables)
│
├── scripts/
│   └── stamp_fougasse.js   → Playwright automation for Fougasse portal
│
├── start-chrome-debug.command  → Launches Chrome with CDP port 9222
├── Brewfile                    → System dependencies (brew bundle)
└── MANUAL.md                   → Full operation manual
```

---

## Workflow

1. **Upload** a ticket photo → Gemini extracts the data automatically
2. **Review and correct** the extracted fields, select the buyer company
3. **Stamp** — the script opens Fougasse in your Chrome, fills the form, and saves the XML and PDF to `/uploads/`
4. If the ticket was already stamped, the script recovers the files automatically

---

## Adding a new portal

1. Create `scripts/stamp_<name>.js` following the pattern in `stamp_fougasse.js`
2. Add an entry to the `$scriptMap` in `stamp.php`:

```php
$scriptMap = [
    'fougasse' => 'stamp_fougasse.js',
    'oxxo'     => 'stamp_oxxo.js',
];
```

The key is matched against the ticket's `store_name` (case-insensitive, partial match).

---

## Troubleshooting

| Problem | Fix |
|---|---|
| Chrome opens a new window instead of a tab | Run `start-chrome-debug.command` to relaunch Chrome with port 9222 |
| Stamp shows "El navegador está procesando…" forever | Check Chrome is open; see `/tmp/stamp_result_<id>.json` |
| Database connection error | Run `brew services start postgresql@16` |
| PHP server not responding | Run `php -S localhost:8080 -t /path/to/invoice-tracker/` |

Full details in [MANUAL.md](MANUAL.md).
