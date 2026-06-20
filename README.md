# MF Event

A lightweight WordPress plugin that renders an **agenda-style list of upcoming events** through a single shortcode. Built for school / institution calendars that mix two kinds of dates:

- **Recurring dates** that fall on the same day every year (most Gregorian dates, e.g. *First Day of School*).
- **Year-specific dates** that shift each year and you update annually (Hijri / religious days like Ramadan, Eid, Mawlid).

The output inherits the active theme's typography and adapts to the surrounding text colour, so it looks native on almost any theme without configuration.

---

## Features

- 📅 **One shortcode** — `[mf_event]` (alias `[mf_events]`), drop it anywhere.
- 🔁 **Recurring & one-off dates** — leave the year empty to repeat yearly, or set a year for shifting religious dates that auto-expire after they pass.
- 🎨 **Custom event types** — colour-coded accents, fully manageable from the admin.
- 🟢 **"Today" highlight** — events happening today get their own emphasized boxes.
- 📆 **Multi-day events** — optional end date, including year-spanning ranges (e.g. Dec 25 – Jan 3).
- 🌍 **Translation-ready** — ships with **Turkish (tr_TR)** and **Dutch (nl_NL)** out of the box, plus a `.pot` template for any other language.
- 🧩 **Theme-friendly CSS** — typography via `inherit`, colours via `currentColor`/`color-mix` with safe fallbacks.
- 🪶 **Zero dependencies** — one PHP file + one small CSS file. No JavaScript on the front end.
- ⬆️ **One-click sample data** and a **legacy importer** (from the older `isabet-events` plugin).

---

## Installation

1. Copy the `mf-event` folder into `wp-content/plugins/`.
2. Activate **MF Event** in *Plugins*.
3. Add events under **MF Events → Add Event**, or click **Load sample events** on first run to start from a ready-made academic calendar.
4. Place `[mf_event]` on any page, post, or page-builder text/HTML element.

---

## Usage

```text
[mf_event]
```

### Shortcode parameters

| Parameter | Default | Description | Example |
|-----------|---------|-------------|---------|
| `months`  | `12`    | How many months ahead to show. Hides events further out (also keeps next year's religious dates from appearing too early). | `[mf_event months="6"]` |
| `limit`   | `0`     | Max number of upcoming items. `0` = no limit. | `[mf_event limit="5"]` |
| `today`   | `yes`   | Show the highlighted "Today" boxes at the top. `no` to hide. | `[mf_event today="no"]` |
| `title`   | *(empty)* | Optional heading shown above the list. | `[mf_event title="Academic Calendar"]` |
| `type`    | *(all)* | Show only one type, by its **slug**. | `[mf_event type="religious"]` |

Parameters can be combined, e.g. `[mf_event title="This Year" months="12" limit="10"]`.

### How dates work

- **No year** on an event → it repeats the same day every year.
- **A year set** → a one-off date for that specific year; once it passes it drops off automatically — add next year's entry to keep it visible.
- The list is ordered relative to **today**, which uses your site's timezone (*Settings → General → Timezone*).

---

## Event types

Each type has a colour used as the card's left-accent. Manage them under **MF Events → Settings**:

| Default type | Slug | Colour |
|--------------|------|--------|
| Academic | `academic` | `#2b6cb0` |
| Religious | `religious` | `#2f855a` |
| National Holiday | `holiday` | `#c05621` |
| Festival | `festival` | `#b83280` |
| Break | `break` | `#6b46c1` |
| Other | `other` | `#4a5568` |

You can add, rename, recolour, or delete types. A type's **slug** (used in `type="…"`) is generated from its name on creation and is fixed afterwards so existing events keep working.

---

## Translations / i18n

Text domain: **`mf-event`** — translations live in [`/languages`](languages).

| Locale | Status |
|--------|--------|
| English (source) | ✅ built-in |
| Turkish — `tr_TR` | ✅ complete |
| Dutch — `nl_NL` | ✅ complete |

To add another language, copy `languages/mf-event.pot` to `mf-event-<locale>.po`, translate, and compile:

```bash
msgfmt mf-event-<locale>.po -o mf-event-<locale>.mo
```

WordPress loads the file matching the active site/user locale automatically.

---

## File structure

```
mf-event/
├── mf-event.php              # plugin (CPT, meta box, settings, shortcode, renderer)
├── assets/
│   └── mf-event.css          # front-end styles (theme-inheriting)
├── languages/
│   ├── mf-event.pot          # translation template
│   ├── mf-event-tr_TR.po/.mo # Turkish
│   └── mf-event-nl_NL.po/.mo # Dutch
└── README.md
```

---

## Requirements

- WordPress 5.0+ (tested with the timezone & `wp_date()` APIs)
- PHP 7.4+ (uses arrow functions and `DateTime`)

---

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
