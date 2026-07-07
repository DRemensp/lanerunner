import { chromium } from 'playwright-core';
import { execSync } from 'node:child_process';
import fs from 'node:fs';

const shell = fs.readdirSync(process.env.HOME + '/.cache/ms-playwright')
  .find(d => d.startsWith('chromium-'));
const exe = `${process.env.HOME}/.cache/ms-playwright/${shell}/chrome-linux64/chrome`;

const browser = await chromium.launch({
  executablePath: exe,
  args: ['--use-gl=swiftshader', '--enable-unsafe-swiftshader', '--no-sandbox'],
});
const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
page.on('console', m => console.log('[console]', m.type(), m.text().slice(0, 300)));
page.on('pageerror', e => console.log('[pageerror]', e.message));

await page.goto('http://localhost:8123/game', { waitUntil: 'networkidle' });
await page.waitForTimeout(3000);

// Guest-Prompt wegklicken, falls vorhanden
const guestBtn = page.locator('button', { hasText: /continue as guest/i }).first();
if (await guestBtn.count()) {
  await guestBtn.click();
  console.log('clicked guest');
  await page.waitForTimeout(2000);
}
await page.screenshot({ path: '/tmp/menu.png' });

// Showroom-Button suchen
const btns = await page.locator('button').allTextContents();
console.log('BUTTONS:', JSON.stringify(btns));
const showroomBtn = page.locator('button', { hasText: /showroom|gallery/i }).first();
if (await showroomBtn.count()) {
  await showroomBtn.click();
  await page.waitForTimeout(4000);
  await page.screenshot({ path: '/tmp/showroom.png' });
  console.log('clicked showroom');
} else {
  console.log('NO SHOWROOM BUTTON FOUND');
}
await browser.close();
