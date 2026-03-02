---
name: generate-docs
description: "Generates documentation markdown pages for the Signals framework. Activates when creating docs, writing documentation, documenting a feature, generating a docs page from code, or explaining how a feature works for documentation purposes."
license: MIT
metadata:
  author: signals
---

# Generate Documentation

## When to Apply

Activate this skill when:

- Creating new documentation pages
- Writing docs for a feature, service, or architectural concept
- Generating a docs page from source code (class, file, or directory)
- Documenting how a feature works from a natural language explanation

## Modes

Determine the mode from `$ARGUMENTS`:

1. **Code mode** — arguments reference file paths or class names (e.g. `app/Services/DocsService.php`, `App\Actions\Opportunities\CreateOpportunity`). Read the code first, then generate documentation explaining the feature's purpose, public API, configuration, and usage patterns.
2. **Explanation mode** — arguments describe a topic or feature in natural language (e.g. `Members system — contacts, organisations, venues, and users`). Use the description as the primary source. Optionally explore related code for accuracy.
3. **Interactive mode** — no arguments provided. Ask the user what to document and which mode to use.

## Workflow

### 1. Gather Context

- **Code mode:** Read the referenced files. Understand the feature's purpose, public methods, configuration, relationships, and usage patterns. Look at related tests, DTOs, and action classes for additional context.
- **Explanation mode:** Use the user's description. Optionally search the codebase for related code to ensure accuracy.

### 2. Determine Placement

Ask or infer:

- **Section** — an existing section slug from `docs/documentation.json`, or a new section title if none fits.
- **Page title** — a clear, concise title for the page.
- **Page slug** — kebab-case derived from the title (e.g. `members-overview`).

If unsure, ask the user before proceeding.

### 3. Read the Manifest

Read `docs/documentation.json` to understand existing sections and pages. Do not duplicate existing pages.

### 4. Generate the Markdown File

Write the documentation page following these rules:

**Front matter (YAML):**

```yaml
---
title: Page Title Here
description: A brief description under 160 characters, used in search results.
---
```

**Content structure:**

- Start with a brief overview paragraph immediately after the front matter — explain what this feature is and why it matters.
- Use `##` (h2) for major sections, `###` (h3) for subsections. Do not use h4 or deeper.
- Headings are auto-ID'd by CommonMark HeadingPermalinkExtension for the "On This Page" sidebar TOC.
- Use fenced code blocks with language hints (`php`, `bash`, `blade`, `json`).
- Use tables for configuration options, parameters, and comparison lists.
- Use blockquotes (`>`) for tips and important notes, prefixed with `**Note:**` or `**Tip:**`.
- Keep descriptions under 160 characters for the front matter `description` field.
- Focus on what users need to know — not implementation internals. Document the "what" and "how to use", not the "how it works internally" unless that's specifically requested.
- Follow the tone and style of existing docs in `docs/getting-started/` — concise, direct, practical.

### 5. Write the File

Write to `docs/{section-slug}/{page-slug}.md`. Create the section directory if it doesn't exist.

### 6. Update the Manifest

Read `docs/documentation.json` again (to avoid stale data), then update it:

- If the section already exists, append the new page to its `pages` array.
- If the section is new, append a new section object with the page as its first entry.
- Preserve existing ordering — always append, never reorder.

The manifest structure:

```json
{
    "sections": [
        {
            "title": "Section Title",
            "slug": "section-slug",
            "pages": [
                { "title": "Page Title", "slug": "page-slug" }
            ]
        }
    ]
}
```

### 7. Verify

Read back the written file and confirm:

- Valid YAML front matter with `title` and `description`
- Proper heading structure (h2/h3 only)
- No broken code blocks
- The manifest entry matches the file location

Report the file path and the URL where the page will be accessible: `/docs/{section-slug}/{page-slug}`.

## Style Guidelines

- Use h2 (`##`) for major sections, h3 (`###`) for subsections — never h4 or deeper
- Keep front matter `description` under 160 characters
- Include a brief overview paragraph after the title before diving into sections
- Use tables for configuration options, parameters, and comparison lists
- Use blockquotes (`>`) for tips and important notes
- Use fenced code blocks with language hints (`php`, `bash`, `blade`, `json`)
- Don't over-document — focus on what users need to know, not implementation internals
- Match the tone of existing docs: concise, direct, practical
- Use bold (`**term**`) for key terms on first mention
- Use inline code (`` ` ``) for class names, method names, file paths, and config keys
