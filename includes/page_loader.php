<!-- ══ PAGE LOADER ══ -->
<div id="pageLoader">
    <div class="loader-inner">
        <div class="loader-logo">BAIL<span>MANAGER</span></div>
        <div class="loader-bar"><div class="loader-bar-fill"></div></div>
    </div>
</div>
<style>
/* ── Page loader ──────────────────────────────────────────── */
#pageLoader {
    position: fixed;
    inset: 0;
    background: var(--menu-color, #14305c);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity .25s ease, visibility .25s ease;
}
#pageLoader.hidden {
    opacity: 0;
    visibility: hidden;
}
.loader-inner { text-align: center; }
.loader-logo {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: 2px;
    color: #fff;
    margin-bottom: 28px;
}
.loader-logo span { color: #e53e3e; }
.loader-bar {
    width: 180px;
    height: 3px;
    background: rgba(255,255,255,.15);
    border-radius: 3px;
    overflow: hidden;
    margin: 0 auto;
}
.loader-bar-fill {
    height: 100%;
    width: 0%;
    background: #e53e3e;
    border-radius: 3px;
    animation: loaderFill .6s ease-in-out forwards;
}
@keyframes loaderFill {
    0%   { width: 0%; }
    60%  { width: 75%; }
    100% { width: 100%; }
}
</style>
<script>
/* Masque le loader dès que le HTML est prêt (n'attend pas les images/polices comme
   le ferait "load") — reste rapide même sur une page riche en images. Un filet de
   sécurité à 1s masque le loader même en cas de script bloquant imprévu. */
(function() {
    function hidePageLoader() {
        var l = document.getElementById('pageLoader');
        if (l) l.classList.add('hidden');
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hidePageLoader);
    } else {
        hidePageLoader();
    }
    setTimeout(hidePageLoader, 1000);
})();
</script>
