# Lane Runner — CLAUDE.md

Neon endless runner (PWA + Android TWA). Laravel 12 + Inertia + Vue 3 + Three.js/GSAP, Tailwind, Vite, SQLite. Live: https://lanerunner.on-forge.com/game

## Token rules (wichtig)
- `resources/js/Pages/Game.vue` ist ~8400 Zeilen: NIE komplett lesen. Immer grep nach Funktions-/Konstantennamen, dann Read mit offset/limit.
- `vendor/`, `node_modules/`, `build/`, `.gradle/`, `*.apk`, `*.aab` ignorieren.
- Struktur steht unten — kein exploratives ls/find nötig.

## Struktur
- `resources/js/Pages/Game.vue` — das GESAMTE Spiel: Three.js-Szene, World-Scroll-Engine, WebAudio-SFX, Input, Menüs, alle 3 Zonen (Run → Drive ab 10k → Fly ab 20k)
- `resources/js/game/constants.js` — Gameplay-Tuning (Lanes, Speed, FINALE_SCORE=10000). MUSS synchron bleiben mit Anti-Cheat-Bounds in RunnerController!
- `resources/js/game/audio.js`, `missions.js` — extrahierte Module
- `app/Http/Controllers/RunnerController.php` — server-autoritative Runs, duration-basiertes Anti-Cheat (`maxDistanceForDuration`, DRIVE_MAX_SPEED=160)
- `app/Services/RunnerProfileService.php` — Skin-Katalog (re-seedet sich beim Deploy selbst)
- `app/Http/Controllers/InventoryController.php` + `app/Services/InventoryService.php`
- `routes/web.php` — `/game` (ohne Auth), `api/runner/*` (profile, run/start, run/end, skin, skin/buy, leaderboard, mission/claim), `api/inventory/*` (auth)
- Models: User, RunnerProfile, Skin, UserItem, Item, MissionClaim
- PWA/TWA: `public/manifest.webmanifest`, `public/sw.js`, `twa-manifest.json`, Gradle-Dateien im Root (Bubblewrap-Android-Build)
- Play-Store-Material: `store-assets/`

## Befehle
- Build: `npm run build` · Dev: `npm run dev` + `php artisan serve` (→ localhost:8000/game)
- Migrieren: `php artisan migrate` · Format: `vendor/bin/pint`
- Dev-Cheat im Spiel: F9 → Zone-2-Finale, nochmal F9 → Zone-3-Rampe; Dev-Runs werden nie persistiert

## Regeln
- Gameplay-Werte ändern ⇒ beide Seiten prüfen: `constants.js` UND Anti-Cheat in RunnerController
- Daniel testet selbst — keine Tests schreiben/fixen, nur `npm run build` als Verifikation
- Nach fertiger Arbeit direkt committen und auf main pushen, nicht fragen
- Commit-Messages: kurzer englischer Imperativ-Einzeiler (siehe git log)
