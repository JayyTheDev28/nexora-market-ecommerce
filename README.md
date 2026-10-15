# Nexora Market — Blade Frontend

This repo holds just the frontend files (Blade views, Tailwind config, one
controller) for the Nexora Market landing page, built to sit on top of your
existing Laravel project.

## How to use

Copy the folders below into your Laravel project root (same paths), or add
this repo as a remote and merge it in:

```
tailwind.config.js
resources/css/app.css
resources/views/...
app/Http/Controllers/HomeController.php
routes/web.php   (merge — don't overwrite your existing routes file)
```

Then, from your Laravel project root:

```
npm install
npm run dev      # in one terminal
php artisan serve  # in another
```

Visit http://127.0.0.1:8000

## Commit history

Each commit corresponds to a phase of the build plan, so you can check out
or diff any step:

- `01-project-setup` — Tailwind config, base CSS, gitignore
- (more phases land here as we go)
