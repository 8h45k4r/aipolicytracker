# Translations

One YAML file per locale directory (`es`, `id`, `pt-BR`), keyed by record
(`hub:<jurisdiction slug>`, `policy:<slug>`). Only the site's **own** text is
translated: the page title, the answer-box summary, a lead and the FAQ. The
legal text, the instrument names and every fact stay as recorded in English
and link to the official source.

An entry is served at `/<locale>/…` as soon as it exists, with `hreflang`
links both ways, but it is **noindex until reviewed**: set `reviewed_by` to a
roster name (data/reviewers) and `reviewed_on` to the date of the review.
Unreviewed entries say so on the page.

```yaml
hub:japan:
  title: "Regulación de la IA en Japón 2026: leyes, estrategia y plazos"
  answer: "…"
  lead: "…"
  faq:
    - question: "…"
      answer: "…"
  translated_by: "Bhaskar Bhatt"
  reviewed_by: null
  reviewed_on: null
```
