<footer class="site-footer">
    <div style="max-width:1400px;margin:auto;">
        <h2>MERCATO NOVA</h2>
        <p style="color:var(--text-soft);margin-top:8px;">Marketplace Gaming nouvelle génération — Achetez, vendez, négociez.</p>
        <div class="footer-links">
            <a href="mentions_legales.php">Mentions légales</a>
            <a href="confidentialite.php">Confidentialité</a>
            <a href="support.php">Support</a>
            <?php if (($_SESSION['role'] ?? 0) < 1): ?>
            <a href="devenir_vendeur.php" style="color:var(--neon-yellow);">🏪 Devenir vendeur</a>
            <?php else: ?>
            <a href="home.php?menu=Espace Vendeurs">🏪 Espace Vendeur</a>
            <?php endif; ?>
        </div>
        <p class="footer-copy">© 2026 Mercato Nova. Tous droits réservés.</p>
    </div>
</footer>
