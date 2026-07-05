# Lane Runner

A neon endless runner with a secret: run far enough and the game changes genre.
Free, no ads, no tracking. Live at **https://lanerunner.on-forge.com/game** —
installable as a PWA and shipped to Android as a Trusted Web Activity.

## The three zones

1. **Run** — dodge night-city traffic across three lanes: jump, slide,
   side-bump, chain near-misses into combos. Events break up the flow:
   rush-hour walls, wrong-way drivers, coin rushes, drifting cars.
2. **Drive** *(secret, at 10,000 points)* — the road ends in a plaza; board
   your getaway car for four-lane highway driving up to 160. Hold top speed
   long enough and the car goes god mode.
3. **Fly** *(secret, at 20,000 points in god mode)* — a ramp jump into the
   sunrise, a mid-air dock into a cargo plane, then free flight over green
   countryside with boss drones and an alien mothership.

## Stack

- **Backend:** Laravel 11, Breeze auth, SQLite/MySQL. Runs, coins, skins and
  the leaderboard are server-authoritative with a duration-based anti-cheat
  model (`app/Http/Controllers/RunnerController.php`).
- **Frontend:** Inertia + Vue 3. The entire game lives in
  `resources/js/Pages/Game.vue` — Three.js scene, world-scroll engine,
  synthesized WebAudio SFX, touch/keyboard input, all menus.
- **PWA/TWA:** `public/manifest.webmanifest`, `public/sw.js` (offline
  fallback, cache-first static assets), Bubblewrap config in
  `twa-manifest.json`, asset links in `public/.well-known/assetlinks.json`.

## Development

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate
npm run dev          # Vite dev server
php artisan serve    # http://localhost:8000/game
```

`npm run build` produces the production bundle. Deploys on Laravel Forge run
build + migrate automatically; skins re-seed themselves when the catalog in
`app/Services/RunnerProfileService.php` grows.

Dev cheat: **F9** jumps to the zone-2 finale, F9 again (while driving) to the
zone-3 ramp. Dev runs are never persisted.

## Assets & licenses

All third-party models are committed with their licenses next to them:

- **Kenney** Car Kit & Blocky Characters — CC0 (`public/models/carkit`,
  `public/models/characters`)
- **Quaternius** Astronaut, Ninja, Robot, Alien — CC0 (via Poly Pizza)
- **Cesium Air** plane — CC-BY 4.0 (`public/models/plane`)
- **Poly by Google** Jet & Airplane — CC-BY 3.0 (via Poly Pizza)

Play Store material (feature graphic, listing texts) lives in `store-assets/`.
