/*
 * Draws the shareable stats card (1200 x 630) on a canvas in the browser, so no image library is needed on the
 * server and nothing leaves your machine. Only text and shapes are drawn (no cover art), so the canvas stays
 * exportable. Usage: gamehubShareCard.draw(canvas, data) and gamehubShareCard.download(canvas, filename).
 */
(function () {
    var W = 1200;
    var H = 630;
    var TAN = Math.tan((14 * Math.PI) / 180);

    function slant(ctx, x, y, w, h, skew) {
        ctx.beginPath();
        ctx.moveTo(x + skew, y);
        ctx.lineTo(x + w, y);
        ctx.lineTo(x + w - skew, y + h);
        ctx.lineTo(x, y + h);
        ctx.closePath();
    }

    function fit(ctx, text, maxWidth) {
        if (ctx.measureText(text).width <= maxWidth) {
            return text;
        }

        while (text.length > 1 && ctx.measureText(text + '…').width > maxWidth) {
            text = text.slice(0, -1);
        }

        return text.replace(/\s+$/, '') + '…';
    }

    function number(n) {
        return Number(n).toLocaleString('en-US');
    }

    function loadFonts() {
        if (!document.fonts || !document.fonts.load) {
            return Promise.resolve();
        }

        return Promise.all([
            document.fonts.load('900 120px "Big Shoulders Display"'),
            document.fonts.load('800 36px "Big Shoulders Display"'),
            document.fonts.load('400 28px "Schibsted Grotesk"'),
            document.fonts.load('700 28px "Schibsted Grotesk"'),
        ]).catch(function () {});
    }

    function draw(canvas, data) {
        return loadFonts().then(function () {
            canvas.width = W;
            canvas.height = H;

            var ctx = canvas.getContext('2d');
            var display = '"Big Shoulders Display", "Arial Narrow", sans-serif';
            var body = '"Schibsted Grotesk", system-ui, sans-serif';

            // stage
            var bg = ctx.createLinearGradient(0, 0, W * 0.35, H);
            bg.addColorStop(0, '#131f78');
            bg.addColorStop(0.5, '#080e33');
            bg.addColorStop(1, '#060a26');
            ctx.fillStyle = bg;
            ctx.fillRect(0, 0, W, H);

            [[W * 0.92, -40, 520, 'rgba(255,90,160,.30)'], [40, H * 0.3, 560, 'rgba(61,123,255,.34)'], [W * 0.7, H + 40, 480, 'rgba(53,240,176,.16)']]
                .forEach(function (g) {
                    var r = ctx.createRadialGradient(g[0], g[1], 0, g[0], g[1], g[2]);
                    r.addColorStop(0, g[3]);
                    r.addColorStop(1, 'rgba(0,0,0,0)');
                    ctx.fillStyle = r;
                    ctx.fillRect(0, 0, W, H);
                });

            var skew = H * TAN;
            for (var x = -skew; x < W + skew; x += 330) {
                ctx.fillStyle = 'rgba(150,175,255,.07)';
                slant(ctx, x, 0, 5, H, -skew);
                ctx.fill();
                ctx.fillStyle = 'rgba(255,255,255,.035)';
                slant(ctx, x + 70, 0, 56, H, -skew);
                ctx.fill();
            }

            // brand
            [['#ff5a52', 12, 18], ['#ffc83d', 26, 30], ['#35f0b0', 40, 42], ['#8aa2ff', 54, 24]].forEach(function (b) {
                ctx.fillStyle = b[0];
                slant(ctx, 64 + b[1] - 12, 64 + 42 - b[2], 8, b[2], 3);
                ctx.fill();
            });
            ctx.fillStyle = '#eaf0ff';
            ctx.font = '800 40px ' + display;
            ctx.textBaseline = 'alphabetic';
            ctx.fillText('GameHub', 142, 104);

            if (data.handle) {
                ctx.font = '600 26px ' + body;
                ctx.fillStyle = '#9aa7de';
                ctx.textAlign = 'right';
                ctx.fillText('@' + data.handle, W - 64, 100);
                ctx.textAlign = 'left';
            }

            // headline number
            ctx.fillStyle = '#eaf0ff';
            ctx.font = '900 250px ' + display;
            ctx.fillText(number(data.hours), 56, 340);

            ctx.font = '700 34px ' + body;
            ctx.fillStyle = '#ffc83d';
            ctx.fillText('hours played', 64, 408);

            // secondary numbers
            var stats = [[data.games, data.games === 1 ? 'game' : 'games'], [data.finished, 'finished']];
            if (data.yearHours > 0) {
                stats.push([data.yearHours, 'hours in 12 months']);
            }

            var sx = 64;
            stats.forEach(function (s) {
                ctx.fillStyle = '#eaf0ff';
                ctx.font = '900 72px ' + display;
                ctx.fillText(number(s[0]), sx, 492);
                var w = ctx.measureText(number(s[0])).width;
                ctx.fillStyle = '#9aa7de';
                ctx.font = '500 24px ' + body;
                ctx.fillText(s[1], sx + w + 12, 492);
                sx += w + 12 + ctx.measureText(s[1]).width + 44;
            });

            // most played
            if (data.top && data.top.length) {
                var colX = 760;
                var colW = W - 64 - colX;
                var maxHours = Math.max.apply(null, data.top.map(function (t) { return t.hours; })) || 1;

                ctx.fillStyle = '#9aa7de';
                ctx.font = '700 22px ' + body;
                ctx.fillText('Most played', colX, 190);

                data.top.forEach(function (t, i) {
                    var y = 244 + i * 100;
                    ctx.fillStyle = '#eaf0ff';
                    ctx.font = '800 42px ' + display;
                    ctx.fillText(fit(ctx, t.title, colW - 96), colX, y);

                    ctx.fillStyle = '#9aa7de';
                    ctx.font = '600 26px ' + body;
                    ctx.textAlign = 'right';
                    ctx.fillText(number(Math.round(t.hours)) + ' h', colX + colW, y);
                    ctx.textAlign = 'left';

                    ctx.fillStyle = 'rgba(138,162,255,.18)';
                    slant(ctx, colX, y + 14, colW, 10, 4);
                    ctx.fill();
                    ctx.fillStyle = ['#ffc83d', '#35f0b0', '#8aa2ff'][i % 3];
                    slant(ctx, colX, y + 14, Math.max(14, colW * (t.hours / maxHours)), 10, 4);
                    ctx.fill();
                });
            }

            // status spectrum
            var total = (data.statuses || []).reduce(function (a, s) { return a + s.count; }, 0);
            if (total > 0) {
                var gap = 6;
                var avail = W - 128 - gap * (data.statuses.length - 1);
                var px = 64;

                data.statuses.forEach(function (s) {
                    var w = Math.max(16, (avail * s.count) / total);
                    ctx.fillStyle = s.color;
                    slant(ctx, px, 556, w, 22, 8);
                    ctx.fill();
                    px += w + gap;
                });
            }
        });
    }

    function download(canvas, filename) {
        var link = document.createElement('a');
        link.download = filename || 'gamehub-card.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    }

    window.gamehubShareCard = { draw: draw, download: download };
})();
