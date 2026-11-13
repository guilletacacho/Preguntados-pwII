const { chromium } = require('C:/Users/Pc/AppData/Local/npm-cache/_npx/48b1ca104c3549f4/node_modules/playwright');
const assert = require('node:assert/strict');
(async () => {
    const browser = await chromium.launch({headless:true});
    const contexts = [];
    async function login(user) {
        const context = await browser.newContext(); contexts.push(context);
        const page = await context.newPage();
        page.on('pageerror', error => { throw error; });
        await page.goto('http://localhost/login');
        await page.locator('[name=Usuario]').fill(user);
        await page.locator('[name=Contrasena]').fill('Demo1234!');
        await page.locator('button[type=submit]').click();
        await page.waitForURL('**/home');
        await page.waitForSelector('[data-online-count]');
        return page;
    }
    async function ping(page) {
        return page.evaluate(async () => {
            const token=document.querySelector('script[data-token]').dataset.token;
            const response=await fetch('/presence/ping',{method:'POST',body:new URLSearchParams({token})});
            if(!response.ok) throw new Error('Ping '+response.status);
            return (await response.json()).online;
        });
    }
    try {
        const first=await login('demo13');
        const baseline=await ping(first);
        let fact=await first.locator('.home-curiosity p').innerText();
        const colors = await first.locator('.home-curiosity').evaluate(card => {
            const original = card.dataset.category;
            const colors = ['ciencia','geografia','deporte','arte','cine','historia'].map(category => {
                card.dataset.category=category;
                const actual=getComputedStyle(card.querySelector('.home-category')).color;
                const probe=document.createElement('span');
                probe.style.color=`var(--${category})`; card.append(probe);
                const expected=getComputedStyle(probe).color; probe.remove();
                return {category,actual,expected};
            });
            card.dataset.category=original;
            return colors;
        });
        for(const color of colors) assert.equal(color.actual,color.expected,color.category+' color');
        for(let i=0;i<4;i++) {
            await first.goto('http://localhost/perfil');
            await first.goto('http://localhost/home');
            await first.waitForSelector('.home-curiosity p');
            const next=await first.locator('.home-curiosity p').innerText();
            assert.notEqual(next,fact,'curiosity changes on returning home'); fact=next;
        }
        const second=await login('demo14');
        assert.equal(await ping(first),baseline+1,'another user increments count');
        await first.waitForFunction(expected=>document.querySelector('[data-online-count]').textContent.startsWith(String(expected)+' '),baseline+1,{timeout:20000});
        const duplicate=await login('demo14');
        assert.equal(await ping(first),baseline+1,'same user across sessions counts once');
        await second.goto('http://localhost/login/logout');
        assert.equal(await ping(first),baseline+1,'other session keeps user online');
        await duplicate.goto('http://localhost/login/logout');
        assert.equal(await ping(first),baseline,'last logout removes user');
        const rejected=await first.evaluate(async()=>({
            get:(await fetch('/presence/ping')).status,
            badToken:(await fetch('/presence/ping',{method:'POST',body:new URLSearchParams({token:'invalid'})})).status
        }));
        assert.equal(rejected.get,405); assert.equal(rejected.badToken,403);
        await first.goto('http://localhost/login/logout');
        const unauth=await first.evaluate(async()=>(await fetch('/presence/ping',{method:'POST'})).status);
        assert.equal(unauth,401);
        console.log('PASS: six category colors, changing curiosities, live polling, distinct users, multiple sessions, logout, endpoint authorization');
    } finally {
        for(const context of contexts) {
            await context.request.get('http://localhost/login/logout').catch(()=>{});
            await context.close();
        }
        await browser.close();
    }
})().catch(error=>{console.error(error);process.exitCode=1;});
