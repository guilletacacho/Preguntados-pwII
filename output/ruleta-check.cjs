const { chromium } = require('C:/Users/Pc/AppData/Local/npm-cache/_npx/48b1ca104c3549f4/node_modules/playwright');
const fs = require('node:fs');
const assert = require('node:assert/strict');
(async () => {
    const browser = await chromium.launch({ headless: true });
    for (const reducedMotion of ['no-preference', 'reduce']) {
      for (const width of [390, 1280]) {
        const page = await browser.newPage({ reducedMotion, viewport: { width, height: 700 } });
        page.on('pageerror', error => console.log('ERROR', error.message));
        await page.goto('http://localhost/login');
        let view = fs.readFileSync('app/view/ruletaView.mustache', 'utf8');
        view = view.replace(/{{#categorias}}([\s\S]*?){{\/categorias}}/g, (_, item) =>
            ['historia', 'arte', 'cine'].map((slug, index) => item
                .replace(/{{#seleccionada}}true{{\/seleccionada}}/g, index === 1 ? 'true' : '')
                .replaceAll('{{name}}', slug).replaceAll('{{slug}}', slug)).join(''))
            .replaceAll('{{basePath}}', '').replaceAll('{{puntos}}', '0');
        await page.setContent(`<link rel="stylesheet" href="/public/css/bootstrap.min.css"><link rel="stylesheet" href="/app/css/style.css"><main id="single-tab-status"></main><template id="single-tab-content">${view}</template><script src="/public/js/single-tab.js" data-base="" data-user="animation-test"></script>`);
        await page.waitForSelector('.roulette-spin');
        await page.click('.roulette-spin');
        await page.waitForTimeout(250);
        assert.equal(await page.locator('.roulette-track').evaluate(el => el.getAnimations().length), 1);
        assert.equal(await page.locator('.roulette-continue').isVisible(), false);
        await page.setViewportSize({width: width + 20, height:700});
        await page.locator('.roulette-continue').waitFor({state:'visible', timeout:8000});
        assert.equal(await page.locator('.roulette-spin').isVisible(), false);
        assert.equal(await page.locator('.is-winner').innerText(), 'CATEGORÍA\narte');
        const offset = await page.locator('.is-winner').evaluate(el => {
            const card=el.getBoundingClientRect(), viewport=el.closest('.roulette-window').getBoundingClientRect();
            return Math.abs(card.left+card.width/2-viewport.left-viewport.width/2);
        });
        assert.ok(offset < 2, 'winner centered after resizing');
        console.log('PASS', reducedMotion, width, 'animated, completed, winner centered after resize');
        await page.close();
      }
    }
    await browser.close();
})();
