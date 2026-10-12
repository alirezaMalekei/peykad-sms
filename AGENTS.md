# AGENT RULES (strict)

## Default mode: READ-ONLY
Unless my message contains an unlock keyword, you may ONLY read files,
explain, analyze, and propose changes as text or diffs.
You must NOT edit, create, rename, move, or delete any file.

## Unlock keywords
Edits are allowed ONLY when my message starts with one of these:
- `APPLY:`   make exactly the change I described
- `ADD:`     add new code/files I described
- `FIX:`     fix the specific problem I described
- `REFACTOR:` restructure only the code I named

Phrases like "can you look at", "what do you think", "why", "review",
or "check" are NOT permission to edit.

## Scope of an unlocked edit
- Change ONLY the files and code I named or that are strictly required.
- No unrelated refactoring, renaming, reformatting, or "cleanup".
- Don't touch other files. If another file must change, stop and ask.
- Keep the existing code style and structure.
- After the edit, list every file and line range you changed.
- After finishing, return to READ-ONLY mode automatically.

## Always ask first, even after an unlock keyword
- Running `php artisan migrate`, `migrate:fresh`, `migrate:rollback`, `db:wipe`, `db:seed`
- Anything that deletes data, drops tables/columns, or truncates tables
- `git commit`, `git push`, `git reset`, `git checkout .`, `git clean`, `git stash drop`
- Editing `.env`, keys, credentials, or production config
- `composer require/remove/update`, `npm install/uninstall`
- Editing anything in `vendor/`, `node_modules/`, `storage/`, `bootstrap/cache/`

## Laravel rules
- Never edit a migration that has already run; create a new one.
- Use `DB::table()` in migrations, not Eloquent models.
- Create migrations but never run them unless I say so.
- Use Form Requests for validation and keep controllers thin.
- Prefer Eloquent relationships; avoid raw queries and N+1 problems.
- Never remove or weaken authorization, validation, or CSRF protection.
- Match the Laravel version in composer.json; don't use newer-only features.

## Honesty and safety
- If unsure, ask. Do not guess or invent files, methods, or packages.
- Before any risky change, state the risk (data loss, breaking change).
- Never claim tests pass unless you actually ran them.
- Show a short plan and wait for my OK on any change touching 3+ files.